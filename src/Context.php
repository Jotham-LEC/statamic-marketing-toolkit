<?php

namespace JothamLec\MarketingToolkit;

use Illuminate\Http\Request;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Structures\Page;

/**
 * What a page's SEO is worked out from: the entry or term being shown (if
 * any), the request, and values the template passes in to override the rules
 * (a controller page's title, a 404's status).
 */
final readonly class Context
{
    /**
     * @param  array<string, mixed>  $overrides  title, description, canonical, image, og_type, noindex
     */
    public function __construct(
        public ?Entry $entry,
        public ?Term $term,
        public Request $request,
        public array $overrides = [],
        public int $status = 200,
    ) {}

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function make(mixed $content, ?Request $request = null, array $overrides = [], int $status = 200): self
    {
        // A page in a structured collection arrives as the tree's Page wrapping its entry.
        $content = $content instanceof Page ? $content->entry() : $content;

        return new self(
            entry: $content instanceof Entry ? $content : null,
            term: $content instanceof Term ? $content : null,
            request: $request ?? request(),
            overrides: array_filter($overrides, fn ($value) => filled($value) || $value === false),
            status: $status,
        );
    }

    public function content(): Entry|Term|null
    {
        return $this->entry ?? $this->term;
    }

    public function isHome(): bool
    {
        return $this->entry?->uri() === '/';
    }

    /**
     * The entry's `seo` group, without the fields left empty: an override
     * cleared in the control panel counts as not set. A translation that has
     * no group of its own takes its origin's, as the control panel shows it
     * (a group is linked to its origin, or not, as a whole).
     *
     * @return array<string, mixed>
     */
    public function seo(): array
    {
        $values = $this->content()?->value('seo');

        return array_filter(is_array($values) ? $values : [], fn ($value) => filled($value));
    }

    public function override(string $key): mixed
    {
        return $this->overrides[$key] ?? null;
    }

    public function page(): int
    {
        $page = $this->request->query('page');

        return is_numeric($page) ? max(1, (int) $page) : 1;
    }
}
