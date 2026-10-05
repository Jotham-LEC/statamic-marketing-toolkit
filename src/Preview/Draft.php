<?php

namespace JothamLec\Seo\Preview;

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
 * The entry or term a publish form describes, built from its unsaved values
 * the way Statamic's live preview builds one, and never saved. It counts as
 * published, so an editor sees the page as it will look once it is live.
 */
class Draft
{
    /**
     * Fields that are not plain data: Statamic keeps them as properties.
     */
    private const array PROPERTIES = ['slug', 'blueprint', 'published', 'date', 'parent', 'seo_preview'];

    public static function fromRequest(Request $request): EntryContract|TermContract
    {
        $blueprint = Blueprint::find((string) $request->input('blueprint'));

        abort_unless($blueprint instanceof BlueprintObject, 422, 'Unknown blueprint.');

        $site = Site::get((string) $request->input('site')) ?? Site::default();
        $values = (array) $request->input('values', []);
        $data = Arr::except($blueprint->fields()->addValues($values)->process()->values()->all(), self::PROPERTIES);
        $slug = is_string($values['slug'] ?? null) && $values['slug'] !== '' ? $values['slug'] : null;
        [$kind, $handle] = array_pad(explode('.', (string) $blueprint->namespace(), 2), 2, null);

        return match ($kind) {
            'collections' => self::entry($request, $handle, $site->handle(), $data, $slug),
            'taxonomies' => self::term($request, $handle, $site->handle(), $data, $slug),
            default => abort(422, 'Only entries and terms have a preview.'),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function entry(Request $request, ?string $handle, string $site, array $data, ?string $slug): EntryContract
    {
        $collection = Collection::findByHandle((string) $handle);
        abort_unless($collection !== null, 422, 'Unknown collection.');

        $id = self::referenced($request, 'entry');
        $existing = $id === null ? null : Entry::find($id);

        if ($existing) {
            Gate::authorize('view', $existing);

            $entry = clone $existing;
            $entry->data($existing->data()->merge($data));
        } else {
            Gate::authorize('create', [EntryContract::class, $collection]);

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
    private static function term(Request $request, ?string $handle, string $site, array $data, ?string $slug): TermContract
    {
        $taxonomy = Taxonomy::findByHandle((string) $handle);
        abort_unless($taxonomy !== null, 422, 'Unknown taxonomy.');

        // A localized term's reference is term::{taxonomy}::{slug}::{site}.
        $reference = self::referenced($request, 'term');
        $id = $reference === null ? null : implode('::', array_slice(explode('::', $reference), 0, 2));
        $existing = $id === null ? null : Term::find($id)?->in($site);

        if ($existing) {
            Gate::authorize('view', $existing);
            $data = [...$existing->data()->all(), ...$data];
        } else {
            Gate::authorize('create', [TermContract::class, $taxonomy]);
        }

        // A fresh term, so the stored one (and Statamic's cache of it) is never touched.
        return Term::make()
            ->taxonomy($taxonomy)
            ->slug($slug ?? $existing?->slug() ?? 'slug')
            ->in($site)
            ->data($data);
    }

    /**
     * The id in the form's reference ("entry::{id}"), or null on a create form.
     */
    private static function referenced(Request $request, string $type): ?string
    {
        $reference = (string) $request->input('reference');

        return str_starts_with($reference, $type.'::') ? substr($reference, strlen($type) + 2) : null;
    }
}
