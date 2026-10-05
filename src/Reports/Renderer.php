<?php

namespace JothamLec\Seo\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\View\Cascade;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders a page in this process, the way Statamic's front end would answer a
 * visit, without an HTTP request: a fresh request object, Statamic's cascade
 * pointed at it, the content's own response, then everything put back.
 *
 * While rendering, `noindex_outside_production` is off, so a report run on a
 * local or staging copy sees the robots tags production would print.
 */
class Renderer
{
    /**
     * @return array{status: int, html: string, error: ?string}
     */
    public function render(Entry|Term $content): array
    {
        $request = Request::create((string) $content->absoluteUrl(), 'GET', server: ['HTTP_USER_AGENT' => 'jotham-lec/statamic-seo report']);
        $previous = app('request');
        $cascade = app(Cascade::class);
        $noindex = config('seo.robots.noindex_outside_production');

        app()->instance('request', $request);
        $cascade->withRequest($request);
        config(['seo.robots.noindex_outside_production' => false]);

        // Outside a web request (the console, a queue worker) no middleware shares the
        // validation errors views expect; an empty bag, as ShareErrorsFromSession gives.
        View::share('errors', View::shared('errors') ?? new ViewErrorBag);

        try {
            $response = $content->toResponse($request);

            return ['status' => $response->getStatusCode(), 'html' => (string) $response->getContent(), 'error' => null];
        } catch (HttpExceptionInterface $e) {
            return ['status' => $e->getStatusCode(), 'html' => '', 'error' => null];
        } catch (Throwable $e) {
            report($e);

            return ['status' => 500, 'html' => '', 'error' => class_basename($e).': '.$e->getMessage()];
        } finally {
            app()->instance('request', $previous);
            $cascade->withRequest($previous);
            config(['seo.robots.noindex_outside_production' => $noindex]);
        }
    }
}
