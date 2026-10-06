<?php

namespace JothamLec\MarketingToolkit\Tags;

use JothamLec\MarketingToolkit\Context;
use JothamLec\MarketingToolkit\SiteSeo;
use JothamLec\MarketingToolkit\Tracking\Tracking;
use Statamic\Tags\Tags;

/**
 * `<s:seo:head />` at the top of the <head> and `<s:seo:body />` right after
 * <body> (`{{ seo:head }}`, `{{ seo:body }}` in Antlers): everything the
 * addon adds to a page. The head is the Consent Mode defaults and tracking
 * tags, which must come before anything else that loads Google's tags, then
 * the meta tags; the body is the tags' <noscript> fallbacks.
 *
 * `<s:seo:meta />` alone is the meta tags: every tag the
 * <head> needs for the current page's SEO. It reads the entry or term from the
 * view's `page`; a page without one (a controller view, a 404) passes what it
 * knows as parameters: title, description, canonical (false for none),
 * image, og_type, noindex, status, or `entry` to name the content directly.
 */
class Seo extends Tags
{
    protected static $handle = 'seo';

    public function head(): string
    {
        return app(Tracking::class)->head().$this->meta();
    }

    public function body(): string
    {
        return app(Tracking::class)->body();
    }

    public function meta(): string
    {
        $meta = app(SiteSeo::class)->meta($this->context());

        return view('seo::meta', ['meta' => $meta])->render();
    }

    private function context(): Context
    {
        $content = $this->params->get('entry') ?? $this->context->get('page');

        return Context::make(
            content: $content,
            overrides: $this->params->only(['title', 'description', 'canonical', 'image', 'og_type', 'noindex'])->all(),
            status: (int) $this->params->get('status', 200),
        );
    }
}
