<?php

use JothamLec\Seo\Context;
use JothamLec\Seo\SiteSeo;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;

beforeEach(fn () => seoGlobal(['default_description' => 'The default.']));

describe('title', function () {
    test('a page is "{title} · {site}"; home is the site name', function () {
        expect(metaFor(entryIn('pages', 'about'))->title)->toBe('About · Acme')
            ->and(metaFor(entryIn('home', 'home'))->title)->toBe('Acme');
    });

    test('the site name is dropped when it would push the title past the limit', function () {
        config(['seo.title.max' => 20]);

        expect(metaFor(entryIn('pages', 'a-rather-long-page-title'))->title)->toBe('A rather long page title');
    });

    test('an SEO title replaces the whole title; an empty one counts as not set', function () {
        expect(metaFor(entryIn('pages', 'about', ['seo' => ['title' => 'Who we are']]))->title)->toBe('Who we are')
            ->and(metaFor(entryIn('pages', 'team', ['seo' => ['title' => '']]))->title)->toBe('Team · Acme');
    });

    test('a later page of a listing says which page it is', function () {
        expect(metaFor(entryIn('pages', 'news'), '/news?page=3')->title)->toBe('News · Acme · Page 3');
    });
});

describe('description', function () {
    test('falls back from the SEO field to the description to the first paragraph to the site default', function () {
        expect(metaFor(entryIn('pages', 'a', ['seo' => ['description' => 'Override.'], 'description' => 'Own.']))->description)->toBe('Override.')
            ->and(metaFor(entryIn('pages', 'b', ['description' => 'Own.']))->description)->toBe('Own.')
            ->and(metaFor(entryIn('pages', 'c', ['content' => "First para.\n\nSecond."]))->description)->toBe('First para.')
            ->and(metaFor(entryIn('pages', 'd'))->description)->toBe('The default.');
    });

    test('an empty first paragraph is passed over, and a long text is cut on a word', function () {
        config(['seo.description.length' => 30]);

        $entry = entryIn('pages', 'long', ['content' => "&nbsp;\n\nThe real opening paragraph runs on for a while."]);

        expect(metaFor($entry)->description)->toBe('The real opening paragraph…');
    });
});

describe('canonical and robots', function () {
    test('the canonical is the page itself, keeps ?page and drops other query strings', function () {
        $entry = entryIn('pages', 'news');

        expect(metaFor($entry, '/news?utm_source=x')->canonical)->toBe('https://example.test/news')
            ->and(metaFor($entry, '/news?page=2')->canonical)->toBe('https://example.test/news?page=2');
    });

    test('a republished piece names the original, and false prints none', function () {
        expect(metaFor(entryIn('pages', 'reprint', ['seo' => ['canonical' => 'https://times.example/x']]))->canonical)->toBe('https://times.example/x')
            ->and(metaFor(entryIn('pages', 'plain'), '/plain', ['canonical' => false])->canonical)->toBeNull();
    });

    test('robots say index in production and noindex elsewhere, on request, on filters and on errors', function () {
        config(['seo.robots.noindex_params' => ['sort']]);
        $entry = entryIn('pages', 'news');

        expect(metaFor($entry)->robots)->toBe(config('seo.robots.default'))
            ->and(metaFor(entryIn('pages', 'hidden', ['seo' => ['noindex' => true]]))->robots)->toBe('noindex, follow')
            ->and(metaFor($entry, '/news?sort=new')->robots)->toBe('noindex, follow')
            ->and(metaFor(null, '/missing', status: 404)->robots)->toBe('noindex, follow');

        $this->app['env'] = 'staging';

        expect(metaFor($entry)->robots)->toBe('noindex, follow');
    });
});

describe('open graph', function () {
    test('a dated piece in an article collection carries its dates', function () {
        config(['seo.collections.essays' => ['og_type' => 'article']]);

        $meta = metaFor(entryIn('essays', 'first', [], '2026-01-02'));

        expect($meta->ogType)->toBe('article')
            ->and($meta->published)->toStartWith('2026-01-02T')
            ->and($meta->modified)->not->toBeNull();
    });

    test('verification codes become meta tags', function () {
        GlobalSet::findByHandle('seo')->in('default')->set('google_verification', 'abc123')->save();

        expect(metaFor(entryIn('pages', 'about'))->verification)->toBe(['google-site-verification' => 'abc123']);
    });
});

