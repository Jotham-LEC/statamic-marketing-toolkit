<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use JothamLec\Seo\Redirects\Redirect;

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

test('targets get the trailing slash on a site that adds them, so there is one hop', function () {
    config(['seo.trailing_slash' => 'add']);
    rule('/old', '/new');
    rule('/old-file', '/files/report.pdf');

    // The test client strips trailing slashes (and TrailingSlash would add
    // one first), so the slashed request goes straight to the kernel.
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
