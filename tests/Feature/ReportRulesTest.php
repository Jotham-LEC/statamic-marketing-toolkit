<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Lang;
use JothamLec\MarketingToolkit\Redirects\Redirect;
use JothamLec\MarketingToolkit\Reports\ExternalLinkChecker;
use JothamLec\MarketingToolkit\Reports\HtmlInspector;
use JothamLec\MarketingToolkit\Reports\LinkChecker;
use JothamLec\MarketingToolkit\Reports\PageFacts;
use JothamLec\MarketingToolkit\Reports\Rules\BrokenLinks;
use JothamLec\MarketingToolkit\Reports\Rules\Description;
use JothamLec\MarketingToolkit\Reports\Rules\ExternalLinks;
use JothamLec\MarketingToolkit\Reports\Rules\OgImage;
use JothamLec\MarketingToolkit\Reports\Runner;

/**
 * @param  array<string, mixed>  $facts
 */
function verdict(string $rule, array $facts, string $url = 'https://example.test/page'): string
{
    return app($rule)->check($url, new PageFacts(...$facts))->status;
}

test('the inspector reads what the checks need from the HTML', function () {
    entryIn('pages', 'about');
    Redirect::query()->create(['source' => '/old', 'target' => '/about']);

    $html = <<<'HTML'
        <!doctype html><html><head>
        <title> About   us &amp; more </title>
        <meta name="description" content="Who we are.">
        <meta name="robots" content="noindex, follow">
        <meta property="og:image" content="https://example.test/og/about.png">
        </head><body>
        <a href="/about">ok</a> <a href="https://example.test/nowhere?x=1">broken</a> <a href="/old">redirected</a>
        <a href="https://elsewhere.test/x#top">external</a> <a href="#top">anchor</a> <a href="mailto:a@b.c">mail</a>
        <a href="/sitemap.xml">a route</a> <a href="relative-link">relative</a>
        </body></html>
        HTML;

    $facts = app(HtmlInspector::class)->inspect($html);

    expect($facts->title)->toBe('About us & more')
        ->and($facts->description)->toBe('Who we are.')
        ->and($facts->noindex())->toBeTrue()
        ->and($facts->brokenLinks)->toBe(['/nowhere'])
        ->and($facts->redirectedLinks)->toBe(['/old'])
        ->and($facts->externalLinks)->toBe(['https://elsewhere.test/x'])
        ->and($facts->ogImage)->toBe('https://example.test/og/about.png');
});

test('a missing description or share image fails', function () {
    expect(verdict(Description::class, ['description' => null]))->toBe('fail')
        ->and(verdict(Description::class, ['description' => 'Who we are.']))->toBe('pass')
        ->and(verdict(OgImage::class, ['ogImage' => null]))->toBe('fail')
        ->and(verdict(OgImage::class, ['ogImage' => 'https://example.test/og.png']))->toBe('pass');
});

test('broken links are checked on hidden pages too; a missing description or image is not', function () {
    expect(app(BrokenLinks::class)->appliesToNoindex())->toBeTrue()
        ->and(app(ExternalLinks::class)->appliesToNoindex())->toBeTrue()
        ->and(app(Description::class)->appliesToNoindex())->toBeFalse()
        ->and(app(OgImage::class)->appliesToNoindex())->toBeFalse();
});

test('links within the site: broken fails, through a redirect warns', function () {
    expect(verdict(BrokenLinks::class, []))->toBe('pass')
        ->and(verdict(BrokenLinks::class, ['redirectedLinks' => ['/old']]))->toBe('warn')
        ->and(verdict(BrokenLinks::class, ['brokenLinks' => ['/gone'], 'redirectedLinks' => ['/old']]))->toBe('fail');
});

