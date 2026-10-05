<?php

namespace JothamLec\Seo\Tags;

use JothamLec\Seo\Context;
use JothamLec\Seo\SiteSeo;
use Statamic\Tags\Tags;

/**
 * `<s:seo:meta />` in Blade, `{{ seo:meta }}` in Antlers: every tag the
 * <head> needs for the current page. It reads the entry or term from the
 * view's `page`; a page without one (a controller view, a 404) passes what it
 * knows as parameters: title, description, canonical (false for none),
 * image, og_type, noindex, status, or `entry` to name the content directly.
 */
class Seo extends Tags
{
    protected static $handle = 'seo';

    public function meta(): string
    {
        $meta = app(SiteSeo::class)->meta($this->context());

        return view('seo::meta', ['meta' => $meta])->render();
    }

    /**
     * `<s:seo:image_url />`: the share image's URL alone, for templates that
     * show it (a preview, a feed).
     */
    public function imageUrl(): ?string
    {
        return app(SiteSeo::class)->image($this->context())['url'] ?? null;
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
