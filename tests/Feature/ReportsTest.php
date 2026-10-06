<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Inertia\Testing\AssertableInertia;
use JothamLec\MarketingToolkit\Fieldtypes\SeoPreview;
use JothamLec\MarketingToolkit\Reports\Report;
use JothamLec\MarketingToolkit\Reports\ReportPage;
use JothamLec\MarketingToolkit\Reports\ReportSettings;
use JothamLec\MarketingToolkit\Reports\Runner;
use JothamLec\MarketingToolkit\Reports\RunReportStep;
use JothamLec\MarketingToolkit\ServiceProvider;
use JothamLec\MarketingToolkit\Widgets\SeoWidget;
use Statamic\Facades\Collection;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;

beforeEach(fn () => seoGlobal([]));

/**
 * @param  array<string, mixed>  $values
 */
function reportSettings(array $values = []): ReportSettings
{
    $settings = new ReportSettings($values);
    app()->instance(ReportSettings::class, $settings);

    return $settings;
}

function fullReport(): Report
{
    $runner = app(Runner::class);

    return $runner->runToEnd($runner->start());
}

function reportPage(Report $report, string $path): ReportPage
{
    return $report->pages()->where('url', 'https://example.test'.$path)->sole();
}

test('a link check opens every published page and lists what to fix', function () {
    entryIn('home', 'home', ['title' => 'Home', 'description' => 'The home page.']);
    entryIn('pages', 'about', ['title' => 'About', 'description' => 'Who we are.']);
    entryIn('pages', 'team', ['title' => 'Team', 'body' => '<a href="/nowhere">x</a>']);
    entryIn('pages', 'hidden', ['seo' => ['noindex' => true], 'body' => '<a href="/gone">x</a>']);
    entryIn('pages', 'draft')->published(false)->save();

    $report = fullReport();

    expect($report->status)->toBe(Report::DONE)
        ->and($report->pages_total)->toBe(4)
        ->and($report->pages_done)->toBe(4)
        ->and($report->score)->toBeNull()
        ->and($report->summary)->toMatchArray(['checked' => 3, 'noindex' => 1, 'errors' => 0, 'with_issues' => 2])
        ->and($report->summary['rules']['broken_links']['fail'])->toBe(2)
        ->and($report->summary['rules']['description']['fail'])->toBe(1)
        ->and($report->pages()->pluck('url')->all())->not->toContain('https://example.test/draft');

    $team = reportPage($report, '/team');
    expect($team->results['broken_links'])->toBe(['status' => 'fail', 'message' => 'seo::reports.messages.links_broken', 'params' => ['links' => '/nowhere']])
        ->and($team->results['description']['status'])->toBe('fail')
        ->and($team->results['og_image']['status'])->toBe('pass')
        ->and($team->failing)->toBe(',broken_links:fail,description:fail,');

    // Hidden from search engines: its links are checked, not its description or image.
    $hidden = reportPage($report, '/hidden');
    expect(array_keys($hidden->results))->toBe(['broken_links', 'external_links'])
        ->and(reportPage($report, '/about')->failing)->toBeNull();
});

test('outside production the environment’s noindex is ignored, so a local report means something', function () {
    $this->app['env'] = 'local';
    entryIn('pages', 'about');

    $facts = reportPage(fullReport(), '/about')->facts();

    expect($facts->noindex())->toBeFalse()->and(config('seo.robots.noindex_outside_production'))->toBeTrue();
});

test('a report runs in steps of the chunk size', function () {
    reportSettings(['chunk_size' => 2]);
    foreach (['a', 'b', 'c', 'd', 'e'] as $slug) {
        entryIn('pages', $slug);
    }

    $runner = app(Runner::class);
    $report = $runner->start();

    expect($runner->step($report)->only(['status', 'pages_done']))->toBe(['status' => 'running', 'pages_done' => 2])
        ->and($runner->step($report)->pages_done)->toBe(4)
        ->and($runner->step($report)->only(['status', 'pages_done']))->toBe(['status' => 'done', 'pages_done' => 5]);
});

test('the external link check turned off, left-out collections and the page limit', function () {
    reportSettings(['external_links' => false, 'exclude_collections' => ['essays'], 'max_pages' => 2]);
    entryIn('essays', 'left-out', date: '2026-01-01');
    foreach (['a', 'b', 'c'] as $slug) {
        entryIn('pages', $slug);
    }

    $report = fullReport();

    expect($report->pages_total)->toBe(2)
        ->and($report->summary['rules'])->not->toHaveKey('external_links')
        ->and($report->pages()->first()->results)->not->toHaveKey('external_links')
        ->and($report->pages()->pluck('url')->all())->not->toContain('https://example.test/essays/left-out');
});

