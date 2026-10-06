<?php

namespace JothamLec\Seo\Http\Controllers\CP;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use JothamLec\Seo\Cp\Listing;
use JothamLec\Seo\NotFound\MissingPath;
use JothamLec\Seo\NotFound\Recorder;
use JothamLec\Seo\Support\Sites;
use Statamic\Facades\Action;
use Statamic\Facades\Site;
use Statamic\Facades\User;

/**
 * Tools → SEO → 404s: the missing paths visitors hit, most recent first,
 * each with a "Create redirect" action. On a multi-site install, those of
 * the selected site.
 */
class NotFoundController
{
    public function index(): Response
    {
        $this->authorize();

        return Inertia::render('seo::NotFound', [
            'listingUrl' => cp_route('seo.404s.listing'),
            'actionUrl' => cp_route('seo.actions.run'),
            'enabled' => (bool) config('seo.not_found.enabled'),
            'maxRows' => (int) config('seo.not_found.max_rows'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function listing(Request $request): array
    {
        $this->authorize();

        $sites = Sites::options();

        return Listing::respond(
            MissingPath::query()->shownOn(Site::selected()->handle()),
            $request,
            [
                'last_seen_at' => 'Last seen', 'path' => 'Path',
                // The site column only where there is more than one.
                ...(Sites::multiple() ? ['site' => 'Site'] : []),
                'hits' => 'Hits', 'first_seen_at' => 'First seen', 'referrer' => 'Last linked from',
            ],
            ['path', 'referrer'],
            fn (MissingPath $row) => [
                'id' => $row->id,
                // None: logged before there was more than one site.
                'site' => $row->site === null ? '—' : ($sites[$row->site] ?? $row->site),
                'path' => $row->path,
                'hits' => $row->hits,
                'referrer' => Recorder::webAddress($row->referrer),
                'first_seen_at' => $row->first_seen_at->toIso8601String(),
                'last_seen_at' => $row->last_seen_at->toIso8601String(),
                'actions' => Action::for($row, ['type' => '404s']),
            ],
            defaultOrder: 'desc',
        );
    }

    private function authorize(): void
    {
        abort_unless(User::current()?->can('view seo'), 403);
    }
}
