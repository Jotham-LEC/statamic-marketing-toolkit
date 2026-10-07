<?php

namespace JothamLec\MarketingToolkit\Concerns;

use ArrayAccess;
use Illuminate\Support\Collection;
use JothamLec\MarketingToolkit\Context;
use JothamLec\MarketingToolkit\Settings;
use JothamLec\MarketingToolkit\SiteSeo;
use JothamLec\MarketingToolkit\Support\Assets;
use JothamLec\MarketingToolkit\Support\Text;
use Statamic\Contracts\Assets\Asset;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Facades\Image;
use Statamic\Facades\Markdown;
use Statamic\Facades\Site;
use Statamic\Fields\Value;

/**
 * The helpers the rules share: settings, collection rules, reading fields
 * and images, and the site's address. Part of SiteSeo's override surface: a
 * project overrides these methods on its SiteSeo subclass (bound in its place), not on the trait, which only splits the class into readable
 * parts.
 *
 * @phpstan-require-extends SiteSeo
 */
trait InteractsWithContent
{
    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /** @api */
    public function settings(): Settings
    {
        return $this->settings;
    }

    /**
     * A key of this page's rules: its collection's (config `marketing-toolkit.collections`),
     * or for a term, its taxonomy's (`marketing-toolkit.taxonomies`).
     *
     * @api
     */
    public function contentConfig(Context $context, string $key, mixed $default = null): mixed
    {
        if ($context->entry) {
            return $this->collectionConfig($context, $key, $default);
        }

        $handle = $context->term?->taxonomyHandle();

        return $handle ? config("marketing-toolkit.taxonomies.{$handle}.{$key}", $default) : $default;
    }

    /**
     * A key of this entry's collection settings (config `marketing-toolkit.collections`).
     *
     * @api
     */
    public function collectionConfig(Context $context, string $key, mixed $default = null): mixed
    {
        $handle = $context->entry?->collectionHandle();

        return $handle ? config("marketing-toolkit.collections.{$handle}.{$key}", $default) : $default;
    }

    protected function contentTitle(Context $context): ?string
    {
        $content = $context->content();
        $title = $content instanceof Term ? $content->title() : $content?->get('title');

        return filled($title) ? (string) $title : null;
    }

    /**
     * The entry's `description`, a field the collection names, else the first
     * paragraph of its `content`.
     */
    protected function contentDescription(Context $context): ?string
    {
        $content = $context->content();

        if (! $content) {
            return null;
        }

        foreach (['description', ...$this->contentConfig($context, 'description_fields', [])] as $field) {
            if ($value = $this->rawValues($content, $field)->first(fn ($value) => filled($value) && is_string($value))) {
                return $value;
            }
        }

        $html = $this->html($content->augmentedValue('content'));

        if (blank($html)) {
            return null;
        }

        // A body that is not rendered HTML (no blueprint field, a plain textarea) is read as Markdown.
        if (! str_contains($html, '<p')) {
            $html = Markdown::parse($html);
        }

        return Text::firstParagraph($html);
    }

    /**
     * HTML from an augmented Markdown or Bard value.
     */
    protected function html(mixed $value): ?string
    {
        $value = $this->unwrap($value);

        if (is_string($value)) {
            return $value;
        }

        if (is_iterable($value)) {
            return collect($value)
                ->map(fn ($set) => ($set['type'] ?? null) === 'text' ? (string) ($set['text'] ?? '') : '')
                ->implode('');
        }

        return null;
    }

    protected function assetFrom(Entry|Term $content, string $field): ?Asset
    {
        if ($set = $this->setPath($field)) {
            return $this->visibleSets($content->augmentedValue($set['field'])->value(), $set['type'])
                ->map(fn ($values) => Assets::from($values[$set['key']] ?? null))
                ->first(fn ($asset) => $asset !== null);
        }

        return Assets::from($field === 'seo'
            ? ($content->augmentedValue('seo')->value()['image'] ?? null)
            : $content->augmentedValue($field)->value());
    }

    /**
     * The stored value of a field, as a list: one value for a plain field, one
     * per visible matching set for a path into a Replicator.
     *
     * @return Collection<int, mixed>
     */
    protected function rawValues(Entry|Term $content, string $field): Collection
    {
        if ($set = $this->setPath($field)) {
            return $this->visibleSets($content->get($set['field']), $set['type'])->map(fn ($values) => $values[$set['key']] ?? null)->values();
        }

        return collect([$content->get($field)]);
    }

    /**
     * `sections.hero.image` → the Replicator field, the set type (`*` for any)
     * and the field in the set. Statamic handles have no dots, so a plain name
     * is never a path.
     *
     * @return array{field: string, type: string, key: string}|null
     */
    protected function setPath(string $field): ?array
    {
        $parts = explode('.', $field);

        return count($parts) === 3 && ! in_array('', $parts, true) ? array_combine(['field', 'type', 'key'], $parts) : null;
    }

    /**
     * A Replicator's sets of a type, in order, without those switched off.
     * Takes the stored rows or the augmented ones (which have none switched off).
     *
     * @return Collection<int, mixed>
     */
    protected function visibleSets(mixed $sets, string $type): Collection
    {
        $sets = $this->unwrap($sets);

        return collect(is_iterable($sets) ? $sets : [])
            ->filter(fn ($set) => (is_array($set) || $set instanceof ArrayAccess)
                && ($set['enabled'] ?? true) !== false
                && ($type === '*' || ($set['type'] ?? null) === $type))
            ->values();
    }

    /**
     * A field's value without its augmented Value wrapper, whichever of the two
     * a caller (or a subclass) passes.
     */
    private function unwrap(mixed $value): mixed
    {
        return $value instanceof Value ? $value->value() : $value;
    }

    /**
     * An uploaded image as a share image: cropped on its focal point and
     * served as JPEG (WhatsApp and others do not preview WebP).
     *
     * @return array{url: string, width: int, height: int, alt: ?string}
     */
    protected function cropped(Asset $asset, ?int $width = null, ?int $height = null): array
    {
        $width ??= $this->imageWidth();
        $height ??= $this->imageHeight();

        // Fluently, not as an array: only fit() turns `crop_focal` into Glide's
        // `crop-{x}-{y}`. Glide takes an unknown fit as `contain`, which neither
        // fills the card nor enlarges a small image.
        $url = Image::manipulate($asset)
            ->width($width)
            ->height($height)
            ->fit('crop_focal')
            ->format('jpg')
            ->quality(85)
            ->build();

        $alt = $asset->get('alt');

        return [
            'url' => $this->absolute((string) $url),
            'width' => $width,
            'height' => $height,
            'alt' => filled($alt) ? (string) $alt : null,
        ];
    }

    /**
     * The share image's size, 1200×630 as Facebook, LinkedIn and X expect.
     * Override both for another.
     *
     * @api
     */
    protected function imageWidth(): int
    {
        return 1200;
    }

    /** @api */
    protected function imageHeight(): int
    {
        return 630;
    }

    protected function publisherId(): string
    {
        return $this->home().'#publisher';
    }

    protected function home(): string
    {
        return rtrim(Site::current()->absoluteUrl(), '/').'/';
    }

    /**
     * A site-relative URL made absolute against the current site's address.
     *
     * @api
     */
    public function absolute(string $url): string
    {
        return preg_match('#^https?://#i', $url) ? $url : $this->home().ltrim($url, '/');
    }
}
