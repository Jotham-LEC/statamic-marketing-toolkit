<?php

namespace JothamLec\MarketingToolkit\Reports;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\View\Cascade;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders a page in this process, the way Statamic's front end would answer a
 * visit, but without an HTTP request. It makes a fresh request object, points
 * Statamic's cascade at it, takes the content's own response, and then puts
 * everything back.
 *
 * It switches `noindex_outside_production` off while rendering, so a report run
 * on a local or staging copy sees the robots tags that production would print.
 *
 * The page renders as a visitor sees it, because a report started from the
 * control panel runs while someone is signed in, and a page could show them more
 * (such as an `{{ if logged_in }}` block or the toolbar) than a visitor would get.
 */
class Renderer
{
    /**
     * A page that throws is stored with a generic message and the exception's
     * class, not its text, because the report is shown to anyone who may view
     * reports, and a query exception's text holds SQL and connection details.
     * The full error goes to the log.
     */
    public function render(Entry|Term $content): RenderedPage
    {
        $request = Request::create((string) $content->absoluteUrl(), 'GET', server: ['HTTP_USER_AGENT' => 'jotham-lec/statamic-marketing-toolkit report']);
        $previous = app('request');
        $cascade = app(Cascade::class);
        $noindex = config('marketing-toolkit.robots.noindex_outside_production');

        app()->instance('request', $request);
        $cascade->withRequest($request);
        config(['marketing-toolkit.robots.noindex_outside_production' => false]);

        // Outside a web request (the console or a queue worker), no middleware shares the
        // validation errors that views expect, so this shares an empty bag, as ShareErrorsFromSession does.
        View::share('errors', View::shared('errors') ?? new ViewErrorBag);

        $signedIn = $this->signOut();

        try {
            $response = $content->toResponse($request);

            return new RenderedPage($response->getStatusCode(), (string) $response->getContent());
        } catch (HttpExceptionInterface $e) {
            return new RenderedPage($e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return new RenderedPage(500, error: 'marketing-toolkit::reports.messages.render_failed', exception: class_basename($e));
        } finally {
            app()->instance('request', $previous);
            $cascade->withRequest($previous);
            config(['marketing-toolkit.robots.noindex_outside_production' => $noindex]);
            $this->signBackIn($signedIn);
        }
    }

    /**
     * Forgets the signed-in user of each guard that the site's pages may ask
     * about, for as long as the render lasts.
     *
     * @return array<string, Authenticatable> the forgotten users, keyed by guard
     */
    private function signOut(): array
    {
        $guards = array_unique(array_filter([config('auth.defaults.guard'), config('statamic.users.guards.web'), config('statamic.users.guards.cp')]));
        $users = [];

        foreach ($guards as $name) {
            $guard = Auth::guard($name);

            if ($guard->hasUser() && method_exists($guard, 'forgetUser')) {
                $users[$name] = $guard->user();
                $guard->forgetUser();
            }
        }

        return $users;
    }

    /**
     * @param  array<string, Authenticatable>  $users
     */
    private function signBackIn(array $users): void
    {
        foreach ($users as $name => $user) {
            Auth::guard($name)->setUser($user);
        }
    }
}
