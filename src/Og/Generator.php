<?php

namespace JothamLec\Seo\Og;

use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use JothamLec\Seo\Context;
use JothamLec\Seo\SiteSeo;
use Statamic\Contracts\Entries\Entry;

/**
 * Draws an entry's share card and caches the PNG until the entry, the card's
 * colours or the template's version change.
 *
 * What the card says, in order of precedence: the entry's SEO "Card title" /
 * "Card subtitle", else its title and the same description its meta tags
 * carry. Which template: the entry's SEO "Card template", else the
 * collection's `og_template`, else `default`.
 */
class Generator
{
    public function png(Entry $entry): string
    {
        $card = $this->card($entry);
        $template = $this->template($entry);

        $key = implode(':', [
            'seo:og',
            $entry->id(),
            $entry->lastModified()->timestamp,
            $template::class,
            $template->version(),
            md5(serialize($card)),
        ]);

        return Cache::store(config('seo.og.cache_store'))->remember(
            $key,
            (int) config('seo.og.max_age'),
            fn () => $template->image($card)->toString(),
        );
    }

    public function card(Entry $entry): Card
    {
        $seo = app(SiteSeo::class);
        $context = Context::make($entry);
        $settings = $seo->settings();
        $overrides = $context->seo();
        $mount = $entry->collection()->mount();

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
        $key = config("seo.collections.{$entry->collectionHandle()}.og_template") ?? 'default';

        // A key that names no template falls back to the default rather than
        // breaking the image; a missing default is a setup error.
        $class = config("seo.og.templates.{$key}") ?? config('seo.og.templates.default');

        if (! is_string($class) || ! is_subclass_of($class, Template::class)) {
            throw new InvalidArgumentException('No share-card template is registered as [default] in seo.og.templates.');
        }

        return app($class);
    }
}
