<?php

namespace JothamLec\MarketingToolkit\Http\Controllers\CP;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use JothamLec\MarketingToolkit\Cp\Listing;
use JothamLec\MarketingToolkit\Cp\RecordActions;
use JothamLec\MarketingToolkit\NotFound\MissingPath;
use JothamLec\MarketingToolkit\NotFound\Recorder;
use JothamLec\MarketingToolkit\Support\Features;
use JothamLec\MarketingToolkit\Support\Sites;
use Statamic\Facades\Site;

/**
 * Shows Marketing → 404s, the missing paths that visitors hit, most recent
 * first, each with a "Create redirect" action. On a multi-site install, it
 * shows those of the selected site.
 */
final class NotFoundController
{
    public function index(): Response
    {
        return Inertia::render('marketing-toolkit::NotFound', [
            'listingUrl' => cp_route('mt.404s.listing'),
            'actionUrl' => cp_route('mt.actions.run'),
            'enabled' => Features::on('not_found'),
            'maxRows' => (int) config('marketing-toolkit.not_found.max_rows'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function listing(Request $request): array
    {
        $sites = Sites::options();
        // The actions are the same on every row, because they depend only on the kind of row and the user.
        $actions = RecordActions::for(collect([new MissingPath]), ['type' => '404s']);

        return Listing::respond(
            MissingPath::query()->shownOn(Site::selected()->handle()),
            $request,
            [
                'last_seen_at' => __('marketing-toolkit::cp.listing.last_seen'), 'path' => __('marketing-toolkit::cp.listing.path'),
                ...(Sites::multiple() ? ['site' => __('marketing-toolkit::cp.listing.site')] : []),
                'hits' => __('marketing-toolkit::cp.listing.hits'), 'first_seen_at' => __('marketing-toolkit::cp.listing.first_seen'),
                'referrer' => __('marketing-toolkit::cp.listing.last_linked_from'),
            ],
            ['path', 'referrer'],
            fn (Collection $rows) => $rows->map(fn (MissingPath $row) => [
                'id' => $row->id,
                // A row has no site when it was logged before there was more than one site.
                'site' => $row->site === null ? '—' : ($sites[$row->site] ?? $row->site),
                'path' => $row->path,
                'hits' => $row->hits,
                'referrer' => Recorder::webAddress($row->referrer),
                'first_seen_at' => $row->first_seen_at->toIso8601String(),
                'last_seen_at' => $row->last_seen_at->toIso8601String(),
                'actions' => $actions,
            ]),
            defaultOrder: 'desc',
        );
    }
}
