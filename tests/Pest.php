<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use JothamLec\MarketingToolkit\Commands\Install;
use JothamLec\MarketingToolkit\Context;
use JothamLec\MarketingToolkit\Cp\Navigation;
use JothamLec\MarketingToolkit\Meta;
use JothamLec\MarketingToolkit\Reports\ExternalLinkChecker;
use JothamLec\MarketingToolkit\SiteSeo;
use JothamLec\MarketingToolkit\Tests\TestCase;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\CP\Navigation\NavItem;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Role;
use Statamic\Facades\Site;
use Statamic\Facades\URL;
use Statamic\Facades\User;

uses(TestCase::class)
    ->beforeEach(function () {
        $this->app['env'] = 'production';
        // Statamic keeps this in a static flag; a test that turns it on must not leak.
        URL::enforceTrailingSlashes(false);
        // Nothing leaves the test run: IndexNow is faked where it is tested.
        Http::preventStrayRequests();
        // Production env makes Laravel's CSRF check live; the CP sends the token.
        // PreventRequestForgery is Laravel 13's; ValidateCsrfToken is Laravel 12's.
        $this->withoutMiddleware([PreventRequestForgery::class, ValidateCsrfToken::class, VerifyCsrfToken::class]);

        Site::setSites(['default' => ['name' => 'Acme', 'url' => 'https://example.test/', 'locale' => 'en_US']]);
        AssetContainer::make('assets')->disk('assets')->save();
        Collection::make('home')->routes('/')->save();
        Collection::make('pages')->routes('{slug}')->save();
        Collection::make('essays')->routes('essays/{slug}')->dated(true)->save();
    })
    ->in('Feature');

/**
 * @param  array<string, mixed>  $data
 */