test('a page that fails to render is listed, and says why', function () {
    Collection::make('broken')->routes('broken/{slug}')->template('missing-template')->save();
    entryIn('broken', 'page');

    $report = fullReport();
    $page = reportPage($report, '/broken/page');

    expect($page->results['render']['status'])->toBe('fail')
        ->and($report->summary['errors'])->toBe(1)
        ->and($report->summary['with_issues'])->toBe(1);

    $this->actingAs(cpUser(super: true));
    expect($this->getJson(cp_route('seo.reports.pages', $report))->json('data.0.issues.0'))
        ->toMatchArray(['label' => 'Page loads', 'status' => 'fail'])
        ->and($this->getJson(cp_route('seo.reports.pages', $report))->json('data.0.issues.0.message'))->not->toStartWith('seo::');
});

test('only the newest reports are kept', function () {
    reportSettings(['keep_reports' => 2]);
    entryIn('pages', 'about');

    $ids = [fullReport()->id, fullReport()->id, fullReport()->id];

    expect(Report::query()->pluck('id')->all())->toBe(array_slice($ids, 1))
        ->and(ReportPage::query()->distinct()->pluck('report_id')->sort()->values()->all())->toBe(array_slice($ids, 1));
});

test('starting while a report runs returns that report; one that stopped moving is given up', function () {
    entryIn('pages', 'about');
    $runner = app(Runner::class);

    $running = $runner->start();
    expect($runner->start()->id)->toBe($running->id);

    $running->forceFill(['updated_at' => now()->subHour()])->saveQuietly();
    $next = $runner->start();

    expect($next->id)->not->toBe($running->id)
        ->and($running->fresh()->status)->toBe(Report::FAILED);
});

test('php please seo:report runs a whole check and prints what to fix', function () {
    entryIn('pages', 'about');

    $this->artisan('statamic:seo:report')
        ->expectsOutputToContain('1 of 1 pages have something to fix')
        ->expectsOutputToContain('Description')
        ->assertSuccessful();

    expect(Report::query()->sole()->status)->toBe(Report::DONE);
});

test('without a queue worker the CP advances a report one step per progress request', function () {
    reportSettings(['chunk_size' => 1]);
    entryIn('pages', 'a');
    entryIn('pages', 'b');
    $this->actingAs(cpUser(['view seo', 'run seo reports']));

    $started = $this->postJson(cp_route('seo.reports.run'))->assertOk()->json();
    expect($started)->toMatchArray(['status' => 'running', 'pages_total' => 2, 'pages_done' => 0]);

    $this->postJson($started['progress_url'])->assertJson(['status' => 'running', 'pages_done' => 1]);
    $this->postJson($started['progress_url'])->assertJson(['status' => 'done', 'pages_done' => 2]);
});

test('with a queue worker the run is queued, and each step queues the next', function () {
    config(['queue.default' => 'database', 'queue.connections.database.driver' => 'database']);
    Queue::fake();
    reportSettings(['chunk_size' => 1]);
    entryIn('pages', 'a');
    entryIn('pages', 'b');
    $this->actingAs(cpUser(['view seo', 'run seo reports']));

    $report = $this->postJson(cp_route('seo.reports.run'))->json();
    Queue::assertPushed(RunReportStep::class, fn ($job) => $job->reportId === $report['id']);

    // The progress request only reads when a worker does the work.
    $this->postJson($report['progress_url'])->assertJson(['pages_done' => 0]);

    (new RunReportStep($report['id']))->handle(app(Runner::class));
    Queue::assertPushed(RunReportStep::class, 2);
});

