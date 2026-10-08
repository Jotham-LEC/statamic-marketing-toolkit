<?php

namespace JothamLec\MarketingToolkit\Og;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JothamLec\MarketingToolkit\Context;
use JothamLec\MarketingToolkit\SiteSeo;
use JothamLec\MarketingToolkit\Support\Sites;
use Statamic\Contracts\Entries\Entry;

/**
 * Draws an entry's share card and caches the PNG until the entry, the card's
 * colours or the template's version change.
 *
 * The card shows the entry's SEO "Card title" and "Card subtitle" if they are set, or else its
 * title and the same description its meta tags carry. The template is the collection's
 * `og_template`, or else `default`.
 */
class Generator
{
    /**
     * Determines whether cards can be drawn here. the-og draws with Imagick alone (not GD), and
     * PHP's imagick extension is only suggested, so a host may lack it. Without it, the meta tags
     * fall back to the default image rather than point at a card that fails, and the card routes
     * answer 404.
     */
    public function available(): bool
    {
        return extension_loaded('imagick');
    }

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
     * Draws the card without the cache, for a Live Preview, whose unsaved values change with
     * each keystroke and must not stand in for the saved card.
     */
    public function draw(Entry $entry): string
    {
        return $this->template($entry)->image($this->card($entry))->toString();
    }

    /**
     * Works out what the card says with the entry's site as the current one (for its brand values
     * and colours), wherever it is asked for.
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
        // This is the mount page as it is on the entry's site, where it has its own title.
        $mount = $entry->collection()->mount();
        $mount = $mount?->in($entry->locale()) ?? $mount;

        return new Card(
            title: $overrides['og_title'] ?? (string) $context->value('title'),
            // A long subtitle may be typed on several lines, but the card draws it as one paragraph.
            description: isset($overrides['og_subtitle']) ? Str::squish((string) $overrides['og_subtitle']) : $seo->description($context),
            label: $mount ? (string) $mount->value('title') : $settings->siteName(),
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
        // breaking the image, and a missing default is a setup error.
        $class = config("marketing-toolkit.og.templates.{$key}") ?? config('marketing-toolkit.og.templates.default');

        if (! is_string($class) || ! is_subclass_of($class, Template::class)) {
            throw new InvalidArgumentException('No share-card template is registered as [default] in marketing-toolkit.og.templates.');
        }

        return app($class);
    }
}
