<?php

namespace JothamLec\MarketingToolkit\Tags;

use JothamLec\MarketingToolkit\Context;
use JothamLec\MarketingToolkit\Favicons\Favicons;
use JothamLec\MarketingToolkit\SiteSeo;
use JothamLec\MarketingToolkit\Toolbar\Toolbar;
use JothamLec\MarketingToolkit\Tracking\Tracking;
use Statamic\Tags\Tags;

/**
 * `<s:mt:head />` in the <head>, after <meta charset>, and `<s:mt:body />` right after
 * <body> (`{{ mt:head }}`, `{{ mt:body }}` in Antlers): everything the
 * addon adds to a page. The head is the Consent Mode defaults and tracking
 * tags, which must come before anything else that loads Google's tags, then
 * the meta tags, then the icons' links; the body is the tags' <noscript>
 * fallbacks, then the front-end toolbar's guard, the same for every visitor.
 * `<s:mt:favicons />` is the icons' links alone, and `<s:mt:toolbar />` the
 * toolbar's guard alone, before </body>, for a layout without `mt:body`.
 *
 * `<s:mt:meta />` alone is the meta tags: every tag the
 * <head> needs for the current page's SEO. It reads the entry or term from the
 * view's `page`; a page without one (a controller view, a 404) passes what it
 * knows as parameters: title, description, canonical (false for none),
 * image, og_type, noindex, status, or `entry` to name the content directly.
 */
class Seo extends Tags
{
    protected static $handle = 'mt';

    public function head(): string
    {
        return app(Tracking::class)->head().$this->meta().$this->favicons();
    }

    /**
     * The icons' <link> tags and theme colour, when Brand has an icon.
     */
    public function favicons(): string
    {
        if (! config('marketing-toolkit.favicons.enabled')) {
            return '';
        }

        $favicons = app(Favicons::class);

        return view('marketing-toolkit::favicons', ['links' => $favicons->links(), 'themeColor' => $favicons->themeColor()])->render();
    }

    public function body(): string
    {
        return app(Tracking::class)->body().Toolbar::guard();
    }

    public function toolbar(): string
    {
        return Toolbar::guard();
    }

    public function meta(): string
    {
        $meta = app(SiteSeo::class)->meta($this->context());

        return view('marketing-toolkit::meta', ['meta' => $meta])->render();
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
