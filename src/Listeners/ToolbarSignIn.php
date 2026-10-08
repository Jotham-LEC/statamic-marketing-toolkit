<?php

namespace JothamLec\MarketingToolkit\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Cookie;
use JothamLec\MarketingToolkit\Toolbar\Toolbar;
use Statamic\Facades\User;

/**
 * Signing in through the control panel or a front-end form sets the toolbar's marker cookie for a user
 * who gets the toolbar. This only happens on Statamic's own guards. When the toolbar is off,
 * Toolbar::wants() is false for everyone.
 */
final class ToolbarSignIn
{
    public function handle(Login $event): void
    {
        if (! in_array($event->guard, array_values((array) config('statamic.users.guards')), true)) {
            return;
        }

        if (Toolbar::wants(User::fromUser($event->user))) {
            Cookie::queue(Toolbar::cookie());
        }
    }
}
