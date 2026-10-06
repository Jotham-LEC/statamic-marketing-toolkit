<?php

namespace JothamLec\MarketingToolkit\Listeners;

use Illuminate\Support\Facades\Cookie;
use JothamLec\MarketingToolkit\Settings;
use Statamic\Events\SubmissionCreated;

/**
 * Pro: a submission that went through leaves a short-lived `mt_conversion`
 * cookie naming its form. The next page, or the page itself after an AJAX
 * submission, reads it in <s:seo:head />'s script and sends the lead to
 * each tracking tool. A cookie rather than the session, so it works on a
 * page served from the static cache. Not registered in Free or with leads
 * off (ServiceProvider::leaveOutUnused()); the check here covers a queue
 * worker or Octane process booted before leads were switched off.
 */
class CountConversion
{
    public const string COOKIE = 'mt_conversion';

    public function handle(SubmissionCreated $event): void
    {
        if (! config('seo.leads.enabled') || ! app(Settings::class)->bool('conversions', true)) {
            return;
        }

        Cookie::queue(Cookie::make(self::COOKIE, $event->submission->form()->handle(), 5, httpOnly: false, sameSite: 'lax'));
    }
}
