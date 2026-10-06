<?php

namespace JothamLec\MarketingToolkit\Listeners;

use JothamLec\MarketingToolkit\Conversions\Attribution;
use JothamLec\MarketingToolkit\Settings;
use Statamic\Events\FormSubmitted;

/**
 * Pro: copies where the lead came from into the submission, before it is
 * saved. Not registered in Free or with leads off (ServiceProvider::leaveOutUnused()).
 */
class AttributeSubmission
{
    public function handle(FormSubmitted $event): void
    {
        if (app(Settings::class)->bool('attribution')) {
            app(Attribution::class)->apply($event->submission, request());
        }
    }
}
