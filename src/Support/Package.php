<?php

namespace JothamLec\MarketingToolkit\Support;

use Statamic\Facades\Addon;
use Statamic\Facades\User;

/**
 * Names the addon's Composer package, which is how Statamic's Addon facade finds it and
 * where its settings (Features, reports, the Search Console key) are kept.
 */
final class Package
{
    public const string NAME = 'jotham-lec/statamic-marketing-toolkit';

    /**
     * Determines whether the signed-in user may change the addon's settings (Statamic's
     * `editSettings`), which are the report settings, Search Console and Features.
     */
    public static function canEditSettings(): bool
    {
        return (bool) User::current()?->can('editSettings', Addon::get(self::NAME));
    }
}
