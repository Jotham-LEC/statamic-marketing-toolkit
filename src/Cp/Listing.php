<?php

namespace JothamLec\MarketingToolkit\Cp;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Statamic\CP\Column;

/**
 * Answers the requests of Statamic's `<Listing>` component for an Eloquent
 * table. It handles search, sorting by a listed column, and pagination, and it
 * returns the columns in the shape the component expects.
 */
final class Listing
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, string>  $columns  Maps each field to its label, and the first field is the default sort.
     * @param  list<string>  $searchable
     * @param  Closure(Collection<int, TModel>): iterable<array<string, mixed>>  $rows  Maps the page's models to rows.
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public static function respond(Builder $query, Request $request, array $columns, array $searchable, Closure $rows, string $defaultOrder = 'asc'): array
    {
        if ($search = trim((string) $request->input('search'))) {
            $query->where(function (Builder $query) use ($searchable, $search) {
                foreach ($searchable as $field) {
                    // This becomes `ilike` on Postgres, because Postgres's `like` is case-sensitive.
                    $query->orWhereLike($field, '%'.$search.'%', caseSensitive: false);
                }
            });
        }

        $sort = array_key_exists((string) $request->input('sort'), $columns) ? $request->input('sort') : array_key_first($columns);
        $order = in_array($request->input('order'), ['asc', 'desc'], true) ? $request->input('order') : $defaultOrder;
        $page = $query->orderBy($sort, $order)->orderBy('id')->paginate(min(500, max(10, (int) $request->input('perPage', 50))));

        return [
            'data' => collect($rows($page->getCollection()))->values()->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
                'activeFilterBadges' => [],
                'columns' => collect($columns)->map(fn ($label, $field) => Column::make($field)
                    ->label($label)
                    ->listable(true)
                    ->visible(true)
                    ->defaultVisibility(true)
                    ->sortable(true)
                    ->toArray())->values()->all(),
            ],
        ];
    }
}
