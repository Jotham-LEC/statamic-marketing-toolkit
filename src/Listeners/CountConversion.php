<?php

namespace JothamLec\MarketingToolkit\Listeners;

use Illuminate\Support\Facades\Cookie;
use JothamLec\MarketingToolkit\Settings;
use JothamLec\MarketingToolkit\Support\Edition;
use Statamic\Events\SubmissionCreated;

/**
 * Pro: a submission that went through leaves a short-lived `mt_conversion`
 * cookie naming its form. The next page, or the page itself after an AJAX
 * submission, reads it in <s:seo:head />'s script and sends the lead to
 * each tracking tool. A cookie rather than the session, so it works on a
 * page served from the static cache.
 */
class CountConversion
{
    public const string COOKIE = 'mt_conversion';

    public function handle(SubmissionCreated $event): void
    {
        if (! Edition::pro() || ! app(Settings::class)->bool('conversions', true)) {
            return;
        }

        Cookie::queue(Cookie::make(self::COOKIE, $event->submission->form()->handle(), 5, httpOnly: false, sameSite: 'lax'));
    }
}
