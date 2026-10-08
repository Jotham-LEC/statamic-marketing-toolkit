<?php

namespace JothamLec\MarketingToolkit\Listeners;

use JothamLec\MarketingToolkit\Favicons\Favicons;
use JothamLec\MarketingToolkit\Support\Features;
use JothamLec\MarketingToolkit\Support\Sites;
use Statamic\Events\GlobalVariablesSaved;
use Throwable;

/**
 * Saving Brand makes the icons again from the image and colours just saved. This happens on every site,
 * because the other sites take whatever they leave empty from it.
 */
final class RemakeFavicons
{
    public function handle(GlobalVariablesSaved $event): void
    {
        if ($event->variables->handle() !== config('marketing-toolkit.global') || ! Features::on('favicons')) {
            return;
        }

        $favicons = app(Favicons::class);
        $favicons->flush();

        // Without a favicon there is nothing to make, so we don't need to look the asset up.
        if (blank($event->variables->value('favicon'))) {
            return;
        }

        try {
            Sites::as($event->variables->locale(), fn () => $favicons->file('site.webmanifest'));
        } catch (Throwable $exception) {
            // We only report the error, because the next request for an icon tries again.
            report($exception);
        }
    }
}
