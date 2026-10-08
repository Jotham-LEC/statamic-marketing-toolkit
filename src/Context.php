<?php

namespace JothamLec\MarketingToolkit;

use Illuminate\Http\Request;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Structures\Page;

/**
 * This class holds what a page's SEO is worked out from. That is the entry or
 * term being shown (if there is one), the request, and the values the template
 * passes in to override the rules, such as a controller page's title or a 404's
 * status.
 */
final readonly class Context
{
    /**
     * @param  array<string, mixed>  $overrides  the title, description, canonical, image, og_type, and noindex
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
        // A page in a structured collection arrives as the tree's Page, which wraps its entry.
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
     * Returns the entry's `seo` group without the fields left empty, so an
     * override cleared in the control panel counts as not set. A translation
     * that has no group of its own takes its origin's, as the control panel
     * shows it, because a group is linked to its origin, or not, as a whole.
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
