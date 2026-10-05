<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use JothamLec\Seo\Fieldtypes\SeoPreview;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Role;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Facades\User;
use Symfony\Component\Yaml\Yaml;

beforeEach(function () {
    seoGlobal(['site_name' => 'Acme']);

    foreach (['collections.pages' => 'page', 'taxonomies.topics' => 'topic'] as $namespace => $handle) {
        Blueprint::make($handle)->setNamespace($namespace)->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'description', 'field' => ['type' => 'textarea']],
            ['import' => 'seo::seo'],
        ]]]]]])->save();
    }

    Taxonomy::make('topics')->save();
});

function superUser(): Statamic\Contracts\Auth\User
{
    return tap(User::make()->email('super@example.test')->makeSuper())->save();
}

/**
 * @param  list<string>  $permissions
 */
function userWith(array $permissions): Statamic\Contracts\Auth\User
{
    Role::make('editor')->permissions(['access cp', ...$permissions])->save();

    return tap(User::make()->email('editor@example.test')->assignRole('editor'))->save();
}

/**
 * @param  array<string, mixed>  $values
 */
function previewOf(string $blueprint, array $values, ?string $reference = null, string $route = 'seo.preview.meta'): TestResponse
{
    // The suite runs as production, so Laravel's CSRF check is live; the CP sends the token.
    return test()->withoutMiddleware([PreventRequestForgery::class, VerifyCsrfToken::class])->postJson(cp_route($route), [
        'blueprint' => $blueprint,
        'reference' => $reference,
        'site' => 'default',
        'values' => $values,
    ]);
}

test('the fieldtype is in the SEO fieldset, hands the form its routes and limits, and stores nothing', function () {
    $fieldtype = new SeoPreview;
    $fields = Blueprint::find('collections.pages.page')->fields();

    expect($fields->has('seo_preview'))->toBeTrue()
        ->and($fieldtype->preload())->toMatchArray([
            'urls' => ['meta' => cp_route('seo.preview.meta'), 'card' => cp_route('seo.preview.card')],
            'limits' => ['title' => [30, 60], 'description' => [50, 155]],
            'og' => true,
        ])
        ->and($fields->addValues(['title' => 'About', 'seo_preview' => 'anything'])->process()->values()['seo_preview'])->toBeNull();
});

test('an entry being edited is previewed from the form, not from what was saved', function () {
    $this->actingAs(superUser());
    $entry = entryIn('pages', 'about', ['description' => 'Saved description.']);

    previewOf('collections.pages.page', ['title' => 'About us', 'description' => 'Who we are, typed just now.', 'slug' => 'about'], $entry->reference())
        ->assertOk()
        ->assertJson([
            'title' => 'About us · Acme',
            'og_title' => 'About us',
            'description' => 'Who we are, typed just now.',
            'url' => 'https://example.test/about',
            'canonical' => 'https://example.test/about',
            'site_name' => 'Acme',
            'image' => ['generated' => true],
        ]);

    expect($entry->fresh()->get('title'))->toBe('About');
});

test('the preview follows the same rules as the page: a typed SEO title as is, a long title without the site name', function () {
    $this->actingAs(superUser());

    previewOf('collections.pages.page', ['title' => 'About', 'seo' => ['title' => 'Exactly this <title>']])
        ->assertJson(['title' => 'Exactly this <title>', 'og_title' => 'Exactly this <title>']);

    $long = 'A title long enough that adding the site name would push it past sixty';

    previewOf('collections.pages.page', ['title' => $long])->assertJson(['title' => $long]);
});

test('a new entry is previewed at the address it will have', function () {
    $this->actingAs(superUser());

    previewOf('collections.pages.page', ['title' => 'Brand new', 'slug' => 'brand-new'])
        ->assertOk()
        ->assertJson(['title' => 'Brand new · Acme', 'url' => 'https://example.test/brand-new', 'image' => ['generated' => true]]);
});

