<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Lang;
use JothamLec\MarketingToolkit\Redirects\Redirect;
use JothamLec\MarketingToolkit\Reports\ExternalLinkChecker;
use JothamLec\MarketingToolkit\Reports\HtmlInspector;
use JothamLec\MarketingToolkit\Reports\LinkChecker;
use JothamLec\MarketingToolkit\Reports\PageFacts;
use JothamLec\MarketingToolkit\Reports\ReportSettings;
use JothamLec\MarketingToolkit\Reports\Result;
use JothamLec\MarketingToolkit\Reports\Rules\BrokenLinks;
use JothamLec\MarketingToolkit\Reports\Rules\Canonical;
use JothamLec\MarketingToolkit\Reports\Rules\DescriptionLength;
use JothamLec\MarketingToolkit\Reports\Rules\DescriptionUnique;
use JothamLec\MarketingToolkit\Reports\Rules\ExternalLinks;
use JothamLec\MarketingToolkit\Reports\Rules\ImageAlt;
use JothamLec\MarketingToolkit\Reports\Rules\JsonLd;
use JothamLec\MarketingToolkit\Reports\Rules\NoindexInSitemap;
use JothamLec\MarketingToolkit\Reports\Rules\OgImage;
use JothamLec\MarketingToolkit\Reports\Rules\OrphanPages;
use JothamLec\MarketingToolkit\Reports\Rules\SingleH1;
use JothamLec\MarketingToolkit\Reports\Rules\TitleLength;
use JothamLec\MarketingToolkit\Reports\Rules\TitleUnique;
use JothamLec\MarketingToolkit\Reports\Runner;
use JothamLec\MarketingToolkit\Reports\SiteFacts;

/**
 * @param  array<string, mixed>  $facts
 */
function verdict(string $rule, array $facts, ?SiteFacts $site = null, string $url = 'https://example.test/page'): string
{
    $result = app($rule)->check($url, new PageFacts(...$facts), $site ?? new SiteFacts(new ReportSettings([])));

    return $result->status;
}

/**
 * A result's message in the current language, as the reports screen shows it.
 */
function resultText(Result $result): string
{
    return Result::translate($result->message, $result->params);
}

