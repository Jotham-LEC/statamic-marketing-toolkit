<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use JothamLec\MarketingToolkit\Redirects\Csv;
use JothamLec\MarketingToolkit\Redirects\Redirect;
use Statamic\Facades\URL;

function rule(string $source, ?string $target, int $status = 301, bool $active = true): Redirect
{
    return Redirect::query()->create(['source' => $source, 'target' => $target, 'status' => $status, 'active' => $active]);
}

test('an exact rule sends a missing address on, and counts the hit', function () {
    $redirect = rule('/old-page', '/new-page');

    $this->get('/old-page')->assertStatus(301)->assertRedirect('https://example.test/new-page');

    expect($redirect->fresh()->hits)->toBe(1)
        ->and($redirect->fresh()->last_hit_at)->not->toBeNull();
});

test('rules are stored and matched without the trailing slash or query string', function () {
    $redirect = rule('old-page/?ref=x', 'new-page/');

    expect($redirect->source)->toBe('/old-page')->and($redirect->target)->toBe('/new-page');

    $this->get('/old-page?utm_source=mail')->assertRedirect('https://example.test/new-page?utm_source=mail');
});

test('a wildcard passes what it matched to the target, and the longest source wins', function () {
    rule('/blog/*', '/essays/$1');
    rule('/blog/2019/*', '/archive/$1', 302);
    rule('/a/*/b/*', '/x/$2/$1');

    $this->get('/blog/on-reading')->assertStatus(301)->assertRedirect('https://example.test/essays/on-reading');
    $this->get('/blog/2019/old/post')->assertStatus(302)->assertRedirect('https://example.test/archive/old/post');
    $this->get('/a/one/b/two')->assertRedirect('https://example.test/x/two/one');
});

test('an exact rule wins over a wildcard', function () {
    rule('/blog/*', '/essays/$1');
    rule('/blog/special', '/special');

    $this->get('/blog/special')->assertRedirect('https://example.test/special');
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
    $this->get('/old')->assertRedirect('https://example.test/one');

    $redirect->update(['target' => '/two']);
    $this->get('/old')->assertRedirect('https://example.test/two');

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

    expect($this->get('/old/a%3Fb')->headers->get('Location'))->toBe('https://example.test/new/a%3Fb')
        ->and($this->get('/old/a%20b/caf%C3%A9')->headers->get('Location'))->toBe('https://example.test/new/a%20b/caf%C3%A9');

    $this->get('/page%3Fx')->assertNotFound();
});

test('a source typed percent-encoded, as copied from the address bar, matches', function () {
    $redirect = rule('/caf%C3%A9', '/x');
    rule('/a%20b', '/y');

    expect($redirect->source)->toBe('/café');
    $this->get('/caf%C3%A9')->assertRedirect('https://example.test/x');
    $this->get('/a%20b')->assertRedirect('https://example.test/y');
});

test('a source saved encoded before it was decoded on save still matches', function () {
    Redirect::query()->insert(['source' => '/caf%C3%A9', 'target' => '/x', 'status' => 301, 'active' => true, 'automatic' => false, 'hits' => 0]);

    $this->get('/caf%C3%A9')->assertRedirect('https://example.test/x');
});

