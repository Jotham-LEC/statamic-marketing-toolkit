<?php

use JothamLec\MarketingToolkit\ServiceProvider;
use JothamLec\MarketingToolkit\Support\Config;

test('a site\'s keyed arrays merge into the defaults at every depth', function () {
    $merged = Config::merge(
        ['og' => ['enabled' => true, 'max_age' => 60, 'templates' => ['default' => 'A']]],
        ['og' => ['templates' => ['card' => 'B']]],
    );

    expect($merged)->toBe(['og' => ['enabled' => true, 'max_age' => 60, 'templates' => ['default' => 'A', 'card' => 'B']]]);
});

test('a site\'s list or value replaces the default whole', function () {
    $merged = Config::merge(
        ['not_found' => ['ignore_paths' => ['*.php', '/wp-*'], 'max_rows' => 1000], 'collections' => []],
        ['not_found' => ['ignore_paths' => ['/private/*'], 'max_rows' => 50], 'collections' => ['blog' => ['og_type' => 'article']]],
    );

    expect($merged)->toBe(['not_found' => ['ignore_paths' => ['/private/*'], 'max_rows' => 50], 'collections' => ['blog' => ['og_type' => 'article']]]);
});

test('a published config/seo.php that sets one nested key keeps the addon\'s others', function () {
    config(['seo' => ['og' => ['templates' => ['card' => 'B']]]]);

    $provider = app()->getProvider(ServiceProvider::class);
    (fn () => $this->mergeConfigFrom(__DIR__.'/../../config/seo.php', 'seo'))->call($provider);

    expect(config('seo.og.enabled'))->toBeTrue()
        ->and(config('seo.og.templates'))->toHaveKeys(['default', 'card'])
        ->and(config('seo.sitemap.enabled'))->toBeTrue();
});
