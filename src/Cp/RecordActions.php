<?php

namespace JothamLec\MarketingToolkit\Cp;

use Illuminate\Support\Collection;
use JothamLec\MarketingToolkit\Actions\CreateRedirect;
use JothamLec\MarketingToolkit\Actions\DeleteSeoRecords;
use Statamic\Actions\Action;
use Statamic\Facades\User;

/**
 * The actions the redirects and 404 listings offer: the addon's own, never
 * every action registered on the site.
 *
 * Statamic's `Action::for()` asks each registered action whether it applies to
 * a row, and some do not ask safely: Runway's Publish and Unpublish call
 * `runwayResource()` on any Eloquent model, so with Runway installed both
 * listings answered 500. This mirrors `Action::for()` and `Action::forBulk()`
 * over the addon's actions alone.
 */
final class RecordActions
{
    /**
     * @var list<class-string<Action>>
     */
    public const array ACTIONS = [DeleteSeoRecords::class, CreateRedirect::class];

    /**
     * @param  Collection<int, mixed>  $items
     * @param  array<string, mixed>  $context
     * @return Collection<int, Action>
     */
    public static function for(Collection $items, array $context): Collection
    {
        $single = $items->count() === 1;

        return collect(self::ACTIONS)
            ->map(fn (string $class): Action => app($class)->items($items)->context($context))
            ->filter(fn (Action $action): bool => $single ? (bool) $action->visibleTo($items->first()) : (bool) $action->visibleToBulk($items))
            ->filter(fn (Action $action): bool => $single ? (bool) $action->authorize(User::current(), $items->first()) : (bool) $action->authorizeBulk(User::current(), $items))
            ->values();
    }
}