test('a target keeps its #fragment, after the visitor\'s query string', function () {
    expect(rule('/old', '/faq#shipping')->target)->toBe('/faq#shipping');

    expect($this->get('/old?utm=x')->headers->get('Location'))->toBe('https://example.test/faq?utm=x#shipping');
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

test('a rule that comes back to its own address through a longer chain of rules is refused', function () {
    $validator = fn (string $source, string $target, ?int $ignore = null) => Redirect::validator(['source' => $source, 'target' => $target, 'status' => 301, 'active' => true], $ignore);
    rule('/b', '/c');
    rule('/c', '/d/x');
    rule('/d/*', '/a');
    // A chain that loops without passing through here stops being followed.
    rule('/p', '/q');
    rule('/q', '/p');

    expect($validator('/a', '/b')->errors()->first('target'))->toBe('The redirects from that address lead back here after 3 steps, so visitors would go round in a loop.')
        ->and($validator('/z', '/b')->fails())->toBeFalse()
        ->and($validator('/z', '/p')->fails())->toBeFalse()
        // Editing the rule at the end of the chain: its old version doesn't count.
        ->and($validator('/d/*', '/elsewhere', Redirect::query()->where('source', '/d/*')->value('id'))->fails())->toBeFalse();
});

test('a rule saved before that check, sending an address to itself, is not served', function () {
    Redirect::query()->insert(['source' => '/a', 'target' => '/a/', 'status' => 301, 'active' => true, 'automatic' => false, 'hits' => 0]);

    $this->get('/a')->assertNotFound();
});

test('an address with a line break or another control character is refused, as it would go into the Location header', function () {
    $fails = fn (string $source, string $target) => Redirect::validator(['source' => $source, 'target' => $target, 'status' => 301, 'active' => true])->fails();

    expect($fails('/a', "/b\r\nSet-Cookie: x=1"))->toBeTrue()
        ->and($fails('/a', "https://elsewhere.test/\nx"))->toBeTrue()
        ->and($fails("/a\nb", '/b'))->toBeTrue()
        ->and($fails('/a', '/b'))->toBeFalse();
});

test('what a visitor typed can\'t choose the site a wildcard sends them to', function () {
    $fails = fn (string $target) => Redirect::validator(['source' => '/go/*', 'target' => $target, 'status' => 301, 'active' => true])->fails();

    expect($fails('https://example.com$1'))->toBeTrue()
        ->and($fails('https://$1'))->toBeTrue()
        ->and($fails('https://example.com/$1'))->toBeFalse()
        ->and($fails('/new/$1'))->toBeFalse();
});

test('a redirect goes to the site\'s own address, whatever Host header the request carried', function () {
    Redirect::query()->create(['source' => '/old', 'target' => '/new']);

    $this->get('https://example.test/old', ['Host' => 'evil.test'])
        ->assertRedirect('https://example.test/new');
    $this->get('https://evil.test/old')->assertRedirect('https://example.test/new');
});

test('a missing address is never kept by Statamic\'s static cache, so a redirect added later applies', function () {
    config(['statamic.static_caching.strategy' => 'half']);

    $this->get('https://example.test/old')->assertNotFound();
    rule('/old', '/new');

    $this->get('https://example.test/old')->assertRedirect('https://example.test/new');
});

test('letter case counts by default', function () {
    rule('/About-Us', '/about');
    rule('/Blog/*', '/essays/$1');

    $this->get('/about-us')->assertNotFound();
    $this->get('/blog/post')->assertNotFound();
    expect(Redirect::validator(['source' => '/about-us', 'target' => '/x', 'status' => 301, 'active' => true])->fails())->toBeFalse();
});

test('with case_sensitive off, a source matches in any letter case, and a wildcard passes on what it matched as typed', function () {
    config(['seo.redirects.case_sensitive' => false]);
    rule('/about-us', '/about');
    rule('/Café', '/coffee');
    rule('/blog/*', '/essays/$1');
    rule('/ÉTÉ/*', '/summer/$1');

    $this->get('/ABOUT-US/')->assertRedirect('https://example.test/about');
    $this->get('/About-Us')->assertRedirect('https://example.test/about');
    $this->get('/CAF%C3%89')->assertRedirect('https://example.test/coffee');
    $this->get('/BLOG/My-Post')->assertRedirect('https://example.test/essays/My-Post');
    $this->get('/%C3%A9t%C3%A9/Plage')->assertRedirect('https://example.test/summer/Plage');
});

test('turning case_sensitive off takes effect though the rules are cached', function () {
    rule('/about-us', '/about');
    $this->get('/ABOUT-US')->assertNotFound();

    config(['seo.redirects.case_sensitive' => false]);

    $this->get('/ABOUT-US')->assertRedirect('https://example.test/about');
});

test('with case_sensitive off, a source in the very case asked for wins over one differing only in case', function () {
    rule('/Old', '/one');
    rule('/old', '/two');
    config(['seo.redirects.case_sensitive' => false]);

    $this->get('/old')->assertRedirect('https://example.test/two');
    $this->get('/Old')->assertRedirect('https://example.test/one');
    // Neither in that case: the older rule.
    $this->get('/OLD')->assertRedirect('https://example.test/one');
});

test('with case_sensitive off, a source differing only in case is the same one, on the form and in a CSV import', function () {
    config(['seo.redirects.case_sensitive' => false]);
    $existing = rule('/about-us', '/about');

    expect(Redirect::validator(['source' => '/ABOUT-US/', 'target' => '/x', 'status' => 301, 'active' => true])->errors()->first('source'))
        ->toBe('Another redirect already starts from this address.')
        ->and(Redirect::validator(['source' => '/ABOUT-US', 'target' => '/x', 'status' => 301, 'active' => true], $existing->id)->fails())->toBeFalse();

    rule('/Café', '/coffee');
    expect(Redirect::validator(['source' => '/CAFÉ', 'target' => '/x', 'status' => 301, 'active' => true])->fails())->toBeTrue();

    $result = app(Csv::class)->import("source,target,status,active\n/About-Us,/company,301,1\n");

    expect($result)->toBe(['created' => 0, 'updated' => 1, 'errors' => []])
        ->and(Redirect::query()->count())->toBe(2)
        ->and($existing->fresh()->target)->toBe('/company');
});

test('with case_sensitive off, a chain of rules that comes back in another letter case is refused', function () {
    config(['seo.redirects.case_sensitive' => false]);
    $validator = fn (string $source, string $target) => Redirect::validator(['source' => $source, 'target' => $target, 'status' => 301, 'active' => true]);
    rule('/b', '/A');
    rule('/c', '/d');
    rule('/D', '/E/x');
    rule('/e/*', '/F');

    expect($validator('/a', '/B')->errors()->first('target'))->toBe('The redirect from that address leads back here, so the two would loop.')
        ->and($validator('/f', '/C')->errors()->first('target'))->toBe('The redirects from that address lead back here after 3 steps, so visitors would go round in a loop.')
        // A capitalised old address sent to its page is how this option is used, not a loop.
        ->and($validator('/About', '/about')->fails())->toBeFalse();
});
