<?php

namespace JothamLec\MarketingToolkit\Preview;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Taxonomies\Term as TermContract;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Fields\Blueprint as BlueprintObject;
use Statamic\Support\Arr;

/**
 * Builds the entry or term that a publish form describes from its unsaved values,
 * the way Statamic's live preview builds one, and never saves it. The draft counts
 * as published, so an editor sees the page as it will look once it is live.
 */
class Draft
{
    /**
     * These fields are not plain data, because Statamic keeps them as properties.
     */
    private const array PROPERTIES = ['slug', 'blueprint', 'published', 'date', 'parent', 'seo_preview'];

    public static function fromRequest(Request $request): EntryContract|TermContract
    {
        return self::from($request->all());
    }

    /**
     * @param  array<string, mixed>  $input  The data a publish form sends, which is `blueprint`
     *                                       (its fully qualified handle), `site`, `values`, and
     *                                       `reference` when it edits saved content.
     */
    public static function from(array $input): EntryContract|TermContract
    {
        $blueprint = Blueprint::find((string) ($input['blueprint'] ?? ''));

        abort_unless($blueprint instanceof BlueprintObject, 422, 'Unknown blueprint.');

        $site = Site::get((string) ($input['site'] ?? '')) ?? Site::default();
        // The form's site gives the preview its site's values. Statamic's term
        // policy doesn't ask about the site, so it is asked here for every form.
        Gate::authorize('view', $site);
        $values = (array) ($input['values'] ?? []);
        $reference = (string) ($input['reference'] ?? '');
        $data = Arr::except($blueprint->fields()->addValues($values)->process()->values()->all(), self::PROPERTIES);
        $slug = is_string($values['slug'] ?? null) && $values['slug'] !== '' ? $values['slug'] : null;
        [$kind, $handle] = array_pad(explode('.', (string) $blueprint->namespace(), 2), 2, null);

        return match ($kind) {
            'collections' => self::entry($reference, $handle, $site->handle(), $data, $slug),
            'taxonomies' => self::term($reference, $handle, $site->handle(), $data, $slug),
            default => abort(422, 'Only entries and terms have a preview.'),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function entry(string $reference, ?string $handle, string $site, array $data, ?string $slug): EntryContract
    {
        $collection = Collection::findByHandle((string) $handle);
        abort_unless($collection !== null, 422, 'Unknown collection.');

        $existing = self::stored($reference);
        $existing = $existing instanceof EntryContract ? $existing : null;

        if ($existing) {
            Gate::authorize('view', $existing);

            $entry = clone $existing;
            $entry->data($existing->data()->merge($data));
        } else {
            // The site is passed in, so the policy also asks whether they may work on it.
            Gate::authorize('create', [EntryContract::class, $collection, Site::get($site)]);

            $entry = Entry::make()->collection($collection)->locale($site)->data($data)->slug('slug');

            if ($collection->dated()) {
                $entry->date(now());
            }
        }

        if ($slug) {
            $entry->slug($slug);
        }

        return $entry->published(true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function term(string $reference, ?string $handle, string $site, array $data, ?string $slug): TermContract
    {
        $taxonomy = Taxonomy::findByHandle((string) $handle);
        abort_unless($taxonomy !== null, 422, 'Unknown taxonomy.');

        $existing = self::stored($reference, $site);
        $existing = $existing instanceof TermContract ? $existing : null;

        if ($existing) {
            Gate::authorize('view', $existing);
            $data = [...$existing->data()->all(), ...$data];
        } else {
            Gate::authorize('create', [TermContract::class, $taxonomy, Site::get($site)]);
        }

        // A fresh term is made, so the stored one (and Statamic's cache of it) is never touched.
        return Term::make()
            ->taxonomy($taxonomy)
            ->slug($slug ?? $existing?->slug() ?? 'slug')
            ->in($site)
            ->data($data);
    }

    /**
     * Finds the saved entry or term that a publish form's reference names ("entry::{id}",
     * "term::{taxonomy}::{slug}::{site}"), in $site or the reference's own site. It
     * returns null on a create form.
     */
    public static function stored(string $reference, ?string $site = null): EntryContract|TermContract|null
    {
        $parts = explode('::', $reference);

        return match ($parts[0]) {
            'entry' => isset($parts[1]) ? Entry::find($parts[1]) : null,
            'term' => isset($parts[2]) ? Term::find($parts[1].'::'.$parts[2])?->in($site ?? $parts[3] ?? Site::default()->handle()) : null,
            default => null,
        };
    }
}
