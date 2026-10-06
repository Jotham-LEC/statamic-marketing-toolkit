<?php

namespace JothamLec\MarketingToolkit\Listeners;

use JothamLec\MarketingToolkit\Conversions\Attribution;
use JothamLec\MarketingToolkit\Settings;
use JothamLec\MarketingToolkit\Support\Edition;
use Statamic\Events\FormSubmitted;

/**
 * Pro: copies where the lead came from into the submission, before it is saved.
 */
class AttributeSubmission
{
    public function handle(FormSubmitted $event): void
    {
        if (Edition::pro() && app(Settings::class)->bool('attribution')) {
            app(Attribution::class)->apply($event->submission, request());
        }
    }
}
