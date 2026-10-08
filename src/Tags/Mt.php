<?php

namespace JothamLec\MarketingToolkit\Tags;

use JothamLec\MarketingToolkit\Context;
use JothamLec\MarketingToolkit\Favicons\Favicons;
use JothamLec\MarketingToolkit\SiteSeo;
use JothamLec\MarketingToolkit\Support\Features;
use JothamLec\MarketingToolkit\Toolbar\Toolbar;
use JothamLec\MarketingToolkit\Tracking\Tracking;
use Statamic\Tags\Tags;

/**
 * This tag prints everything the addon adds to a page. Place `<s:mt:head />` in the <head>, after
 * <meta charset>, and `<s:mt:body />` right after <body> (`{{ mt:head }}` and
 * `{{ mt:body }}` in Antlers). The head prints the Consent Mode defaults and
 * tracking tags, which must come before anything else that loads Google's
 * tags, then the meta tags, and then the icons' links. The body prints the
 * tags' <noscript> fallbacks and then the front-end toolbar's guard, which is
 * the same for every visitor. `<s:mt:favicons />` prints the icons' links
 * alone, and `<s:mt:toolbar />` prints the toolbar's guard alone, before
 * </body>, for a layout without `mt:body`.
 *
 * `<s:mt:meta />` prints the meta tags alone. The parameters are in docs/developers.md ("The tag").
 */
final class Mt extends Tags
{
    /** @var string */
    protected static $handle = 'mt';

    public function head(): string
    {
        return app(Tracking::class)->head().$this->meta().$this->favicons();
    }

    /**
     * Prints the icons' <link> tags and theme colour when Brand has an icon.
     */
    public function favicons(): string
    {
        if (! Features::on('favicons')) {
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
