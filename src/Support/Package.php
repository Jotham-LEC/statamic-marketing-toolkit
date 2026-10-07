<?php

namespace JothamLec\MarketingToolkit\Support;

use Statamic\Facades\Addon;
use Statamic\Facades\User;

/**
 * The addon's Composer package: how Statamic's Addon facade finds it, and
 * where its settings (Features, reports, the Search Console key) are kept.
 */
final class Package
{
    public const string NAME = 'jotham-lec/statamic-marketing-toolkit';

    /**
     * Whether the signed-in user may change the addon's settings (Statamic's
     * `editSettings`): the report settings, Search Console and Features.
     */
    public static function canEditSettings(): bool
    {
        return (bool) User::current()?->can('editSettings', Addon::get(self::NAME));
    }
}