test('a noindexed entry says so, and an uploaded image replaces the card', function () {
    $this->actingAs(superUser());
    AssetContainer::find('assets')->disk()->put('share.png', file_get_contents(__DIR__.'/../fixtures/share.png'));

    $response = previewOf('collections.pages.page', ['title' => 'Hidden', 'slug' => 'hidden', 'seo' => ['noindex' => true, 'image' => ['assets::share.png']]]);

    expect($response->json('robots'))->toStartWith('noindex')
        ->and($response->json('image.generated'))->toBeFalse()
        ->and($response->json('image.url'))->toStartWith('https://example.test/img/');
});

test('a term is previewed too', function () {
    $this->actingAs(superUser());
    $term = tap(Term::make()->taxonomy('topics')->slug('gardens')->data(['title' => 'Gardens']))->save();

    previewOf('taxonomies.topics.topic', ['title' => 'Gardens and parks', 'slug' => 'gardens'], $term->in('default')->reference())
        ->assertOk()
        ->assertJson(['title' => 'Gardens and parks · Acme', 'url' => 'https://example.test/topics/gardens', 'image' => null]);

    previewOf('taxonomies.topics.topic', ['title' => 'Ponds', 'slug' => 'ponds'])
        ->assertJson(['title' => 'Ponds · Acme', 'url' => 'https://example.test/topics/ponds']);
});

test('the card is drawn from the form as a PNG and never cached', function () {
    $this->actingAs(superUser());
    $entry = entryIn('pages', 'about');

    $response = previewOf('collections.pages.page', ['title' => 'About', 'slug' => 'about', 'seo' => ['og_title' => 'Typed card title']], $entry->reference(), 'seo.preview.card')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    $cached = (fn () => $this->storage)->call(Cache::store()->getStore());

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and(getimagesizefromstring($response->getContent())[0])->toBe(1200)
        ->and(collect($cached)->keys()->filter(fn ($key) => str_contains($key, 'seo:og')))->toBeEmpty();
});

test('the card route has nothing to draw for a term or with cards off', function () {
    $this->actingAs(superUser());

    previewOf('taxonomies.topics.topic', ['title' => 'Ponds', 'slug' => 'ponds'], route: 'seo.preview.card')->assertNotFound();

    config(['seo.og.enabled' => false]);

    previewOf('collections.pages.page', ['title' => 'About'], route: 'seo.preview.card')->assertNotFound();
});

test('only people who may see the content get its preview', function () {
    $entry = entryIn('pages', 'about');

    previewOf('collections.pages.page', ['title' => 'About'], $entry->reference())->assertUnauthorized();

    $this->actingAs(userWith([]));
    previewOf('collections.pages.page', ['title' => 'About'], $entry->reference())->assertForbidden();
    previewOf('collections.pages.page', ['title' => 'New'])->assertForbidden();
});

test('an editor of the collection gets the preview without any SEO permission', function () {
    $this->actingAs(userWith(['view pages entries', 'create pages entries']));

    previewOf('collections.pages.page', ['title' => 'About'], entryIn('pages', 'about')->reference())->assertOk();
    previewOf('collections.pages.page', ['title' => 'New'])->assertOk();
});

test('a blueprint that is not an entry or term has no preview', function () {
    $this->actingAs(superUser());

    previewOf('globals.seo', [])->assertStatus(422);
    previewOf('collections.pages.nope', [])->assertStatus(422);
});

test('the fieldset\'s help text has no raw HTML, which the CP would render as tags', function () {
    $instructions = [];
    $fieldset = Yaml::parseFile(__DIR__.'/../../resources/fieldsets/seo.yaml');

    array_walk_recursive(
        $fieldset,
        function ($value, $key) use (&$instructions) {
            if ($key === 'instructions') {
                $instructions[] = preg_replace('/`[^`]*`/', '', $value);
            }
        },
    );

    expect($instructions)->not->toBeEmpty()
        ->each->not->toMatch('/<[a-z!\/]/i');
});