test('the link check screens list only the pages to fix, filtered by a check', function () {
    entryIn('pages', 'about', ['description' => 'Fine.', 'body' => '<a href="/nowhere">x</a>']);
    entryIn('pages', 'team');
    entryIn('pages', 'fine', ['description' => 'Fine.']);
    $report = fullReport();
    $this->actingAs(cpUser(super: true));

    $this->get(cp_route('seo.reports.index'))->assertInertia(fn (AssertableInertia $page) => $page
        ->component('seo::Reports', false)
        ->where('reports.0.id', $report->id)
        ->where('reports.0.with_issues', 2)
        ->where('canRun', true));

    $this->get(cp_route('seo.reports.show', $report))->assertInertia(fn (AssertableInertia $page) => $page
        ->component('seo::Report', false)
        ->where('counts.checked', 3)
        ->where('counts.with_issues', 2)
        ->where('rules', fn ($rules) => collect($rules)->pluck('label')->contains('Broken links')));

    expect($this->getJson(cp_route('seo.reports.pages', $report))->json('data.*.path'))->toEqualCanonicalizing(['/about', '/team']);

    $flagged = $this->getJson(cp_route('seo.reports.pages', [$report, 'rule' => 'broken_links']))->assertOk();
    expect($flagged->json('data.*.path'))->toBe(['/about'])
        ->and($flagged->json('data.0.issues.0'))->toMatchArray(['label' => 'Broken links', 'status' => 'fail', 'message' => 'Links to pages that don’t exist: /nowhere.'])
        ->and($flagged->json('data.0.edit_url'))->toContain('/cp/collections/pages/entries/');
});

