<?php

namespace JothamLec\MarketingToolkit\Listeners;

use Illuminate\Support\Facades\Cookie;
use JothamLec\MarketingToolkit\Settings;
use JothamLec\MarketingToolkit\Support\Features;
use Statamic\Events\SubmissionCreated;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * A submission that went through leaves a short-lived `mt_conversion`
 * cookie naming its form. The next page, or the page itself after an AJAX
 * submission, reads it in <s:mt:head />'s script and sends the lead to
 * each tracking tool. A cookie rather than the session, so it works on a
 * page served from the static cache. It does nothing while the leads
 * module is off.
 *
 * Host-only on path /, not the session cookie's domain and path that
 * Cookie::make() would give it: the script clears it with `Path=/` and no
 * domain, and a clear that doesn't match leaves it to send the lead again
 * on every page for five minutes.
 */
final class CountConversion
{
    public const string COOKIE = 'mt_conversion';

    public function handle(SubmissionCreated $event): void
    {
        if (! Features::on('leads') || ! app(Settings::class)->bool('conversions', true)) {
            return;
        }

        Cookie::queue(SymfonyCookie::create(self::COOKIE, $event->submission->form()->handle(), now()->addMinutes(5), '/', null, config('session.secure'), false, false, SymfonyCookie::SAMESITE_LAX));
    }
}