test('the inspector reads what the checks need from the HTML', function () {
    entryIn('pages', 'about');
    Redirect::query()->create(['source' => '/old', 'target' => '/about']);

    $html = <<<'HTML'
        <!doctype html><html><head>
        <title> About   us &amp; more </title>
        <meta name="description" content="Who we are.">
        <link rel="canonical" href="https://example.test/about">
        <meta name="robots" content="noindex, follow">
        <meta property="og:image" content="https://example.test/og/about.png">
        <script type="application/ld+json">{"@context": "https://schema.org"}</script>
        <script type="application/ld+json">{broken</script>
        </head><body>
        <h1>About</h1><h1>Again</h1>
        <img src="a.jpg" alt="A"><img src="b.jpg" alt=""><img src="c.jpg">
        <a href="/about">ok</a> <a href="https://example.test/nowhere?x=1">broken</a> <a href="/old">redirected</a>
        <a href="https://elsewhere.test/x">external</a> <a href="#top">anchor</a> <a href="mailto:a@b.c">mail</a>
        <a href="/sitemap.xml">a route</a> <a href="relative-link">relative</a>
        </body></html>
        HTML;

    $facts = app(HtmlInspector::class)->inspect($html);

    expect($facts->title)->toBe('About us & more')
        ->and($facts->description)->toBe('Who we are.')
        ->and($facts->h1s)->toBe(['About', 'Again'])
        ->and($facts->canonical)->toBe('https://example.test/about')
        ->and($facts->noindex())->toBeTrue()
        ->and([$facts->images, $facts->imagesWithoutAlt])->toBe([3, 1])
        ->and($facts->brokenLinks)->toBe(['/nowhere'])
        ->and($facts->redirectedLinks)->toBe(['/old'])
        ->and($facts->ogImage)->toBe('https://example.test/og/about.png')
        ->and($facts->jsonLd)->toBe(2)
        ->and($facts->jsonLdErrors)->toHaveCount(1);
});

test('title and description length use the thresholds from the settings', function () {
    $site = new SiteFacts(new ReportSettings(['title_min' => 10, 'title_max' => 20, 'description_min' => 5, 'description_max' => 10]));

    expect(verdict(TitleLength::class, ['title' => null], $site))->toBe('fail')
        ->and(verdict(TitleLength::class, ['title' => 'Short'], $site))->toBe('warn')
        ->and(verdict(TitleLength::class, ['title' => 'Just the right'], $site))->toBe('pass')
        ->and(verdict(TitleLength::class, ['title' => str_repeat('x', 21)], $site))->toBe('warn')
        ->and(verdict(DescriptionLength::class, ['description' => null], $site))->toBe('fail')
        ->and(verdict(DescriptionLength::class, ['description' => 'abc'], $site))->toBe('warn')
        ->and(verdict(DescriptionLength::class, ['description' => 'abcdefg'], $site))->toBe('pass')
        ->and(verdict(DescriptionLength::class, ['description' => str_repeat('x', 11)], $site))->toBe('warn');
});

test('titles and descriptions must differ from every other page’s, ignoring case', function () {
    $site = new SiteFacts(new ReportSettings([]));
    $site->add('https://example.test/a', new PageFacts(title: 'Hello', description: 'Same words'));
    $site->add('https://example.test/b', new PageFacts(title: 'HELLO', description: 'Other words'));
    $site->add('https://example.test/c', new PageFacts(title: 'Unique', description: 'same WORDS'));

    expect(verdict(TitleUnique::class, ['title' => 'Hello'], $site, 'https://example.test/a'))->toBe('fail')
        ->and(resultText(app(TitleUnique::class)->check('https://example.test/a', new PageFacts(title: 'Hello'), $site)))->toBe('Same title as /b.')
        ->and(verdict(TitleUnique::class, ['title' => 'Unique'], $site, 'https://example.test/c'))->toBe('pass')
        ->and(verdict(DescriptionUnique::class, ['description' => 'Same words'], $site, 'https://example.test/a'))->toBe('fail')
        ->and(verdict(DescriptionUnique::class, ['description' => 'Other words'], $site, 'https://example.test/b'))->toBe('pass');
});

test('one h1', function () {
    expect(verdict(SingleH1::class, ['h1s' => []]))->toBe('fail')
        ->and(verdict(SingleH1::class, ['h1s' => ['A']]))->toBe('pass')
        ->and(verdict(SingleH1::class, ['h1s' => ['A', 'B']]))->toBe('warn');
});

test('a canonical link, as a full address', function () {
    expect(verdict(Canonical::class, ['canonical' => null]))->toBe('fail')
        ->and(verdict(Canonical::class, ['canonical' => '/page']))->toBe('fail')
        ->and(verdict(Canonical::class, ['canonical' => 'https://example.test/page']))->toBe('pass')
        ->and(verdict(Canonical::class, ['canonical' => 'https://original.test/piece']))->toBe('pass');
});

test('a page the sitemap lists must not say noindex, and the check runs on hidden pages too', function () {
    expect(verdict(NoindexInSitemap::class, ['robots' => 'noindex, follow', 'inSitemap' => true]))->toBe('fail')
        ->and(verdict(NoindexInSitemap::class, ['robots' => 'noindex, follow', 'inSitemap' => false]))->toBe('pass')
        ->and(verdict(NoindexInSitemap::class, ['robots' => 'max-snippet:-1', 'inSitemap' => true]))->toBe('pass')
        ->and(app(NoindexInSitemap::class)->appliesToNoindex())->toBeTrue()
        ->and(app(TitleLength::class)->appliesToNoindex())->toBeFalse();
});

test('image alt text: a few missing warns, most missing fails', function () {
    expect(verdict(ImageAlt::class, ['images' => 0, 'imagesWithoutAlt' => 0]))->toBe('pass')
        ->and(verdict(ImageAlt::class, ['images' => 4, 'imagesWithoutAlt' => 1]))->toBe('warn')
        ->and(verdict(ImageAlt::class, ['images' => 4, 'imagesWithoutAlt' => 3]))->toBe('fail');
});

test('links within the site: broken fails, through a redirect warns', function () {
    expect(verdict(BrokenLinks::class, []))->toBe('pass')
        ->and(verdict(BrokenLinks::class, ['redirectedLinks' => ['/old']]))->toBe('warn')
        ->and(verdict(BrokenLinks::class, ['brokenLinks' => ['/gone'], 'redirectedLinks' => ['/old']]))->toBe('fail');
});

test('a share image and structured data', function () {
    expect(verdict(OgImage::class, ['ogImage' => null]))->toBe('fail')
        ->and(verdict(OgImage::class, ['ogImage' => 'https://example.test/og.png']))->toBe('pass')
        ->and(verdict(JsonLd::class, ['jsonLd' => 0]))->toBe('warn')
        ->and(verdict(JsonLd::class, ['jsonLd' => 1]))->toBe('pass')
        ->and(verdict(JsonLd::class, ['jsonLd' => 1, 'jsonLdErrors' => ['Block 1: Syntax error']]))->toBe('fail');
});

test('the inspector keeps every link to this site and to others', function () {
    entryIn('pages', 'about');

    $facts = app(HtmlInspector::class)->inspect(<<<'HTML'
        <html><head><title>T</title></head><body>
        <a href="/about/">About</a><a href="https://example.test/">Home</a><a href="/about#team">Team</a>
        <a href="https://other.test/x#top">Other</a><a href="mailto:a@b.c">Mail</a>
        </body></html>
        HTML);

    expect($facts->internalLinks)->toBe(['/about', '/'])
        ->and($facts->externalLinks)->toBe(['https://other.test/x']);
});

test('a page in the sitemap that no other page links to is flagged; home is not', function () {
    $site = new SiteFacts(new ReportSettings([]));
    $site->add('https://example.test/', new PageFacts(internalLinks: ['/', '/linked']));
    $site->add('https://example.test/self', new PageFacts(internalLinks: ['/self']));

    expect(verdict(OrphanPages::class, ['inSitemap' => true], $site, 'https://example.test/linked'))->toBe('pass')
        ->and(verdict(OrphanPages::class, ['inSitemap' => true], $site, 'https://example.test/self'))->toBe('warn')
        ->and(verdict(OrphanPages::class, ['inSitemap' => true], $site, 'https://example.test/'))->toBe('pass')
        ->and(verdict(OrphanPages::class, ['inSitemap' => false], $site, 'https://example.test/hidden'))->toBe('pass');
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
    $site = new SiteFacts(new ReportSettings(['title_min' => 10, 'title_max' => 20]));
    $result = app(TitleLength::class)->check('https://example.test/page', new PageFacts(title: 'Short'), $site);

    expect($result->toArray())->toBe(['status' => 'warn', 'message' => 'seo::reports.messages.title_short', 'params' => ['count' => 5, 'min' => 10, 'max' => 20]])
        ->and(resultText($result))->toBe('5 characters; aim for 10–20. Short titles waste the space search results give them.')
        ->and(resultText(app(TitleLength::class)->check('https://example.test/page', new PageFacts(title: 'A'), $site)))->toStartWith('1 character;')
        ->and(app(OgImage::class)->check('https://example.test/page', new PageFacts, $site)->toArray())->toBe(['status' => 'fail', 'message' => 'seo::reports.messages.og_image_missing']);
});

test('a long list of pages ends with how many more there are', function () {
    $site = new SiteFacts(new ReportSettings([]));
    foreach (['a', 'b', 'c', 'd', 'e'] as $slug) {
        $site->add("https://example.test/{$slug}", new PageFacts(title: 'Same'));
    }
    $links = ['/1', '/2', '/3', '/4', '/5', '/6'];

    expect(resultText(app(TitleUnique::class)->check('https://example.test/a', new PageFacts(title: 'Same'), $site)))->toBe('Same title as /b, /c, /d and 1 more.')
        ->and(resultText(app(BrokenLinks::class)->check('https://example.test/a', new PageFacts(brokenLinks: $links), $site)))
        ->toBe('Links to pages that don’t exist: /1, /2, /3, /4, /5 and 1 more.')
        ->and(resultText(app(BrokenLinks::class)->check('https://example.test/a', new PageFacts(redirectedLinks: ['/old']), $site)))
        ->toBe('Links that go through a redirect (link to the new address instead): /old.');
});

test('every built-in check’s name and message has English words', function () {
    $site = new SiteFacts(new ReportSettings([]));
    $site->add('https://example.test/b', new PageFacts(title: 'T', description: 'D'));
    $page = new PageFacts(title: 'T', description: 'D', h1s: ['A', 'B'], canonical: '/x', robots: 'noindex', inSitemap: true, images: 2, imagesWithoutAlt: 1,
        brokenLinks: ['/gone'], jsonLdErrors: ['Syntax error'], brokenExternalLinks: ['https://gone.test/']);

    foreach (Runner::RULES as $class) {
        $rule = app($class);
        $result = $rule->check('https://example.test/a', $page, $site);

        expect(Lang::has($rule->label()))->toBeTrue($class)
            ->and($result->message === '' || Lang::has($result->message))->toBeTrue($class.': '.$result->message);
    }
});
