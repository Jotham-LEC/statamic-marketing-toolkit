<?php

namespace JothamLec\MarketingToolkit\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JothamLec\MarketingToolkit\Og\Generator;
use JothamLec\MarketingToolkit\SiteSeo;
use JothamLec\MarketingToolkit\Support\Features;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Exceptions\NotFoundHttpException;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Structures\Page;

/**
 * /og.png (home) and /og/{path}.png: the generated share card of the
 * published entry at that path on the domain. The meta tags only point here when the entry has no uploaded share
 * image, but the card is served either way so editors can preview it.
 */
final class OgImageController
{
    public function __invoke(Request $request, Generator $generator, SiteSeo $seo, ?string $path = null): Response
    {
        throw_unless(Features::on('share_cards') && $generator->available(), NotFoundHttpException::class);

        $entry = $this->entry($request, trim((string) $path, '/'));
        // A protected page's card would show its title and text to anyone.
        throw_unless($entry?->status() === 'published' && ! $seo->isProtected($entry), NotFoundHttpException::class);

        return new Response($generator->png($entry), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age='.(int) config('marketing-toolkit.og.max_age'),
        ]);
    }

    /**
     * The entry whose page is at $path on this domain: /og/fr/a-propos.png is
     * the card of /fr/a-propos, on the site under /fr/. A domain no site is
     * on (a local copy) is read as the current site's.
     */
    private function entry(Request $request, string $path): ?EntryContract
    {
        $url = $request->getSchemeAndHttpHost().'/'.$path;
        $site = Site::findByUrl($url);
        $uri = $site ? $site->relativePath($url) : '/'.$path;

        $entry = Entry::findByUri('/'.trim($uri, '/'), ($site ?? Site::current())->handle());

        return $entry instanceof Page ? $entry->entry() : $entry;
    }
}
