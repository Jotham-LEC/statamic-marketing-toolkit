<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use JothamLec\Seo\Redirects\Redirect;
use Statamic\Facades\URL;

function rule(string $source, ?string $target, int $status = 301, bool $active = true): Redirect
{
    return Redirect::query()->create(['source' => $source, 'target' => $target, 'status' => $status, 'active' => $active]);
}

test('an exact rule sends a missing address on, and counts the hit', function () {
    $redirect = rule('/old-page', '/new-page');

    $this->get('/old-page')->assertStatus(301)->assertRedirect('/new-page');

    expect($redirect->fresh()->hits)->toBe(1)
        ->and($redirect->fresh()->last_hit_at)->not->toBeNull();
});

test('rules are stored and matched without the trailing slash or query string', function () {
    $redirect = rule('old-page/?ref=x', 'new-page/');

    expect($redirect->source)->toBe('/old-page')->and($redirect->target)->toBe('/new-page');

    $this->get('/old-page?utm_source=mail')->assertRedirect('/new-page?utm_source=mail');
});

test('a wildcard passes what it matched to the target, and the longest source wins', function () {
    rule('/blog/*', '/essays/$1');
    rule('/blog/2019/*', '/archive/$1', 302);
    rule('/a/*/b/*', '/x/$2/$1');

    $this->get('/blog/on-reading')->assertStatus(301)->assertRedirect('/essays/on-reading');
    $this->get('/blog/2019/old/post')->assertStatus(302)->assertRedirect('/archive/old/post');
    $this->get('/a/one/b/two')->assertRedirect('/x/two/one');
});

test('an exact rule wins over a wildcard', function () {
    rule('/blog/*', '/essays/$1');
    rule('/blog/special', '/special');

    $this->get('/blog/special')->assertRedirect('/special');
});

test('410 answers "gone" with the site\'s error page', function () {
    rule('/withdrawn', null, 410);

    $this->get('/withdrawn')->assertStatus(410);
});

test('a page that exists wins over a rule, and inactive rules do nothing', function () {
    entryIn('pages', 'about');
    rule('/about', '/elsewhere');
    rule('/paused', '/elsewhere', active: false);

    $this->get('https://example.test/about')->assertOk();
    $this->get('/paused')->assertNotFound();
});

test('another site\'s address is kept as typed', function () {
    rule('/shop', 'https://shop.example.com/');

    $this->get('/shop')->assertRedirect('https://shop.example.com/');
});

test('targets get the trailing slash on a site that has Statamic add them, so there is one hop', function () {
    URL::enforceTrailingSlashes();
    rule('/old', '/new');
    rule('/old-file', '/files/report.pdf');

    // The test client strips trailing slashes, so the slashed request goes straight to the kernel.
    $location = fn (string $uri) => app(Kernel::class)->handle(Request::create('https://example.test'.$uri))->headers->get('Location');

    expect($location('/old/'))->toBe('https://example.test/new/')
        ->and($location('/old-file/'))->toBe('https://example.test/files/report.pdf');
});

test('the cached rules follow saves and deletes', function () {
    $redirect = rule('/old', '/one');
    $this->get('/old')->assertRedirect('/one');

    $redirect->update(['target' => '/two']);
    $this->get('/old')->assertRedirect('/two');

    $redirect->delete();
    $this->get('/old')->assertNotFound();
});

test('redirects can be turned off', function () {
    config(['seo.redirects.enabled' => false]);
    rule('/old', '/new');

    $this->get('/old')->assertNotFound();
});

test('what a wildcard matched is passed on encoded, and an encoded ? stays part of the path', function () {
    rule('/old/*', '/new/$1');
    rule('/page', '/elsewhere');

    expect($this->get('/old/a%3Fb')->headers->get('Location'))->toBe('http://localhost/new/a%3Fb')
        ->and($this->get('/old/a%20b/caf%C3%A9')->headers->get('Location'))->toBe('http://localhost/new/a%20b/caf%C3%A9');

    $this->get('/page%3Fx')->assertNotFound();
});

test('a source typed percent-encoded, as copied from the address bar, matches', function () {
    $redirect = rule('/caf%C3%A9', '/x');
    rule('/a%20b', '/y');

    expect($redirect->source)->toBe('/café');
    $this->get('/caf%C3%A9')->assertRedirect('/x');
    $this->get('/a%20b')->assertRedirect('/y');
});

test('a source saved encoded before it was decoded on save still matches', function () {
    Redirect::query()->insert(['source' => '/caf%C3%A9', 'target' => '/x', 'status' => 301, 'active' => true, 'automatic' => false, 'hits' => 0]);

    $this->get('/caf%C3%A9')->assertRedirect('/x');
});

test('a target keeps its #fragment, after the visitor\'s query string', function () {
    expect(rule('/old', '/faq#shipping')->target)->toBe('/faq#shipping');

    expect($this->get('/old?utm=x')->headers->get('Location'))->toBe('http://localhost/faq?utm=x#shipping');
});

test('a rule that sends an address back to itself, directly or through another rule, is refused', function () {
    $fails = fn (string $source, string $target) => Redirect::validator(['source' => $source, 'target' => $target, 'status' => 301, 'active' => true])->fails();
    rule('/a', '/b');

    expect($fails('/same', '/same/'))->toBeTrue()
        ->and($fails('/x/*', '/x/$1'))->toBeTrue()
        ->and($fails('/b', '/a?ref=loop'))->toBeTrue()
        // Under its own source is fine: the pages there may exist.
        ->and($fails('/blog/*', '/blog/new/$1'))->toBeFalse()
        ->and($fails('/c', '/a'))->toBeFalse();
});

test('a rule saved before that check, sending an address to itself, is not served', function () {
    Redirect::query()->insert(['source' => '/a', 'target' => '/a/', 'status' => 301, 'active' => true, 'automatic' => false, 'hits' => 0]);

    $this->get('/a')->assertNotFound();
});
