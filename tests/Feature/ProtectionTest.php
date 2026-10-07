<?php

use JothamLec\MarketingToolkit\IndexNow\IndexNow;
use JothamLec\MarketingToolkit\Reports\Runner;
use JothamLec\MarketingToolkit\SiteSeo;

beforeEach(function () {
    seoGlobal(['default_description' => 'Acme makes things.']);
    config([
        'marketing-toolkit.llms_txt.enabled' => true,
        'statamic.protect.schemes.logged_in' => ['driver' => 'auth', 'login_url' => '/login'],
    ]);
});

test('a protected page is kept out of the sitemap, llms.txt and IndexNow, and has no share card', function () {
    entryIn('pages', 'about', ['description' => 'Who we are.']);
    $members = entryIn('pages', 'members', ['description' => 'The board minutes.', 'protect' => 'logged_in']);

    expect(app(SiteSeo::class)->isProtected($members))->toBeTrue()
        ->and(app(IndexNow::class)->queued())->toBe(['https://example.test/about'])
        ->and(app(SiteSeo::class)->generatedImageUrl($members))->toBeNull()
        ->and(metaFor($members, '/members')->image)->toBeNull();

    $this->get('https://example.test/sitemap.xml')->assertSee('/about')->assertDontSee('/members');
    $this->get('https://example.test/llms.txt')->assertSee('Who we are.')->assertDontSee('board minutes')->assertDontSee('/members');
    $this->get('https://example.test/og/members.png')->assertNotFound();
});

test('site-wide protection keeps every page out; a scheme that doesn\'t exist counts as protected, as Statamic denies it', function () {
    $about = entryIn('pages', 'about');
    $typo = entryIn('pages', 'typo', ['protect' => 'no_such_scheme']);
    $seo = app(SiteSeo::class);

    expect($seo->isProtected($about))->toBeFalse()
        ->and($seo->isProtected($typo))->toBeTrue();

    config(['statamic.protect.default' => 'logged_in']);

    expect($seo->isProtected($about))->toBeTrue();
    $this->get('https://example.test/sitemap.xml')->assertDontSee('/about');
});

test('a protected page is left out of reports, rather than counted as a page that fails to render', function () {
    entryIn('pages', 'about');
    entryIn('pages', 'members', ['protect' => 'logged_in']);
    $runner = app(Runner::class);

    $report = $runner->runToEnd($runner->start());

    expect($report->pages()->pluck('url')->all())->toContain('https://example.test/about')->not->toContain('https://example.test/members');
});
