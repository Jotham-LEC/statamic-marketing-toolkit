<?php

namespace JothamLec\MarketingToolkit\Toolbar;

use Composer\InstalledVersions;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Vite;
use JothamLec\MarketingToolkit\Support\Package;
use Statamic\Contracts\Auth\User;
use Symfony\Component\HttpFoundation\Cookie as CookieObject;
use Throwable;

/**
 * The front-end toolbar's rules, in one place. Pages print the same small
 * guard for everyone (guard()), so they stay safe to cache; it loads the
 * toolbar only while the `mt_toolbar` cookie is there. That cookie is a hint,
 * not a credential: it is set for a control panel user (on sign-in and on
 * control panel requests) and removed on sign-out.
 * What the toolbar shows comes from an endpoint that checks the session.
 */
final class Toolbar
{
    public const string COOKIE = 'mt_toolbar';

    public static function enabled(): bool
    {
        return (bool) config('marketing-toolkit.toolbar.enabled');
    }

    /**
     * Whether $user gets the toolbar: the module is on and they may use the
     * control panel. Its corner, shortcut and whether it is hidden are kept
     * in the browser, where the toolbar's More panel changes them.
     */
    public static function wants(?User $user): bool
    {
        return self::enabled() && $user !== null && $user->can('access cp');
    }

    /**
     * The marker cookie: readable by the page's script (not HttpOnly), no
     * data in it, on the session's domain and for the session's lifetime.
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
     * The inline script pages print, the same for every visitor: it loads the
     * toolbar only when the marker cookie is there and the page isn't in a
     * frame. Empty while the module is off, in Live Preview, and in a static
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
     * An address without its scheme and host, so the same guard works on
     * every domain the site answers on.
     */
    private static function path(string $url): string
    {
        return (string) parse_url($url, PHP_URL_PATH);
    }

    /**
     * The installed version, so an update loads the new script rather than a cached one.
     */
    private static function version(): string
    {
        try {
            // The commit, which changes with every build, else the version (a path repository has no commit).
            return substr((string) (InstalledVersions::getReference(Package::NAME) ?? InstalledVersions::getPrettyVersion(Package::NAME)), 0, 12) ?: 'dev';
        } catch (Throwable) {
            return 'dev';
        }
    }
}
