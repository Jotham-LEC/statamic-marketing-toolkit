<?php

use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia;
use JothamLec\Seo\Actions\CreateRedirect;
use JothamLec\Seo\Actions\DeleteSeoRecords;
use JothamLec\Seo\NotFound\MissingPath;
use JothamLec\Seo\Redirects\Redirect;
use Statamic\Actions\Action;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;

test('the redirects screen and its listing: search, sort, pagination, columns', function () {
    $this->actingAs(cpUser(['manage seo redirects']));
    Redirect::query()->create(['source' => '/b-old', 'target' => '/b-new']);
    Redirect::query()->create(['source' => '/a-old', 'target' => '/a-new', 'automatic' => true]);
    Redirect::query()->create(['source' => '/gone', 'status' => 410]);

    $this->get(cp_route('seo.redirects.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('seo::Redirects', false)->where('listingUrl', cp_route('seo.redirects.listing')));

    $listing = $this->getJson(cp_route('seo.redirects.listing', ['sort' => 'source', 'order' => 'asc', 'perPage' => 10]))->assertOk();

    expect($listing->json('data.*.source'))->toBe(['/a-old', '/b-old', '/gone'])
        ->and($listing->json('data.0'))->toMatchArray(['target' => '/a-new', 'status' => 301, 'active' => true, 'automatic' => true, 'hits' => 0])
        ->and($listing->json('meta'))->toMatchArray(['current_page' => 1, 'last_page' => 1, 'total' => 3])
        ->and($listing->json('meta.columns.*.field'))->toBe(['source', 'target', 'status', 'active', 'hits', 'last_hit_at']);

    expect($this->getJson(cp_route('seo.redirects.listing', ['search' => 'b-n']))->json('data.*.source'))->toBe(['/b-old']);
    expect($this->getJson(cp_route('seo.redirects.listing', ['sort' => 'nope; drop table']))->json('data.*.source'))->toBe(['/a-old', '/b-old', '/gone']);
});

test('creating a redirect through the publish form, and the checks it must pass', function () {
    $this->actingAs(cpUser(['manage seo redirects']));

    $this->get(cp_route('seo.redirects.create', ['source' => '/from-a-404']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('seo::RedirectForm', false)
            ->where('values.source', '/from-a-404')
            ->where('values.status', '301')
            ->where('submitMethod', 'post'));

    $response = $this->postJson(cp_route('seo.redirects.store'), ['source' => '/blog/*', 'target' => '/essays/$1', 'status' => '302', 'active' => true])->assertOk();
    $redirect = Redirect::query()->sole();

    expect($response->json('redirect'))->toBe(cp_route('seo.redirects.edit', $redirect))
        ->and($redirect->only(['source', 'target', 'status', 'active', 'automatic']))->toBe(['source' => '/blog/*', 'target' => '/essays/$1', 'status' => 302, 'active' => true, 'automatic' => false]);

    $this->postJson(cp_route('seo.redirects.store'), ['source' => '/blog/*/', 'target' => '/x', 'status' => '301'])->assertJsonValidationErrors(['source' => 'already starts']);
    $this->postJson(cp_route('seo.redirects.store'), ['source' => 'no-slash', 'target' => '/x', 'status' => '301'])->assertJsonValidationErrors('source');
    $this->postJson(cp_route('seo.redirects.store'), ['source' => '/q?x=1', 'target' => '/x', 'status' => '301'])->assertJsonValidationErrors('source');
    $this->postJson(cp_route('seo.redirects.store'), ['source' => '/a', 'target' => '', 'status' => '301'])->assertJsonValidationErrors('target');
    $this->postJson(cp_route('seo.redirects.store'), ['source' => '/a', 'target' => 'ftp://x', 'status' => '301'])->assertJsonValidationErrors('target');
    $this->postJson(cp_route('seo.redirects.store'), ['source' => '/a', 'target' => '/x/$2', 'status' => '301'])->assertJsonValidationErrors(['target' => 'has no *']);
    $this->postJson(cp_route('seo.redirects.store'), ['source' => '/a', 'target' => '/x', 'status' => '307'])->assertJsonValidationErrors('status');
    $this->postJson(cp_route('seo.redirects.store'), ['source' => '/gone', 'target' => '', 'status' => '410'])->assertOk();
});

test('editing a redirect, which also makes an automatic one manual', function () {
    $this->actingAs(cpUser(['manage seo redirects']));
    $redirect = Redirect::query()->create(['source' => '/old', 'target' => '/new', 'automatic' => true]);

    $this->get(cp_route('seo.redirects.edit', $redirect))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('values.target', '/new')->where('submitMethod', 'patch')->where('stats.automatic', true));

    $this->patchJson(cp_route('seo.redirects.update', $redirect), ['source' => '/old', 'target' => '/newer', 'status' => '301', 'active' => false])->assertOk();

    expect($redirect->fresh()->only(['target', 'active', 'automatic']))->toBe(['target' => '/newer', 'active' => false, 'automatic' => false]);
});

test('CSV export, and import that adds, updates and reports bad rows', function () {
    $this->actingAs(cpUser(['manage seo redirects']));
    Redirect::query()->create(['source' => '/one', 'target' => '/1']);
    Redirect::query()->create(['source' => '/gone', 'status' => 410, 'active' => false]);

    $export = $this->get(cp_route('seo.redirects.export'))->assertOk()->streamedContent();
    expect($export)->toBe("source,target,status,active\n/gone,,410,0\n/one,/1,301,1\n");

    $csv = "source,target,status,active\n/one,/uno,301,1\n/two,/2,302,\nbroken,/x,301,1\n/three,https://elsewhere.test/3,,yes\n";
    $result = $this->post(cp_route('seo.redirects.import'), ['file' => UploadedFile::fake()->createWithContent('r.csv', $csv)])->assertOk()->json();

    expect($result)->toMatchArray(['created' => 2, 'updated' => 1])
        ->and($result['errors'])->toHaveCount(1)
        ->and($result['errors'][0])->toStartWith('Line 4:')
        ->and(Redirect::query()->orderBy('source')->get(['source', 'target', 'status'])->toArray())->toBe([
            ['source' => '/gone', 'target' => null, 'status' => 410],
            ['source' => '/one', 'target' => '/uno', 'status' => 301],
            ['source' => '/three', 'target' => 'https://elsewhere.test/3', 'status' => 301],
            ['source' => '/two', 'target' => '/2', 'status' => 302],
        ]);
});

test('an import refuses a row that loops back through an earlier row of the same file, or through a wildcard', function () {
    $this->actingAs(cpUser(['manage seo redirects']));
    Redirect::query()->create(['source' => '/shop/*', 'target' => '/store/$1']);

    $csv = "/a,/b\n/b,/a\n/store/x,/shop/x\n";
    $result = $this->post(cp_route('seo.redirects.import'), ['file' => UploadedFile::fake()->createWithContent('r.csv', $csv)])->assertOk()->json();

    expect($result['created'])->toBe(1)
        ->and($result['errors'])->toBe([
            'Line 2: The redirect from that address leads back here, so the two would loop.',
            'Line 3: The redirect from that address leads back here, so the two would loop.',
        ]);
});

/**
 * Runway's Publish and Unpublish call `runwayResource()` on any Eloquent model
 * they are asked about, and Statamic's `Action::for()` asks every registered
 * action. With Runway installed, both listings answered 500.
 */
test('another addon\'s action that cannot handle our rows does not break the listings', function () {
    $throwsOnForeignModels = new class extends Action
    {
        public static function handle()
        {
            return 'throws_on_foreign_models';
        }

        public function visibleTo($item)
        {
            throw new BadMethodCallException('Call to undefined method runwayResource()');
        }
    };
    app()->instance($throwsOnForeignModels::class, $throwsOnForeignModels);
    app('statamic.actions')->put($throwsOnForeignModels::handle(), $throwsOnForeignModels::class);

    $this->actingAs(cpUser(['view seo', 'manage seo redirects']));
    $redirect = Redirect::query()->create(['source' => '/old', 'target' => '/new']);
    $row = MissingPath::query()->create(['path' => '/miss', 'hits' => 1, 'first_seen_at' => now(), 'last_seen_at' => now()]);

    expect($this->getJson(cp_route('seo.redirects.listing'))->assertOk()->json('data.0.actions.*.handle'))->toBe([DeleteSeoRecords::handle()])
        ->and($this->getJson(cp_route('seo.404s.listing'))->assertOk()->json('data.0.actions.*.handle'))->toBe([DeleteSeoRecords::handle(), CreateRedirect::handle()]);

    $this->postJson(cp_route('seo.actions.bulk'), ['selections' => [$redirect->id], 'context' => ['type' => 'redirects']])->assertOk();
    $this->postJson(cp_route('seo.actions.bulk'), ['selections' => [$row->id], 'context' => ['type' => '404s']])->assertOk();
});

test('the 404 log listing, newest first, with a "Create redirect" action per row', function () {
    $this->actingAs(cpUser(['view seo', 'manage seo redirects']));
    $old = MissingPath::query()->create(['path' => '/old-miss', 'hits' => 9, 'first_seen_at' => now()->subDay(), 'last_seen_at' => now()->subDay()]);
    MissingPath::query()->create(['path' => '/new-miss', 'hits' => 1, 'first_seen_at' => now(), 'last_seen_at' => now()]);

    $this->get(cp_route('seo.404s.index'))->assertInertia(fn (AssertableInertia $page) => $page->component('seo::NotFound', false));
    $listing = $this->getJson(cp_route('seo.404s.listing'));
    expect($listing->json('data.*.path'))->toBe(['/new-miss', '/old-miss'])
        ->and($listing->json('data.0.actions.*.handle'))->toContain(CreateRedirect::handle(), DeleteSeoRecords::handle());

    $actions = $this->postJson(cp_route('seo.actions.bulk'), ['selections' => [$old->id], 'context' => ['type' => '404s']])->assertOk()->json('*.handle');
    expect($actions)->toContain(CreateRedirect::handle(), DeleteSeoRecords::handle());

    $this->postJson(cp_route('seo.actions.run'), ['action' => CreateRedirect::handle(), 'selections' => [$old->id], 'context' => ['type' => '404s'], 'values' => []])
        ->assertOk()
        ->assertJson(['redirect' => cp_route('seo.redirects.create', ['source' => '/old-miss'])]);
});

test('deleting redirects and 404 rows through the listing action', function () {
    $this->actingAs(cpUser(['view seo', 'manage seo redirects']));
    $redirect = Redirect::query()->create(['source' => '/old', 'target' => '/new']);
    $row = MissingPath::query()->create(['path' => '/x', 'first_seen_at' => now(), 'last_seen_at' => now()]);

    $this->get('/old')->assertRedirect('https://example.test/new');

    $this->postJson(cp_route('seo.actions.run'), ['action' => DeleteSeoRecords::handle(), 'selections' => [$redirect->id], 'context' => ['type' => 'redirects'], 'values' => []])->assertOk();
    $this->postJson(cp_route('seo.actions.run'), ['action' => DeleteSeoRecords::handle(), 'selections' => [$row->id], 'context' => ['type' => '404s'], 'values' => []])->assertOk();

    expect(Redirect::query()->count())->toBe(0)->and(MissingPath::query()->count())->toBe(0);
    $this->get('/old')->assertNotFound();
});

test('redirects need "manage seo redirects", the 404 log "view seo"', function () {
    $redirect = Redirect::query()->create(['source' => '/old', 'target' => '/new']);

    $this->actingAs(cpUser(['view seo']));

    $this->get(cp_route('seo.redirects.index'))->assertForbidden();
    $this->getJson(cp_route('seo.redirects.listing'))->assertForbidden();
    $this->postJson(cp_route('seo.redirects.store'), ['source' => '/a', 'target' => '/b', 'status' => '301'])->assertForbidden();
    $this->get(cp_route('seo.redirects.export'))->assertForbidden();
    $this->postJson(cp_route('seo.actions.run'), ['action' => DeleteSeoRecords::handle(), 'selections' => [$redirect->id], 'context' => ['type' => 'redirects'], 'values' => []])->assertForbidden();
    $this->get(cp_route('seo.404s.index'))->assertOk();
});

test('the save dialog\'s check: does saving the form move the entry?', function () {
    Blueprint::make('page')->setNamespace('collections.pages')->setContents(['fields' => [['handle' => 'title', 'field' => ['type' => 'text']]]])->save();
    $this->actingAs(cpUser(super: true));
    $entry = entryIn('pages', 'about');

    $this->postJson(cp_route('seo.redirects.check'), ['reference' => $entry->reference(), 'values' => ['title' => 'About', 'slug' => 'about-us']])
        ->assertExactJson(['changes' => true, 'from' => '/about', 'to' => '/about-us']);

    $this->postJson(cp_route('seo.redirects.check'), ['reference' => $entry->reference(), 'values' => ['title' => 'New title', 'slug' => 'about']])
        ->assertExactJson(['changes' => false]);

    $this->postJson(cp_route('seo.redirects.check'), ['reference' => null, 'values' => ['slug' => 'new']])
        ->assertExactJson(['changes' => false]);

    // Unpublishing: a draft has no address to protect, so the save adds no redirect and nothing is asked.
    $this->postJson(cp_route('seo.redirects.check'), ['reference' => $entry->reference(), 'values' => ['title' => 'About', 'slug' => 'about-us', 'published' => false]])
        ->assertExactJson(['changes' => false]);

    expect(Entry::find($entry->id())->slug())->toBe('about');
});

test('the dialog only asks people who manage redirects; others get the redirect regardless', function () {
    Blueprint::make('page')->setNamespace('collections.pages')->setContents(['fields' => [['handle' => 'title', 'field' => ['type' => 'text']]]])->save();
    $entry = entryIn('pages', 'about');
    $this->actingAs(cpUser(['access cp', 'view pages entries', 'edit pages entries']));

    $this->postJson(cp_route('seo.redirects.check'), ['reference' => $entry->reference(), 'values' => ['slug' => 'about-us']])
        ->assertExactJson(['changes' => false]);
    $this->postJson(cp_route('seo.redirects.choice'), ['reference' => $entry->reference(), 'create' => false])->assertForbidden();
});

test('the dialog\'s answer is kept for the next save of that entry', function () {
    $this->actingAs(cpUser(super: true));
    $entry = entryIn('pages', 'about');

    $this->postJson(cp_route('seo.redirects.choice'), ['reference' => $entry->reference(), 'create' => false])->assertOk();

    Entry::find($entry->id())->syncOriginal()->slug('about-us')->save();

    expect(Redirect::query()->count())->toBe(0);
});

test('the dialog works for a term too: the check, and the answer read by the term\'s save', function () {
    Taxonomy::make('topics')->termTemplate('default')->save();
    Blueprint::make('topic')->setNamespace('taxonomies.topics')->setContents(['fields' => [['handle' => 'title', 'field' => ['type' => 'text']]]])->save();
    $term = tap(Term::make()->taxonomy('topics')->slug('gardens')->data(['title' => 'Gardens']))->save();
    $reference = $term->in('default')->reference();
    $this->actingAs(cpUser(super: true));

    $this->postJson(cp_route('seo.redirects.check'), ['reference' => $reference, 'values' => ['title' => 'Gardens', 'slug' => 'gardening']])
        ->assertExactJson(['changes' => true, 'from' => '/topics/gardens', 'to' => '/topics/gardening']);

    $this->postJson(cp_route('seo.redirects.choice'), ['reference' => $reference, 'create' => false])->assertOk();
    Term::find('topics::gardens')->term()->syncOriginal()->slug('gardening')->save();

    expect(Redirect::query()->count())->toBe(0);
});

test('the dialog does not ask about a term without a page of its own', function () {
    Taxonomy::make('topics')->save();
    Blueprint::make('topic')->setNamespace('taxonomies.topics')->setContents(['fields' => [['handle' => 'title', 'field' => ['type' => 'text']]]])->save();
    $term = tap(Term::make()->taxonomy('topics')->slug('gardens')->data(['title' => 'Gardens']))->save();
    $this->actingAs(cpUser(super: true));

    $this->postJson(cp_route('seo.redirects.check'), ['reference' => $term->in('default')->reference(), 'values' => ['title' => 'Gardens', 'slug' => 'gardening']])
        ->assertExactJson(['changes' => false]);
});
