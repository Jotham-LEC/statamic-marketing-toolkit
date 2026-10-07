<?php

namespace JothamLec\MarketingToolkit\Listeners;

use JothamLec\MarketingToolkit\Conversions\Attribution;
use JothamLec\MarketingToolkit\Settings;
use JothamLec\MarketingToolkit\Support\Features;
use Statamic\Events\FormSubmitted;

/**
 * Copies where the lead came from into the submission, before it is
 * saved. Not registered with leads off (ServiceProvider::leaveOutUnused());
 * the check here covers a queue worker or Octane process booted before
 * leads were switched off.
 */
class AttributeSubmission
{
    public function handle(FormSubmitted $event): void
    {
        if (Features::on('leads') && app(Settings::class)->bool('attribution')) {
            app(Attribution::class)->apply($event->submission, request());
        }
    }
}
