<?php

namespace JothamLec\MarketingToolkit\Support;

use Statamic\Contracts\Assets\Asset;
use Statamic\Contracts\Query\Builder;
use Statamic\Facades\Asset as AssetFacade;
use Statamic\Fields\Value;

/**
 * Returns the one asset a field holds, whatever shape it comes in. The value may be augmented or
 * not, come from a field that takes one file or several, or be a stored id.
 */
class Assets
{
    public static function from(mixed $value): ?Asset
    {
        $value = $value instanceof Value ? $value->value() : $value;
        // A field that takes more than one file augments to a query, not a list.
        $value = $value instanceof Builder ? $value->first() : $value;
        $value = is_iterable($value) && ! $value instanceof Asset ? collect($value)->first() : $value;

        // A stored "container::path" id still resolves when there is no blueprint field to augment through.
        if (is_string($value)) {
            $value = AssetFacade::find($value);
        }

        return $value instanceof Asset ? $value : null;
    }
}
