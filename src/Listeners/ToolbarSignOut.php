<?php

namespace JothamLec\MarketingToolkit\Listeners;

use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Cookie;
use JothamLec\MarketingToolkit\Toolbar\Toolbar;

/**
 * Signing out removes the toolbar's marker cookie, so the next page loads nothing.
 */
class ToolbarSignOut
{
    public function handle(Logout $event): void
    {
        Cookie::queue(Toolbar::forget());
    }
}