test('viewing reports needs "view seo"; running one needs "run seo reports"', function () {
    entryIn('pages', 'about');
    $report = fullReport();

    $this->actingAs(cpUser(['view seo']));
    $this->get(cp_route('seo.reports.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('canRun', false));
    $this->get(cp_route('seo.reports.show', $report))->assertOk();
    $this->postJson(cp_route('seo.reports.run'))->assertForbidden();
});

test('the dashboard widget shows the latest finished check', function () {
    entryIn('pages', 'about');
    $report = fullReport();
    $this->actingAs(cpUser(['view seo']));

    expect((new SeoWidget)->component()->toArray()['props']['report'])->toMatchArray([
        'issues' => 1,
        'pages' => 1,
        'url' => cp_route('seo.reports.show', $report),
    ]);
});

test('the check runs weekly unless config/seo.php says otherwise', function () {
    $events = function (array $settings) {
        reportSettings($settings);
        $schedule = new Schedule;
        (fn () => $this->schedule($schedule))->call(app()->getProvider(ServiceProvider::class));

        return collect($schedule->events())->filter(fn ($event) => str_contains((string) $event->command, 'seo:report'))->map(fn ($event) => $event->expression)->values()->all();
    };

    expect($events([]))->toBe(['0 3 * * 1'])
        ->and($events(['schedule' => false]))->toBe([])
        ->and($events(['schedule' => 'daily', 'schedule_time' => '04:30']))->toBe(['30 4 * * *'])
        ->and($events(['schedule' => 'weekly', 'schedule_day' => 'wednesday', 'schedule_time' => '03:00']))->toBe(['0 3 * * 3']);
});

test('the schedule is built only for the commands that need it', function (string $command, bool $built) {
    reportSettings(['schedule' => 'daily']);
    app()->instance(Schedule::class, $schedule = new Schedule);
    $argv = $_SERVER['argv'];
    $_SERVER['argv'] = ['artisan', $command];

    try {
        (fn () => $this->bootSchedule())->call(app()->getProvider(ServiceProvider::class));
    } finally {
        $_SERVER['argv'] = $argv;
    }

    expect(collect($schedule->events())->contains(fn ($event) => str_contains((string) $event->command, 'seo:report')))->toBe($built);
})->with([
    'schedule:run' => ['schedule:run', true],
    'schedule:finish, which finishes a background event' => ['schedule:finish', true],
    'schedule:list' => ['schedule:list', true],
    'a queue worker' => ['queue:work', false],
    'any other command' => ['migrate', false],
]);

test('the preview counters turn amber where Google cuts a title or description short', function () {
    config(['seo.title.max' => 70]);

    expect((new SeoPreview)->preload()['limits'])->toBe(['title' => [30, 70], 'description' => [50, 160]]);
});

test('config/seo.php sets the link check', function () {
    config(['seo.reports' => ['schedule' => 'daily', 'external_links' => false, 'exclude_collections' => ['essays']]]);
    $settings = new ReportSettings;

    expect($settings->get('schedule'))->toBe('daily')
        ->and($settings->ruleEnabled('external_links'))->toBeFalse()
        ->and($settings->ruleEnabled('broken_links'))->toBeTrue()
        ->and($settings->excludedCollections())->toBe(['essays'])
        ->and($settings->int('keep_reports'))->toBe(10);
});

test('a Blade page that shows validation errors renders in a report run from the console or a queue', function () {
    entryIn('pages', 'contact', ['template' => 'form']);

    $facts = reportPage(fullReport(), '/contact')->facts();

    expect($facts->error)->toBeNull()->and($facts->status)->toBe(200)->and($facts->title)->not->toBeNull();
});

test('only one report starts at a time', function () {
    Sleep::fake(syncWithCarbon: true);
    $other = Cache::lock('seo:reports:start', 120);
    $other->get();

    // Another process is starting one: this start waits, then gives up rather than starting a second.
    expect(fn () => app(Runner::class)->start())->toThrow(LockTimeoutException::class)
        ->and(Report::query()->count())->toBe(0);

    $other->release();
    app(Runner::class)->start();
    expect(Report::query()->count())->toBe(1);
});

function reportOnAboutAndATerm(): array
{
    Taxonomy::make('topics')->save();
    $term = tap(Term::make()->taxonomy('topics')->slug('gardens')->data(['title' => 'Gardens']))->save();
    $entry = entryIn('pages', 'about');
    $report = Report::query()->create(['settings' => [], 'status' => Report::DONE, 'summary' => ['rules' => []]]);
    $report->pages()->create(['url' => 'https://example.test/about', 'content_type' => 'entry', 'content_id' => $entry->id(), 'checked' => true, 'failing' => ',og_image:fail,']);
    $report->pages()->create(['url' => 'https://example.test/topics/gardens', 'content_type' => 'term', 'content_id' => $term->id(), 'checked' => true, 'failing' => ',og_image:fail,']);

    return [$report, $entry, $term];
}

test('a report page links to the entry or term behind it', function () {
    [$report, $entry, $term] = reportOnAboutAndATerm();
    $this->actingAs(cpUser(super: true));

    expect($this->getJson(cp_route('seo.reports.pages', $report))->json('data.*.edit_url'))
        ->toBe([$entry->editUrl(), $term->in('default')->editUrl()]);
});

test('a report page has no edit link for someone who may not edit it', function () {
    [$report] = reportOnAboutAndATerm();
    $this->actingAs(cpUser(['view seo']));

    expect($this->getJson(cp_route('seo.reports.pages', $report))->json('data.*.edit_url'))->toBe([null, null]);
});

test('a check asks other sites about links to them unless config turns it off', function () {
    Http::fake(['gone.test/*' => Http::response('', 404)]);
    fakeDns(['gone.test' => '93.184.215.14']);
    entryIn('pages', 'about', ['body' => '<a href="https://gone.test/a">Gone</a>']);

    expect(reportPage(fullReport(), '/about')->facts['brokenExternalLinks'])->toBe(['https://gone.test/a']);

    Http::fake();
    reportSettings(['external_links' => false]);

    expect(reportPage(fullReport(), '/about')->facts['brokenExternalLinks'])->toBe([]);
    Http::assertNothingSent();
});

test('a title longer than its column is cut to fit, so the report still runs on MySQL and Postgres', function () {
    entryIn('pages', 'long', ['title' => str_repeat('Long ', 60)]);

    $page = reportPage(fullReport(), '/long');

    expect(mb_strlen($page->title))->toBe(255)
        ->and($page->facts['title'])->toStartWith('Long Long');
});

test('a check reads in the language of whoever opens it', function () {
    app('translator')->addLines([
        'reports.rules.broken_links' => 'Liens cassés',
        'reports.messages.links_broken' => 'Liens vers des pages qui n’existent pas : :links.',
        'reports.messages.and_more' => ':list et :count autre|:list et :count autres',
    ], 'fr', 'seo');
    entryIn('pages', 'about', ['body' => '<a href="/1">1</a><a href="/2">2</a><a href="/3">3</a><a href="/4">4</a><a href="/5">5</a><a href="/6">6</a><a href="/7">7</a>']);
    $report = fullReport();
    $this->actingAs(cpUser(super: true));
    app()->setLocale('fr');

    $this->get(cp_route('seo.reports.show', $report))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('rules.0.label', 'Liens cassés'));

    // An untranslated line falls back to English.
    expect($this->getJson(cp_route('seo.reports.pages', $report))->json('data.0.issues'))->toBe([
        ['label' => 'Liens cassés', 'status' => 'fail', 'message' => 'Liens vers des pages qui n’existent pas : /1, /2, /3, /4, /5 et 2 autres.'],
        ['label' => 'Description', 'status' => 'fail', 'message' => 'No description: Google will pick text from the page for its search result.'],
    ]);
});

test('php please seo:report prints a check’s name, not its key', function () {
    entryIn('pages', 'about');

    $this->artisan('statamic:seo:report')
        ->doesntExpectOutputToContain('seo::reports')
        ->assertSuccessful();
});
