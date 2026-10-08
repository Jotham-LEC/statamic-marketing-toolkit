<?php

namespace JothamLec\MarketingToolkit\Http\Controllers;

use Facades\Statamic\CP\LivePreview;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JothamLec\MarketingToolkit\Og\Generator;
use JothamLec\MarketingToolkit\SiteSeo;
use JothamLec\MarketingToolkit\Support\Features;
use JothamLec\MarketingToolkit\Support\Fields;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Exceptions\NotFoundHttpException;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Structures\Page;

/**
 * Serves /og.png (home) and /og/{path}.png, the generated share card of the
 * published entry at that path on the domain. The meta tags only point here when the entry has no uploaded share
 * image, but the card is served either way so that editors can preview it.
 *
 * In a Live Preview the page's card address carries the preview's `token`. When that token
 * previews the entry at the path, the card is drawn from the form's unsaved values, a draft
 * included, and neither cached nor kept by the browser. Any other token is ignored.
 */
final class OgImageController
{
    public function __invoke(Request $request, Generator $generator, SiteSeo $seo, ?string $path = null): Response
    {
        throw_unless(Features::on('share_cards') && $generator->available(), NotFoundHttpException::class);

        $entry = $this->entry($request, trim((string) $path, '/'));
        $previewed = $entry ? $this->previewed($request, $entry) : null;
        // A protected page is refused, because its card would show its title and text to anyone.
        throw_unless($entry && ($previewed || $entry->status() === 'published') && ! $seo->isProtected($entry), NotFoundHttpException::class);

        if ($previewed) {
            return new Response($generator->draw($previewed), 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'no-store']);
        }

        return new Response($generator->png($entry), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age='.(int) config('marketing-toolkit.og.max_age'),
        ]);
    }

    /**
     * Returns the entry as a Live Preview holds it, when the request's token previews this
     * entry. The card routes are outside Statamic's web middleware, which would otherwise put
     * the previewed entry in place.
     */
    private function previewed(Request $request, EntryContract $entry): ?EntryContract
    {
        if (! Fields::isPreviewed($entry)) {
            return null;
        }

        $item = LivePreview::item($request->statamicToken());

        return $item instanceof EntryContract ? $item : null;
    }

    /**
     * Finds the entry whose page is at $path on this domain. For example,
     * /og/fr/a-propos.png is the card of /fr/a-propos, on the site under /fr/. A
     * domain that no site is on (a local copy) is read as the current site's.
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
