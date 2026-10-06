<?php

use Statamic\Facades\Collection;

test('llms.txt lists each collection\'s pages with their descriptions, without hidden ones', function () {
    seoGlobal(['default_description' => 'We make <b>things</b>.']);
    Collection::findByHandle('pages')->title('Pages')->save();
    entryIn('pages', 'about', ['title' => 'About [us]', 'seo' => ['description' => 'Who we are.']]);
    entryIn('pages', 'team', ['title' => 'Team']);
    entryIn('pages', 'hidden', ['seo' => ['noindex' => true]]);

    $text = $this->get('https://example.test/llms.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->assertHeaderMissing('Set-Cookie')->getContent();

    expect($text)->toStartWith("# Acme\n\n> We make things.\n")
        ->toContain("## Pages\n")
        ->toContain('- [About (us)](https://example.test/about): Who we are.')
        ->toContain('- [Team](https://example.test/team)')
        ->not->toContain('hidden');
});

test('llms.txt is cached, and forgotten when content is saved', function () {
    seoGlobal([]);
    entryIn('pages', 'about');
    $this->get('https://example.test/llms.txt')->assertSee('/about');

    entryIn('pages', 'team');

    $this->get('https://example.test/llms.txt')->assertSee('/team');
});

test('ads.txt serves the lines from SEO & brand, and nothing until there are some', function () {
    seoGlobal([]);
    $this->get('https://example.test/ads.txt')->assertNotFound();

    seoGlobal(['ads_txt' => "google.com, pub-123, DIRECT, f08c47fec0942fa0\n"]);
    $this->get('https://example.test/ads.txt')->assertOk()->assertContent("google.com, pub-123, DIRECT, f08c47fec0942fa0\n");
});
