<?php

namespace JothamLec\MarketingToolkit\Listeners;

use JothamLec\MarketingToolkit\Favicons\Favicons;
use JothamLec\MarketingToolkit\Support\Sites;
use Statamic\Events\GlobalVariablesSaved;
use Throwable;

/**
 * Saving SEO & brand makes the icons again, from the image and colours just
 * saved: on every site, since the others take what they leave empty from it.
 */
class RemakeFavicons
{
    public function handle(GlobalVariablesSaved $event): void
    {
        if ($event->variables->handle() !== config('seo.global') || ! config('seo.favicons.enabled', true)) {
            return;
        }

        $favicons = app(Favicons::class);
        $favicons->flush();

        // Nothing to make, and no need to look the asset up.
        if (blank($event->variables->value('favicon'))) {
            return;
        }

        try {
            Sites::as($event->variables->locale(), fn () => $favicons->file('site.webmanifest'));
        } catch (Throwable $exception) {
            // The next request for an icon tries again.
            report($exception);
        }
    }
}