test('only a clear miss is a broken link to another site, and each address is asked once a day', function () {
    Http::fake([
        'gone.test/*' => Http::response('', 404),
        'fine.test/*' => Http::response('', 200),
        'blocked.test/*' => Http::response('', 403),
        'busy.test/*' => Http::response('', 503),
    ]);
    fakeDns(['gone.test' => '93.184.215.14', 'fine.test' => '93.184.215.14', 'blocked.test' => '93.184.215.14', 'busy.test' => '93.184.215.14']);
    $checker = app(ExternalLinkChecker::class);
    $urls = ['https://gone.test/a', 'https://fine.test/b', 'https://blocked.test/c', 'https://busy.test/d'];

    expect($checker->broken($urls))->toBe(['https://gone.test/a'])
        ->and($checker->broken($urls))->toBe(['https://gone.test/a']);

    // Four HEADs, and a GET for the site that refused HEAD; nothing the second time.
    Http::assertSentCount(5);
    expect(verdict(ExternalLinks::class, ['brokenExternalLinks' => ['https://gone.test/a']]))->toBe('fail');
});

test('links to this machine or a private network are never asked, nor followed there by a redirect', function () {
    Http::fake([
        'moved.test/*' => Http::response('', 301, ['Location' => 'http://internal.test/admin']),
        '*' => Http::response('', 404),
    ]);
    fakeDns(['internal.test' => '10.0.0.5', 'moved.test' => '93.184.215.14', 'split.test' => ['93.184.215.14', '127.0.0.1']]);

    $broken = app(ExternalLinkChecker::class)->broken([
        'http://127.0.0.1/admin', 'http://169.254.169.254/latest/meta-data', 'http://[::1]/', 'http://localhost.test/',
        'http://internal.test/', 'https://split.test/', 'https://moved.test/a',
    ]);

    // Only moved.test was asked; its redirect into the private network was not followed.
    Http::assertSentCount(1);
    expect($broken)->toBe(['http://localhost.test/']);
});

test('a link that redirects is judged where it ends, and a host that doesn\'t resolve is broken without asking', function () {
    Http::fake([
        'old.test/new' => Http::response('', 404),
        'old.test/*' => Http::response('', 301, ['Location' => '/new']),
        'hop.test/*' => Http::response('', 302, ['Location' => 'https://fine.test/landing']),
        'fine.test/*' => Http::response('', 200),
    ]);
    fakeDns(['old.test' => '93.184.215.14', 'hop.test' => '93.184.215.14', 'fine.test' => '93.184.215.15']);

    expect(app(ExternalLinkChecker::class)->broken(['https://old.test/a', 'https://hop.test/b', 'https://nowhere.test/c']))
        ->toBe(['https://old.test/a', 'https://nowhere.test/c']);
});

test('a link is checked against files in public/ and nowhere above it', function () {
    $links = app(LinkChecker::class);

    expect($links->check('/index.php'))->toBe('ok')
        ->and($links->check('/../composer.json'))->toBe('broken')
        ->and($links->check('/x/../../composer.json'))->toBe('broken');
});

test('a result keeps a translation key and its parameters, and reads as English', function () {
    $result = app(BrokenLinks::class)->check('https://example.test/page', new PageFacts(brokenLinks: ['/gone']));

    expect($result->toArray())->toBe(['status' => 'fail', 'message' => 'seo::reports.messages.links_broken', 'params' => ['links' => '/gone']])
        ->and($result->text())->toBe('Links to pages that don’t exist: /gone.')
        ->and(app(OgImage::class)->check('https://example.test/page', new PageFacts)->toArray())->toBe(['status' => 'fail', 'message' => 'seo::reports.messages.og_image_missing']);
});

test('a long list of links ends with how many more there are', function () {
    $links = ['/1', '/2', '/3', '/4', '/5', '/6'];

    expect(app(BrokenLinks::class)->check('https://example.test/a', new PageFacts(brokenLinks: $links))->text())
        ->toBe('Links to pages that don’t exist: /1, /2, /3, /4, /5 and 1 more.')
        ->and(app(BrokenLinks::class)->check('https://example.test/a', new PageFacts(redirectedLinks: ['/old']))->text())
        ->toBe('Links that go through a redirect (link to the new address instead): /old.');
});

test('every built-in check’s name and message has English words', function () {
    $page = new PageFacts(brokenLinks: ['/gone'], brokenExternalLinks: ['https://gone.test/']);

    foreach (Runner::RULES as $class) {
        $rule = app($class);
        $result = $rule->check('https://example.test/a', $page);

        expect(Lang::has($rule->label()))->toBeTrue($class)
            ->and($result->message === '' || Lang::has($result->message))->toBeTrue($class.': '.$result->message);
    }
});
