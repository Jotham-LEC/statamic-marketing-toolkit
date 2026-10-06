<?php

namespace JothamLec\MarketingToolkit\Support;

use Statamic\Contracts\Assets\Asset;
use Statamic\Contracts\Query\Builder;
use Statamic\Facades\Asset as AssetFacade;
use Statamic\Fields\Value;

/**
 * The one asset a field holds, whatever shape it comes in: augmented or
 * not, a field that takes one file or several, or a stored id.
 */
class Assets
{
    public static function from(mixed $value): ?Asset
    {
        $value = $value instanceof Value ? $value->value() : $value;
        // A field that takes more than one file augments to a query, not a list.
        $value = $value instanceof Builder ? $value->first() : $value;
        $value = is_iterable($value) && ! $value instanceof Asset ? collect($value)->first() : $value;

        // Without a blueprint field to augment through, a stored "container::path" id still resolves.
        if (is_string($value)) {
            $value = AssetFacade::find($value);
        }

        return $value instanceof Asset ? $value : null;
    }
}
