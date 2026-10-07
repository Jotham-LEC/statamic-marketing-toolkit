<?php

namespace JothamLec\MarketingToolkit\Preview;

use Illuminate\Http\Request;
use JothamLec\MarketingToolkit\Context;
use JothamLec\MarketingToolkit\SiteSeo;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Facades\Site;

/**
 * What a search result and a share card show for an entry or a term, worked
 * out by the same SiteSeo rules the page uses: for the SEO tab's preview
 * (the form as it stands) and the front-end toolbar (the page as saved).
 * Run it on the content's site (Sites::as()).
 */
class MetaPayload
{
    public function __construct(private SiteSeo $seo) {}

    /**
     * @return array{title: string, og_title: string, description: ?string, url: string, canonical: ?string, robots: string, site_name: string, twitter_site: ?string, image: ?array{url: string, alt: ?string, generated: bool}}
     */
    public function forContent(Entry|Term $content): array
    {
        $meta = $this->seo->meta(self::context($content));
        $generated = $content instanceof Entry ? $this->seo->generatedImageUrl($content) : null;

        return [
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
        ];
    }

    /**
     * The rules fall back to the request's URL when the content has none; for
     * a draft that would be the control panel's route, so give them the page's
     * own address instead (or where a new page would land).
     */
    public static function context(Entry|Term $content): Context
    {
        $url = $content->absoluteUrl() ?? rtrim(Site::get($content->locale())?->absoluteUrl() ?? Site::current()->absoluteUrl(), '/').'/'.$content->slug();

        return Context::make($content, Request::create($url));
    }
}