describe('json-ld', function () {
    test('the graph has the site, the publisher, the page and its breadcrumbs', function () {
        entryIn('pages', 'essays');
        config(['seo.collections.essays' => ['schema' => 'Article']]);

        $graph = collect(metaFor(entryIn('essays', 'first', [], '2026-01-02'))->graph)->keyBy('@type');

        expect($graph->keys()->all())->toBe(['WebSite', 'Organization', 'WebPage', 'BreadcrumbList', 'Article'])
            ->and(collect($graph['BreadcrumbList']['itemListElement'])->pluck('name')->all())->toBe(['Acme', 'Essays', 'First'])
            ->and($graph['Article']['author'])->toBe(['@id' => 'https://example.test/#publisher']);
    });

    test('a draft ancestor is left out of the breadcrumbs: its title and address aren\'t public yet', function () {
        entryIn('pages', 'essays')->published(false)->save();

        $crumbs = nodeOf(metaFor(entryIn('essays', 'first', [], '2026-01-02')), 'BreadcrumbList')['itemListElement'];

        expect(collect($crumbs)->pluck('name')->all())->toBe(['Acme', 'First'])
            ->and(collect($crumbs)->pluck('position')->all())->toBe([1, 2]);
    });

    test('an FAQ grid becomes an FAQPage with the answers as the page shows them', function () {
        config(['seo.collections.pages' => ['faq_field' => 'faqs']]);

        $graph = collect(metaFor(entryIn('pages', 'help', ['faqs' => [['question' => 'Why?', 'answer' => 'Because **so**.']]]))->graph)->keyBy('@type');

        expect($graph['FAQPage']['mainEntity'][0]['acceptedAnswer']['text'])->toBe('<p>Because <strong>so</strong>.</p>');
    });

    test('extra JSON-LD typed in the entry is added; text that does not parse is ignored', function () {
        $types = fn ($entry) => collect(metaFor($entry)->graph)->pluck('@type')->all();

        expect($types(entryIn('pages', 'event', ['seo' => ['json_ld' => '{"@type": "Event", "name": "Launch"}']])))->toContain('Event')
            ->and($types(entryIn('pages', 'broken', ['seo' => ['json_ld' => '{not json']])))->toBe(['WebSite', 'Organization', 'WebPage', 'BreadcrumbList']);
    });

    test('an error page has no graph', function () {
        expect(metaFor(null, '/missing', status: 404)->graph)->toBe([]);
    });
});

test('the tag prints the tags, escaped, with JSON-LD that cannot close its script', function () {
    entryIn('pages', 'quotes', ['title' => 'Say "hi" </script><b>&']);
    $html = renderAt('/quotes', '<s:seo:meta :entry="$entry" />', ['entry' => Entry::findByUri('/quotes')]);

    expect($html)->toContain('<title>Say &quot;hi&quot; &lt;/script&gt;&lt;b&gt;&amp; · Acme</title>')
        ->toContain('<link rel="canonical" href="https://example.test/quotes">')
        ->toContain('<meta property="og:image" content="https://example.test/og/quotes.png?v=')
        ->not->toContain('</script><b>');

    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $match);

    expect(json_decode($match[1], true)['@graph'][2]['name'])->toBe('Say "hi" </script><b>&');
});

test('a page without an entry passes what it knows', function () {
    expect(renderAt('/contact-form', '<s:seo:meta title="Contact" description="Write to us." />'))
        ->toContain('<title>Contact · Acme</title>')
        ->toContain('<meta name="description" content="Write to us.">')
        ->toContain('<link rel="canonical" href="https://example.test/contact-form">');
});

class CountingSeo extends SiteSeo
{
    /** @var array<string, int> */
    public static array $calls = [];

    protected function contentDescription(Context $context): ?string
    {
        self::$calls['body'] = (self::$calls['body'] ?? 0) + 1;

        return parent::contentDescription($context);
    }

    public function generatedImageUrl(Statamic\Contracts\Entries\Entry $entry): ?string
    {
        self::$calls['card'] = (self::$calls['card'] ?? 0) + 1;

        return parent::generatedImageUrl($entry);
    }
}

test('the tag works out the description and the share image once per page', function () {
    config(['seo.class' => CountingSeo::class, 'seo.collections.essays.schema' => 'Article']);
    $entry = entryIn('essays', 'long-read', ['content' => "The first paragraph.\n\nThe second."], '2026-01-02');
    CountingSeo::$calls = [];

    $html = renderAt('/essays/long-read', '<s:seo:meta :entry="$entry" />', ['entry' => $entry]);

    expect($html)->toContain('The first paragraph.')->and(CountingSeo::$calls)->toBe(['body' => 1, 'card' => 1]);
});

test('one rules object asked about page after page gives each its own values', function () {
    $seo = app(SiteSeo::class);
    $entries = [entryIn('pages', 'one', ['description' => 'First.']), entryIn('pages', 'two', ['description' => 'Second.'])];

    $descriptions = array_map(fn ($entry) => $seo->description(Context::make($entry)), $entries);

    expect($descriptions)->toBe(['First.', 'Second.']);
});
