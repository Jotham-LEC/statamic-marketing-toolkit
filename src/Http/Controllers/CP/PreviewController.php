<?php

namespace JothamLec\Seo\Http\Controllers\CP;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JothamLec\Seo\Context;
use JothamLec\Seo\Og\Generator;
use JothamLec\Seo\Preview\Draft;
use JothamLec\Seo\SiteSeo;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Facades\Site;

/**
 * Feeds the `seo_preview` fieldtype. Both actions read the publish form's
 * current values, so the preview follows the editor's typing before the
 * entry is saved, and run them through the same SiteSeo rules the page uses.
 */
class PreviewController
{
    public function meta(Request $request, SiteSeo $seo): JsonResponse
    {
        $content = Draft::fromRequest($request);
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
                // would show the saved entry's card instead.
                'generated' => $generated !== null && $meta->image['url'] === $generated,
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

        abort_unless($content instanceof Entry && config('seo.og.enabled'), 404);

        $png = $generator->template($content)->image($generator->card($content))->toString();

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
        $url = $content->absoluteUrl() ?? rtrim(Site::current()->absoluteUrl(), '/').'/'.$content->slug();

        return Context::make($content, Request::create($url));
    }
}
