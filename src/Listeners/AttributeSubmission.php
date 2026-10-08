<?php

namespace JothamLec\MarketingToolkit\Listeners;

use JothamLec\MarketingToolkit\Conversions\Attribution;
use JothamLec\MarketingToolkit\Settings;
use JothamLec\MarketingToolkit\Support\Features;
use Statamic\Events\FormSubmitted;

/**
 * This listener copies where the lead came from into the submission before it is saved. It does nothing
 * while the leads module is off.
 */
final class AttributeSubmission
{
    public function handle(FormSubmitted $event): void
    {
        if (Features::on('leads') && app(Settings::class)->bool('attribution')) {
            app(Attribution::class)->apply($event->submission, request());
        }
    }
}
