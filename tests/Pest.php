<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use JothamLec\Seo\Commands\Install;
use JothamLec\Seo\Context;
use JothamLec\Seo\Meta;
use JothamLec\Seo\SiteSeo;
use JothamLec\Seo\Tests\TestCase;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;

uses(TestCase::class)
    ->beforeEach(function () {
        $this->app['env'] = 'production';

        Site::setSites(['default' => ['name' => 'Default', 'url' => 'https://example.test/', 'locale' => 'en_US']]);
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
function metaFor(?EntryContract $entry, string $uri = '/', array $overrides = [], int $status = 200): Meta
{
    return app(SiteSeo::class)->meta(Context::make($entry, Request::create('https://example.test'.$uri), $overrides, $status));
}

/**
 * @param  array<string, mixed>  $values
 */
function seoGlobal(array $values): void
{
    Blueprint::make('seo')->setNamespace('globals')->setContents(['tabs' => Install::tabs('assets')])->save();

    $set = GlobalSet::make('seo')->title('SEO & brand');
    $set->save();
    $set->in('default')->data($values)->save();
}

/**
 * Renders Blade as if for a request to $uri: what a layout's <s:seo:meta /> prints.
 *
 * @param  array<string, mixed>  $data
 */
function renderAt(string $uri, string $blade, array $data = []): string
{
    app()->instance('request', Request::create('https://example.test'.$uri));

    return Blade::render($blade, $data);
}