function entryIn(string $collection, string $slug, array $data = [], ?string $date = null): EntryContract
{
    $entry = Entry::make()->collection($collection)->slug($slug)->data(['title' => ucfirst(str_replace('-', ' ', $slug)), ...$data]);

    if ($date) {
        $entry->date($date);
    }

    $entry->save();

    return $entry;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function metaFor(EntryContract|Term|null $entry, string $uri = '/', array $overrides = [], int $status = 200): Meta
{
    // Bound as the app's request too: Statamic works out the current site from it.
    app()->instance('request', $request = Request::create(absoluteTestUrl($uri)));

    return app(SiteSeo::class)->meta(Context::make($entry, $request, $overrides, $status));
}

/**
 * A path on the default site, or a full address (another site's) as it is.
 */
function absoluteTestUrl(string $uri): string
{
    return preg_match('#^https?://#', $uri) ? $uri : 'https://example.test'.$uri;
}

/**
 * The brand global with these values on one site. On a multi-site install
 * the set is on every site, and each other site's origin is the default.
 *
 * @param  array<string, mixed>  $values
 */
function seoGlobal(array $values, string $site = 'default'): void
{
    Blueprint::make('seo')->setNamespace('globals')->setContents(['tabs' => Install::tabs('assets')])->save();

    $set = GlobalSet::findByHandle('seo') ?? GlobalSet::make('seo')->title('SEO & brand');

    if (Site::multiEnabled()) {
        $set->sites(Site::all()->mapWithKeys(fn ($each) => [$each->handle() => $each->handle() === 'default' ? null : 'default'])->all());
    }

    $set->save();
    $set->in($site)->data($values)->save();
}

/**
 * Two sites on two domains, as Statamic Pro runs them: `default` on
 * example.test and `cothinking` on cothink.test. Home and pages are on both.
 */
function multisite(): void
{
    config(['statamic.editions.pro' => true, 'statamic.system.multisite' => true]);

    Site::setSites([
        'default' => ['name' => 'Acme', 'url' => 'https://example.test/', 'locale' => 'en_US'],
        'cothinking' => ['name' => 'CoThinking', 'url' => 'https://cothink.test/', 'locale' => 'en_GB'],
    ]);

    Collection::findByHandle('home')->sites(['default', 'cothinking'])->save();
    Collection::findByHandle('pages')->sites(['default', 'cothinking'])->save();
}

/**
 * One site in four languages: English (US) on example.test, French under
 * /fr/, British English under /uk/, German on its own domain. Home and pages
 * are on every site; a localization names its origin.
 */
function multilang(): void
{
    config(['statamic.editions.pro' => true, 'statamic.system.multisite' => true]);

    Site::setSites([
        'default' => ['name' => 'Acme', 'url' => 'https://example.test/', 'locale' => 'en_US'],
        'fr' => ['name' => 'Acme', 'url' => 'https://example.test/fr/', 'locale' => 'fr_FR'],
        'uk' => ['name' => 'Acme', 'url' => 'https://example.test/uk/', 'locale' => 'en_GB'],
        'de' => ['name' => 'Acme', 'url' => 'https://de.example.test/', 'locale' => 'de_DE'],
    ]);

    Collection::findByHandle('home')->sites(['default', 'fr', 'uk', 'de'])->save();
    Collection::findByHandle('pages')->sites(['default', 'fr', 'uk', 'de'])->save();
}

/**
 * $origin in another language: an entry on $site that names it as its origin.
 *
 * @param  array<string, mixed>  $data
 */
function translationOf(EntryContract $origin, string $site, string $slug, array $data = []): EntryContract
{
    $entry = Entry::make()->collection($origin->collectionHandle())->locale($site)->origin($origin)->slug($slug)
        ->data(['title' => ucfirst(str_replace('-', ' ', $slug)), ...$data]);
    $entry->save();

    return $entry;
}

/**
 * An entry on another site than the default.
 *
 * @param  array<string, mixed>  $data
 */
function entryOn(string $site, string $collection, string $slug, array $data = []): EntryContract
{
    $entry = Entry::make()->collection($collection)->locale($site)->slug($slug)->data(['title' => ucfirst(str_replace('-', ' ', $slug)), ...$data]);
    $entry->save();

    return $entry;
}

/**
 * Renders Blade as if for a request to $uri (a path on the default site, or
 * a full address): what a layout's <s:mt:meta /> prints.
 *
 * @param  array<string, mixed>  $data
 */
function renderAt(string $uri, string $blade, array $data = []): string
{
    app()->instance('request', Request::create(absoluteTestUrl($uri)));

    return Blade::render($blade, $data);
}

/**
 * A control panel user: a super user, or one whose role has these permissions.
 *
 * @param  list<string>  $permissions
 */
function cpUser(array $permissions = [], bool $super = false): Statamic\Contracts\Auth\User
{
    $user = User::make()->email(($super ? 'super' : 'editor').'@example.test');

    if ($super) {
        $user->makeSuper();
    } else {
        Role::make('seo-role')->permissions(['access cp', ...$permissions])->save();
        $user->assignRole('seo-role');
    }

    return tap($user)->save();
}

/**
 * @return array<string, mixed>|null
 */
function nodeOf(Meta $meta, string $type): ?array
{
    return collect($meta->graph)->first(fn (array $node) => in_array($type, (array) $node['@type'], true));
}

/**
 * The publisher node of a page, once the brand global holds $values.
 *
 * @param  array<string, mixed>  $values
 * @return array<string, mixed>
 */
function publisherOf(array $values): array
{
    seoGlobal($values);

    return collect(metaFor(entryIn('pages', 'about'))->graph)->firstWhere('@id', 'https://example.test/#publisher');
}

/**
 * Host names the external link checker resolves, to these addresses; any
 * other doesn't resolve. Nothing leaves the test run for DNS either.
 *
 * @param  array<string, string|list<string>>  $hosts
 */
function fakeDns(array $hosts): void
{
    app()->instance(ExternalLinkChecker::class, new class($hosts) extends ExternalLinkChecker
    {
        /** @param  array<string, string|list<string>>  $hosts */
        public function __construct(private array $hosts) {}

        protected function resolve(string $host): array
        {
            return array_values((array) ($this->hosts[$host] ?? []));
        }
    });
}

/**
 * The CP navigation's items under Tools, keyed by name, as the user sees them.
 *
 * @return Illuminate\Support\Collection<string, NavItem>
 */
function toolsNav(): Illuminate\Support\Collection
{
    // AddonTestCase mocks the nav after the addon has extended the real one.
    Nav::swap(new Statamic\CP\Navigation\Nav);
    Navigation::register();

    $tools = collect(Nav::build())->firstWhere('display', 'Tools');

    return collect($tools['items'] ?? [])->keyBy(fn ($item) => $item->display());
}
