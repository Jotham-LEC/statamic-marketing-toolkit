<?php

use Illuminate\Http\Request;
use JothamLec\Seo\Http\Middleware\TrailingSlash;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel's test client trims a trailing slash from the URL, so the
 * middleware is called with a request built by hand.
 */
function throughTrailingSlash(string $uri, string $method = 'GET'): Response
{
    return (new TrailingSlash)->handle(Request::create('https://example.test'.$uri, $method), fn () => response('page'));
}

test('left alone by default', function () {
    expect(throughTrailingSlash('/about/')->getContent())->toBe('page');
});

test('"remove" sends the slashed form to the bare one, keeping the query', function () {
    config(['seo.trailing_slash' => 'remove']);

    $response = throughTrailingSlash('/about/?ref=x');

    expect($response->getStatusCode())->toBe(301)
        ->and($response->headers->get('Location'))->toBe('https://example.test/about?ref=x')
        ->and(throughTrailingSlash('/about')->getContent())->toBe('page');
});

test('"add" sends the bare form to the slashed one, but not files, the control panel, images or forms', function () {
    config(['seo.trailing_slash' => 'add']);

    expect(throughTrailingSlash('/about')->headers->get('Location'))->toBe('https://example.test/about/');

    foreach (['/sitemap.xml', '/cp/entries', '/img/x/y', '/'] as $path) {
        expect(throughTrailingSlash($path)->getContent())->toBe('page');
    }

    expect(throughTrailingSlash('/contact', 'POST')->getContent())->toBe('page');
});
