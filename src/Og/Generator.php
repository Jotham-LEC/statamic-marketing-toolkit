<?php

namespace JothamLec\MarketingToolkit\Og;

use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use JothamLec\MarketingToolkit\Context;
use JothamLec\MarketingToolkit\SiteSeo;
use JothamLec\MarketingToolkit\Support\Sites;
use Statamic\Contracts\Entries\Entry;

/**
 * Draws an entry's share card and caches the PNG until the entry, the card's
 * colours or the template's version change.
 *
 * What the card says, in order of precedence: the entry's SEO "Card title" /
 * "Card subtitle", else its title and the same description its meta tags
 * carry. Which template: the collection's `og_template`, else `default`.
 */
class Generator
{
    public function png(Entry $entry): string
    {
        $card = $this->card($entry);
        $template = $this->template($entry);

        $key = implode(':', [
            'mt:og',
            $entry->id(),
            $entry->lastModified()->timestamp,
            $template::class,
            $template->version(),
            md5(serialize($card)),
        ]);

        return Cache::remember(
            $key,
            (int) config('marketing-toolkit.og.max_age'),
            fn () => $template->image($card)->toString(),
        );
    }

    /**
     * What the card says, worked out with the entry's site as the current one
     * (its brand values and colours), wherever it is asked for.
     */
    public function card(Entry $entry): Card
    {
        return Sites::as($entry->locale(), fn () => $this->cardInSite($entry));
    }

    private function cardInSite(Entry $entry): Card
    {
        $seo = app(SiteSeo::class);
        $context = Context::make($entry);
        $settings = $seo->settings();
        $overrides = $context->seo();
        // The mount page as it is on the entry's site, where it has its own title.
        $mount = $entry->collection()->mount();
        $mount = $mount?->in($entry->locale()) ?? $mount;

        return new Card(
            title: $overrides['og_title'] ?? (string) $entry->get('title'),
            description: $overrides['og_subtitle'] ?? $seo->description($context),
            label: $mount ? (string) $mount->get('title') : $settings->siteName(),
            siteName: $settings->siteName(),
            picture: $settings->asset('og_picture')?->resolvedPath(),
            background: $settings->string('og_background', '#ffffff'),
            text: $settings->string('og_text', '#111111'),
            accent: $settings->string('og_accent', '#2563eb'),
        );
    }

    public function template(Entry $entry): Template
    {
        $key = config("marketing-toolkit.collections.{$entry->collectionHandle()}.og_template") ?? 'default';

        // A key that names no template falls back to the default rather than
        // breaking the image; a missing default is a setup error.
        $class = config("marketing-toolkit.og.templates.{$key}") ?? config('marketing-toolkit.og.templates.default');

        if (! is_string($class) || ! is_subclass_of($class, Template::class)) {
            throw new InvalidArgumentException('No share-card template is registered as [default] in marketing-toolkit.og.templates.');
        }

        return app($class);
    }
}
