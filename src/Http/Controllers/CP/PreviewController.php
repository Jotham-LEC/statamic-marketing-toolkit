<?php

namespace JothamLec\MarketingToolkit\Http\Controllers\CP;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JothamLec\MarketingToolkit\Context;
use JothamLec\MarketingToolkit\Og\Generator;
use JothamLec\MarketingToolkit\Preview\Draft;
use JothamLec\MarketingToolkit\SiteSeo;
use JothamLec\MarketingToolkit\Support\Sites;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Facades\Site;

/**
 * Feeds the `mt_preview` fieldtype. Both actions read the publish form's
 * current values, so the preview follows the editor's typing before the
 * entry is saved, and run them through the same SiteSeo rules the page uses.
 */
class PreviewController
{
    public function meta(Request $request, SiteSeo $seo): JsonResponse
    {
        $content = Draft::fromRequest($request);

        // Worked out on the content's site: its brand values, its locale, its address.
        return Sites::as($content->locale(), fn () => $this->metaOf($content, $seo));
    }

    private function metaOf(Entry|Term $content, SiteSeo $seo): JsonResponse
    {
        $context = $this->context($content);
        $meta = $seo->meta($context);
        $generated = $content instanceof Entry ? $seo->generatedImageUrl($content) : null;

        return response()->json([
            'title' => $meta->title,
            'og_title' => $meta->ogTitle,
            'description' => $meta->description,
            'url' => $meta->url,
            'canonical' => $meta->canonical,
            'robots' => $meta->robots,
            'site_name' => $meta->siteName,
            'twitter_site' => $meta->twitterSite,
            'image' => $meta->image === null ? null : [
                'url' => $meta->image['url'],
                'alt' => $meta->image['alt'],
                // A generated card is drawn from the form by card(); its public URL
                // would show the saved entry's card instead. Compared without the
                // ?v= stamp: an unsaved entry's is "now", read twice.
                'generated' => $generated !== null && strtok($meta->image['url'], '?') === strtok($generated, '?'),
            ],
        ]);
    }

    /**
     * The generated card for the form as it stands. Drawn every time and
     * never cached: each draft differs, and only saved entries go public.
     */
    public function card(Request $request, Generator $generator): Response
    {
        $content = Draft::fromRequest($request);

        abort_unless($content instanceof Entry && config('marketing-toolkit.og.enabled'), 404);

        $png = Sites::as($content->locale(), fn () => $generator->template($content)->image($generator->card($content))->toString());

        return new Response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * The rules fall back to the request's URL when the content has none; for
     * a draft that would be this CP route, so give them the page's own
     * address instead (or where a new page would land).
     */
    private function context(Entry|Term $content): Context
    {
        $url = $content->absoluteUrl() ?? rtrim(Site::get($content->locale())?->absoluteUrl() ?? Site::current()->absoluteUrl(), '/').'/'.$content->slug();

        return Context::make($content, Request::create($url));
    }
}
