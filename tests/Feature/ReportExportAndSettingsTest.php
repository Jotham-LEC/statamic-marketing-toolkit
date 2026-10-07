<?php

use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;
use JothamLec\MarketingToolkit\Reports\Report;
use JothamLec\MarketingToolkit\Reports\Runner;
use JothamLec\MarketingToolkit\Support\Package;
use Statamic\Facades\Addon;

// The addon's settings are a file that outlives a test: the settings test writes it.
afterEach(fn () => File::delete(resource_path('addons/marketing-toolkit.yaml')));

test('a report downloads as CSV, worst pages first, with the checks each failed', function () {
    entryIn('pages', 'about', ['title' => 'Same']);
    entryIn('pages', 'team', ['title' => '=HYPERLINK("https://evil.test")']);
    $runner = app(Runner::class);
    $report = $runner->runToEnd($runner->start());
    $this->actingAs(cpUser(['view marketing toolkit']));

    $csv = $this->get(cp_route('mt.reports.export', $report))->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename=report-'.$report->id.'-'.$report->created_at->format('Y-m-d').'.csv')
        ->streamedContent();
    $rows = array_map(fn (string $line) => str_getcsv($line, escape: ''), array_filter(explode("\n", $csv)));

    expect($rows[0])->toBe(array_map(fn (string $column) => __('marketing-toolkit::reports.cp.csv.'.$column), ['url', 'title', 'score', 'failed', 'warnings']))
        ->and(count($rows))->toBe($report->pages()->count() + 1)
        ->and(array_map(fn (array $row) => (int) $row[2], array_slice($rows, 1)))->toBe(collect(array_slice($rows, 1))->map(fn ($row) => (int) $row[2])->sort()->values()->all())
        // A title a spreadsheet would run as a formula is kept as text.
        ->and(collect($rows)->firstWhere(1, '\'=HYPERLINK("https://evil.test")'))->not->toBeNull();
});

test('a report of another site is not exported', function () {
    multisite();
    entryIn('pages', 'about');
    $runner = app(Runner::class);
    $report = $runner->runToEnd($runner->start(site: 'cothinking'));
    $this->actingAs(cpUser(super: true));

    $this->get(cp_route('mt.reports.export', $report))->assertNotFound();
});

test('the Reports screen has a Settings tab that saves the report settings, keeping the other addon settings', function () {
    $addon = Addon::get(Package::NAME);
    $addon->settings()->set('features_off', ['llms_txt'])->save();
    $this->actingAs(cpUser(super: true));

    $screen = $this->get(cp_route('mt.reports.index'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('settings.submitUrl', cp_route('mt.reports.settings'))
        ->where('settings.values.keep_reports', 10)
        ->has('settings.blueprint.tabs'));
    $values = $screen->viewData('page')['props']['settings']['values'];

    // As the form sends it: every value, three of them changed.
    $this->postJson(cp_route('mt.reports.settings'), [...$values, 'keep_reports' => 3, 'schedule' => 'weekly', 'title_max' => 65])->assertOk();

    $settings = Addon::get(Package::NAME)->settings();
    expect($settings->raw()['keep_reports'])->toBe(3)
        ->and($settings->get('schedule'))->toBe('weekly')
        ->and($settings->get('features_off'))->toBe(['llms_txt']);
});

test('saving the Settings tab leaves the settings set on other screens, however stale its copy of them', function () {
    $addon = Addon::get(Package::NAME);
    $addon->settings()->set('features_off', ['llms_txt'])->set('search_console_property', 'sc-domain:old.test')->save();
    $this->actingAs(cpUser(super: true));

    $values = $this->get(cp_route('mt.reports.index'))->viewData('page')['props']['settings']['values'];

    // Meanwhile, in another tab, a feature is switched off and the property changed.
    Addon::get(Package::NAME)->settings()->set('features_off', ['llms_txt', 'sitemap'])->set('search_console_property', 'sc-domain:new.test')->save();

    $this->postJson(cp_route('mt.reports.settings'), [...$values, 'keep_reports' => 3])->assertOk();
    // A form that leaves them out doesn't empty them either.
    $this->postJson(cp_route('mt.reports.settings'), collect($values)->except(['features_off', 'search_console_property', 'search_console_properties'])->all())->assertOk();

    $settings = Addon::get(Package::NAME)->settings();
    expect($settings->get('features_off'))->toBe(['llms_txt', 'sitemap'])
        ->and($settings->get('search_console_property'))->toBe('sc-domain:new.test')
        ->and($settings->raw()['keep_reports'])->toBe(10);
});

test('the Settings tab is only for those who may change the addon settings', function () {
    $this->actingAs(cpUser(['view marketing toolkit', 'run marketing toolkit reports']));

    $this->get(cp_route('mt.reports.index'))->assertInertia(fn (AssertableInertia $page) => $page->where('settings', null));
    $this->postJson(cp_route('mt.reports.settings'), ['keep_reports' => 3])->assertForbidden();
});

test('a report that failed before it scored any page still has every count, and its error', function () {
    $report = Report::query()->create(['status' => Report::FAILED, 'error' => 'The report stopped.', 'settings' => [], 'pages_total' => 0, 'pages_done' => 0, 'summary' => []]);
    $this->actingAs(cpUser(['view marketing toolkit']));

    $this->get(cp_route('mt.reports.show', $report))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('counts', ['scored' => 0, 'noindex' => 0, 'errors' => 0])
        ->where('report.status', 'failed')
        ->where('report.error', 'The report stopped.'));
});

test('a running report says whether watching it moves it on', function () {
    entryIn('pages', 'a');
    $report = app(Runner::class)->start();

    $this->actingAs(cpUser(['view marketing toolkit']));
    $this->postJson(cp_route('mt.reports.progress', $report))->assertJson(['status' => 'running', 'advancing' => false]);

    config(['queue.default' => 'database', 'queue.connections.database.driver' => 'database']);
    $this->postJson(cp_route('mt.reports.progress', $report))->assertJson(['status' => 'running', 'advancing' => true]);
});
