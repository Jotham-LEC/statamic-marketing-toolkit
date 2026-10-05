<?php

use JothamLec\Seo\Redirects\Redirect;
use JothamLec\Seo\Reports\HtmlInspector;
use JothamLec\Seo\Reports\PageFacts;
use JothamLec\Seo\Reports\ReportSettings;
use JothamLec\Seo\Reports\Rules\BrokenLinks;
use JothamLec\Seo\Reports\Rules\Canonical;
use JothamLec\Seo\Reports\Rules\DescriptionLength;
use JothamLec\Seo\Reports\Rules\DescriptionUnique;
use JothamLec\Seo\Reports\Rules\ImageAlt;
use JothamLec\Seo\Reports\Rules\JsonLd;
use JothamLec\Seo\Reports\Rules\NoindexInSitemap;
use JothamLec\Seo\Reports\Rules\OgImage;
use JothamLec\Seo\Reports\Rules\SingleH1;
use JothamLec\Seo\Reports\Rules\TitleLength;
use JothamLec\Seo\Reports\Rules\TitleUnique;
use JothamLec\Seo\Reports\SiteFacts;

/**
 * @param  array<string, mixed>  $facts
 */
function verdict(string $rule, array $facts, ?SiteFacts $site = null, string $url = 'https://example.test/page'): string
{
    $result = app($rule)->check($url, new PageFacts(...$facts), $site ?? new SiteFacts(new ReportSettings([])));

    return $result->status;
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
        ->and(app(TitleUnique::class)->check('https://example.test/a', new PageFacts(title: 'Hello'), $site)->message)->toBe('Same title as /b.')
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
