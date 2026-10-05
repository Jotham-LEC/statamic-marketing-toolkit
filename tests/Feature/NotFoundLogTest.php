<?php

use JothamLec\Seo\NotFound\MissingPath;
use JothamLec\Seo\Redirects\Redirect;

test('a 404 is logged once per path, with hits, dates and the last referrer', function () {
    $this->get('/missing', ['Referer' => 'https://elsewhere.test/a', 'User-Agent' => 'Mozilla/5.0']);
    $this->travel(5)->minutes();
    $this->get('/missing/', ['Referer' => 'https://elsewhere.test/b', 'User-Agent' => 'Mozilla/5.0'])->assertNotFound();

    $row = MissingPath::query()->sole();

    expect($row->path)->toBe('/missing')
        ->and($row->hits)->toBe(2)
        ->and($row->referrer)->toBe('https://elsewhere.test/b')
        ->and($row->last_seen_at->gt($row->first_seen_at))->toBeTrue();
});

test('bots, scanner probes, other methods and the control panel are not logged', function () {
    $this->get('/missing', ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)']);
    $this->get('/wp-login.php', ['User-Agent' => 'Mozilla/5.0']);
    $this->get('/.env', ['User-Agent' => 'Mozilla/5.0']);
    $this->post('/missing', [], ['User-Agent' => 'Mozilla/5.0']);
    $this->get('/cp/nothing-here', ['User-Agent' => 'Mozilla/5.0']);

    expect(MissingPath::query()->count())->toBe(0);
});

test('pages that exist and redirected addresses are not logged', function () {
    entryIn('pages', 'about');
    Redirect::query()->create(['source' => '/old', 'target' => '/about']);

    $this->get('https://example.test/about', ['User-Agent' => 'Mozilla/5.0'])->assertOk();
    $this->get('/old', ['User-Agent' => 'Mozilla/5.0'])->assertRedirect();

    expect(MissingPath::query()->count())->toBe(0);
});

test('the log keeps the most recently seen paths up to its cap', function () {
    config(['seo.not_found.max_rows' => 3]);

    foreach (['/a', '/b', '/c', '/d'] as $path) {
        $this->get($path, ['User-Agent' => 'Mozilla/5.0']);
        $this->travel(1)->minutes();
    }

    expect(MissingPath::query()->orderBy('path')->pluck('path')->all())->toBe(['/b', '/c', '/d']);
});

test('logging can be turned off', function () {
    config(['seo.not_found.enabled' => false]);

    $this->get('/missing', ['User-Agent' => 'Mozilla/5.0']);

    expect(MissingPath::query()->count())->toBe(0);
});

test('only a web address is kept as the referrer, so the log never links to a script', function () {
    $this->get('/missing', ['User-Agent' => 'Mozilla/5.0', 'Referer' => 'javascript:alert(document.domain)']);

    expect(MissingPath::query()->sole()->referrer)->toBeNull();

    // A row written before this check is not handed to the control panel as a link.
    MissingPath::query()->create(['path' => '/older', 'referrer' => 'javascript:alert(1)', 'first_seen_at' => now(), 'last_seen_at' => now()]);
    $this->actingAs(cpUser(['view seo']));

    expect($this->getJson(cp_route('seo.404s.listing'))->json('data.*.referrer'))->toBe([null, null]);
});

test('a path or referrer that is not valid UTF-8 is not logged (Postgres would refuse it)', function () {
    $this->get('/%C3', ['User-Agent' => 'Mozilla/5.0']);
    $this->get('/a%00b', ['User-Agent' => 'Mozilla/5.0']);
    $this->get('/fine', ['User-Agent' => 'Mozilla/5.0', 'Referer' => "https://elsewhere.test/\xC3"]);

    expect(MissingPath::query()->pluck('referrer', 'path')->all())->toBe(['/fine' => null]);
});
