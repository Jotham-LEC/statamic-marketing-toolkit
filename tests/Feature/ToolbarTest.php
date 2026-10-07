<?php

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;
use JothamLec\MarketingToolkit\Toolbar\Toolbar;
use Statamic\Facades\Preference;
use Statamic\Facades\Token;
use Statamic\Facades\User;
use Statamic\Tokens\Handlers\LivePreview;

beforeEach(function () {
    // Its own layout, with <s:mt:body />, so other tests' pages stay as they are.
    entryIn('pages', 'about', ['layout' => 'toolbar-layout']);
});

function toolbarCookie(TestResponse $response): ?Symfony\Component\HttpFoundation\Cookie
{
    // A test's requests share one cookie jar, which a real request starts afresh.
    Cookie::flushQueuedCookies();

    return collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === Toolbar::COOKIE);
}

test('every visitor gets the same guard, which names the script and the endpoint by path', function () {
    $guest = $this->get('https://example.test/about')->assertOk()->getContent();

    $this->actingAs(cpUser(super: true));
    $signedIn = $this->get('https://example.test/about')->assertOk()->getContent();

    expect($guest)->toBe($signedIn)
        ->toContain('mt_toolbar=1')
        ->toContain('self===top')
        ->toContain('"\/vendor\/statamic-marketing-toolkit\/build\/toolbar.js?v=')
        ->toContain('"\/!\/marketing-toolkit\/toolbar"')
        ->not->toContain('editor@example.test')
        ->not->toContain('super@example.test');
});

test('the guard is left out with the toolbar off, and in Live Preview', function () {
    expect(renderAt('/', '<s:mt:body />'))->toContain('mt_toolbar=1');

    $token = Token::make(null, LivePreview::class);
    $token->save();
    expect(renderAt('/?token='.$token->token(), '<s:mt:body />'))->not->toContain('mt_toolbar');

    config(['marketing-toolkit.toolbar.enabled' => false]);
    expect(renderAt('/', '<s:mt:body />'))->toBe('');
});

test('<s:mt:toolbar /> prints the guard alone, with the CSP nonce', function () {
    Vite::useCspNonce('abc123');

    expect(trim(renderAt('/', '<s:mt:toolbar />')))->toStartWith('<script nonce="abc123">(function(d){')
        ->toEndWith('</script>');
});

test('a page cached while a control panel user is signed in holds nothing of theirs', function () {
    config(['statamic.static_caching.strategy' => 'half']);
    $this->actingAs(cpUser(super: true));

    $first = $this->get('https://example.test/about')->assertOk()->getContent();
    Auth::logout();
    $cached = $this->get('https://example.test/about')->assertOk()->getContent();

    expect($cached)->toBe($first)->not->toContain('super@example.test');
});

test('signing in sets the marker cookie for a control panel user, and not for anyone else', function () {
    $user = cpUser(super: true);
    event(new Login('web', $user, false));
    expect(Cookie::queued(Toolbar::COOKIE)?->getValue())->toBe('1')
        ->and(Cookie::queued(Toolbar::COOKIE)->isHttpOnly())->toBeFalse()
        ->and(Cookie::queued(Toolbar::COOKIE)->getSameSite())->toBe('lax');
});

test('a user who may not use the control panel gets no marker cookie', function () {
    $member = User::make()->email('member@example.test');
    $member->save();

    event(new Login('web', $member, false));

    expect(Cookie::queued(Toolbar::COOKIE))->toBeNull();
});

test('a user who hid the toolbar gets no marker cookie', function () {
    $user = cpUser(super: true);
    $user->setPreference('mt_toolbar_hidden', true)->save();

    event(new Login('web', $user, false));

    expect(Cookie::queued(Toolbar::COOKIE))->toBeNull()
        ->and(Toolbar::wants($user))->toBeFalse();
});

test('a control panel request sets the cookie once, and takes it away once the toolbar is hidden', function () {
    $user = cpUser(super: true);
    $this->actingAs($user);

    expect(toolbarCookie($this->get(cp_route('dashboard')))?->getValue())->toBe('1')
        ->and(toolbarCookie($this->withUnencryptedCookie(Toolbar::COOKIE, '1')->get(cp_route('dashboard'))))->toBeNull();

    $user->setPreference('mt_toolbar_hidden', true)->save();
    $forgotten = toolbarCookie($this->withUnencryptedCookie(Toolbar::COOKIE, '1')->get(cp_route('dashboard')));

    expect($forgotten?->getValue())->toBe('')
        ->and($forgotten->getExpiresTime())->toBeLessThan(time());
});

test('signing out removes the marker cookie', function () {
    event(new Logout('web', cpUser(super: true)));

    expect(Cookie::queued(Toolbar::COOKIE)?->getExpiresTime())->toBeLessThan(time());
});

test('the preferences: bottom left and Alt+Shift+M unless the user, a role or the defaults say otherwise; a cleared shortcut is none', function () {
    $user = cpUser(super: true);

    expect(Toolbar::preferences($user))->toBe(['hidden' => false, 'position' => 'bottom-left', 'shortcut' => 'Alt+Shift+M']);

    $user->setPreference('mt_toolbar_position', 'bottom-right')->setPreference('mt_toolbar_shortcut', null)->save();
    expect(Toolbar::preferences($user))->toBe(['hidden' => false, 'position' => 'bottom-right', 'shortcut' => null]);

    $user->setPreference('mt_toolbar_shortcut', 'Ctrl+Alt+T')->setPreference('mt_toolbar_position', 'top')->save();
    expect(Toolbar::preferences($user))->toMatchArray(['position' => 'bottom-left', 'shortcut' => 'Ctrl+Alt+T']);
});

test('the preferences are under Preferences → Marketing Toolkit, and a shortcut must be a combination', function () {
    $this->actingAs(cpUser(super: true));

    $this->get(cp_route('preferences.user.edit'))->assertOk();
    $tab = Preference::tabs()->get('marketing-toolkit');

    expect($tab['display'])->toBe('Marketing Toolkit')
        ->and(array_keys($tab['fields']))->toBe(['mt_toolbar_hidden', 'mt_toolbar_position', 'mt_toolbar_shortcut']);

    $this->patchJson(cp_route('preferences.user.update'), ['mt_toolbar_shortcut' => 'press m'])->assertJsonValidationErrors('mt_toolbar_shortcut');
});
