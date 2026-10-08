<?php

namespace JothamLec\MarketingToolkit\Toolbar;

use Composer\InstalledVersions;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Vite;
use JothamLec\MarketingToolkit\Support\Features;
use JothamLec\MarketingToolkit\Support\Package;
use Statamic\Contracts\Auth\User;
use Symfony\Component\HttpFoundation\Cookie as CookieObject;
use Throwable;

/**
 * Keeps the front-end toolbar's rules in one place. Pages print the same small
 * guard for everyone (guard()), so they stay safe to cache, and the guard loads
 * the toolbar only while the `mt_toolbar` cookie is there. That cookie is a hint,
 * not a credential, because it is set for a control panel user (on sign-in and on
 * control panel requests) and removed on sign-out.
 * What the toolbar shows comes from an endpoint that checks the session.
 */
final class Toolbar
{
    public const string COOKIE = 'mt_toolbar';

    /**
     * Holds the guard's code. The addresses it loads are attributes of its tag, so
     * the code is the same on every site and in every version, and a Content
     * Security Policy can allow it by its hash (docs/configuration.md#toolbar).
     */
    public const string SCRIPT = "(function(c,d){if(/(?:^|; )mt_toolbar=1(?:;|$)/.test(d.cookie)&&self===top){var s=d.createElement('script');s.src=c.dataset.src;s.dataset.endpoint=c.dataset.endpoint;d.head.appendChild(s);}})(document.currentScript,document);";

    public static function enabled(): bool
    {
        return Features::on('toolbar');
    }

    /**
     * Determines whether $user gets the toolbar, which is when the module is on and
     * they may use the control panel. Its corner and whether it is hidden are kept
     * in the browser, where the toolbar's More panel changes them.
     */
    public static function wants(?User $user): bool
    {
        return self::enabled() && $user !== null && $user->can('access cp');
    }

    /**
     * Makes the marker cookie, which the page's script can read (it isn't HttpOnly).
     * It holds no data and lives on the session's domain for the session's lifetime.
     */
    public static function cookie(): CookieObject
    {
        return Cookie::make(self::COOKIE, '1', (int) config('session.lifetime', 120), secure: self::secure(), httpOnly: false, sameSite: 'lax');
    }

    public static function forget(): CookieObject
    {
        return Cookie::make(self::COOKIE, '', -2628000, secure: self::secure(), httpOnly: false, sameSite: 'lax');
    }

    private static function secure(): bool
    {
        return (bool) config('session.secure') || request()->isSecure();
    }

    /**
     * Returns the inline script that pages print, which is the same for every visitor.
     * It loads the toolbar only when the marker cookie is there and the page isn't in
     * a frame. It is empty while the module is off, in Live Preview, and in a static
     * export (`ssg:generate`), which has no session.
     */
    public static function guard(): string
    {
        if (! self::enabled() || request()->isLivePreview() || app()->runningConsoleCommand(['ssg:generate', 'statamic:ssg:generate'])) {
            return '';
        }

        return view('marketing-toolkit::toolbar-guard', [
            'src' => self::path(url('vendor/statamic-marketing-toolkit/build/toolbar.js')).'?v='.self::version(),
            'endpoint' => self::path(route('statamic.mt.toolbar')),
            'nonce' => Vite::cspNonce(),
        ])->render();
    }

    /**
     * Strips the scheme and host from an address, so the same guard works on
     * every domain the site answers on.
     */
    private static function path(string $url): string
    {
        return (string) parse_url($url, PHP_URL_PATH);
    }

    /**
     * Returns the installed version, so an update loads the new script rather than a cached one.
     */
    private static function version(): string
    {
        try {
            // This is the commit, which changes with every build, or the version (a path repository has no commit).
            return substr((string) (InstalledVersions::getReference(Package::NAME) ?? InstalledVersions::getPrettyVersion(Package::NAME)), 0, 12) ?: 'dev';
        } catch (Throwable) {
            return 'dev';
        }
    }
}
