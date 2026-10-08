<?php

namespace JothamLec\MarketingToolkit\Listeners;

use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Cookie;
use JothamLec\MarketingToolkit\Toolbar\Toolbar;

/**
 * Signing out removes the toolbar's marker cookie, so the next page loads nothing for the toolbar.
 */
final class ToolbarSignOut
{
    public function handle(Logout $event): void
    {
        if (! Toolbar::enabled()) {
            return;
        }

        Cookie::queue(Toolbar::forget());
    }
}
