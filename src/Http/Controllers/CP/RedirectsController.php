<?php

namespace JothamLec\MarketingToolkit\Http\Controllers\CP;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use JothamLec\MarketingToolkit\Cp\Listing;
use JothamLec\MarketingToolkit\Cp\RecordActions;
use JothamLec\MarketingToolkit\Http\Requests\SaveRedirect;
use JothamLec\MarketingToolkit\Preview\Draft;
use JothamLec\MarketingToolkit\Redirects\AutoRedirects;
use JothamLec\MarketingToolkit\Redirects\Campaign;
use JothamLec\MarketingToolkit\Redirects\Csv;
use JothamLec\MarketingToolkit\Redirects\Redirect;
use JothamLec\MarketingToolkit\Support\Features;
use JothamLec\MarketingToolkit\Support\Permissions;
use JothamLec\MarketingToolkit\Support\Sites;
use JothamLec\MarketingToolkit\Support\Uris;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Taxonomies\Term as TermContract;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Marketing → Redirects: the list, the create and edit forms (Statamic's
 * publish form, on a blueprint defined here), CSV in and out, and the two
 * endpoints behind the "add a redirect?" question when content is saved.
 */
final class RedirectsController
{
    public function index(): Response
    {
        return Inertia::render('marketing-toolkit::Redirects', [
            'listingUrl' => cp_route('mt.redirects.listing'),
            'actionUrl' => cp_route('mt.actions.run'),
            'createUrl' => cp_route('mt.redirects.create'),
            'exportUrl' => cp_route('mt.redirects.export'),
            'importUrl' => cp_route('mt.redirects.import'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function listing(Request $request): array
    {
        $sites = Sites::options();
        // The same on every row: they depend on the kind of row and the user alone.
        $actions = RecordActions::for(collect([new Redirect]), ['type' => 'redirects']);

        return Listing::respond(
            Redirect::query()->accessible(),
            $request,
            [
                'source' => __('marketing-toolkit::cp.listing.from'), 'target' => __('marketing-toolkit::cp.listing.to'),
                ...(Sites::multiple() ? ['site' => __('marketing-toolkit::cp.listing.site')] : []),
                'status' => __('marketing-toolkit::cp.listing.status'), 'active' => __('marketing-toolkit::cp.listing.active'),
                'hits' => __('marketing-toolkit::cp.listing.hits'), 'last_hit_at' => __('marketing-toolkit::cp.listing.last_used'),
            ],
            ['source', 'target'],
            fn (Redirect $redirect) => [
                'id' => $redirect->id,
                'site' => $redirect->site === null ? __('marketing-toolkit::cp.listing.all_sites') : ($sites[$redirect->site] ?? $redirect->site),
                'source' => $redirect->source,
                'target' => $redirect->target,
                'status' => $redirect->status,
                'active' => $redirect->active,
                'automatic' => $redirect->automatic,
                'hits' => $redirect->hits,
                'last_hit_at' => $redirect->last_hit_at?->toIso8601String(),
                'edit_url' => cp_route('mt.redirects.edit', $redirect),
                'actions' => $actions,
            ],
        );
    }

    public function create(Request $request): Response
    {
        return $this->form(
            new Redirect([
                'source' => (string) $request->query('source', ''),
                // Without one, for every site; for someone who can't work on every site, the selected one if theirs.
                'site' => Sites::scope($request->query('site') === null ? self::defaultSite() : (string) $request->query('site')),
            ]),
            title: __('marketing-toolkit::cp.redirects.create'),
            submitUrl: cp_route('mt.redirects.store'),
            method: 'post',
        );
    }

    public function store(SaveRedirect $request): JsonResponse
    {
        $redirect = Redirect::query()->create($request->validated());

        return response()->json(['redirect' => cp_route('mt.redirects.edit', $redirect)]);
    }

    public function edit(Redirect $redirect): Response
    {
        abort_unless($redirect->isAccessible(), 404);

        return $this->form($redirect, title: $redirect->source, submitUrl: cp_route('mt.redirects.update', $redirect), method: 'patch');
    }

    public function update(SaveRedirect $request, Redirect $redirect): JsonResponse
    {
        abort_unless($redirect->isAccessible(), 404);

        // Edited by hand, it is no longer one the content made.
        $redirect->update([...$request->validated(), 'automatic' => false]);

        return response()->json(['saved' => true]);
    }

    public function export(Csv $csv): StreamedResponse
    {
        return response()->streamDownload(function () use ($csv) {
            $out = fopen('php://output', 'w');
            $csv->export($out);
            fclose($out);
        }, 'redirects-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{created: int, updated: int, errors: list<string>}
     */
    public function import(Request $request, Csv $csv): array
    {
        $request->validate(['file' => ['required', 'file', 'max:5120']]);

        return $csv->import((string) file_get_contents($request->file('file')->getRealPath()));
    }

    /**
     * Before an entry or term is saved from its form: will its address change?
     *
     * @return array{changes: bool, from?: string, to?: string}
     */
    public function check(Request $request): array
    {
        if (! Features::on('automatic_redirects') || ! User::current()?->can(Permissions::REDIRECTS)) {
            return ['changes' => false];
        }

        $stored = $this->referenced((string) $request->input('reference'));

        // Only published content gets a redirect (RedirectChangedUris), before and after the save.
        if (! $stored || ($stored instanceof EntryContract && (! $stored->published() || ! $request->boolean('values.published', true)))) {
            return ['changes' => false];
        }

        // A term without a page (no template) gets no redirect either (RedirectChangedUris).
        if ($stored instanceof TermContract && ! Uris::termHasPage($stored)) {
            return ['changes' => false];
        }

        Uris::forget();
        $from = $stored->uri();

        $draft = Draft::from([
            'blueprint' => $stored->blueprint()->fullyQualifiedHandle(),
            'reference' => $request->input('reference'),
            'site' => $stored->locale(),
            'values' => (array) $request->input('values', []),
        ]);

        Uris::forget();
        $to = $draft->uri();

        return $from && $to && Redirect::normalize($from) !== Redirect::normalize($to)
            ? ['changes' => true, 'from' => $from, 'to' => $to]
            : ['changes' => false];
    }

    /**
     * The editor's answer, read by the save that follows.
     */
    public function choice(Request $request, AutoRedirects $redirects): JsonResponse
    {
        $request->validate(['reference' => ['required', 'string'], 'create' => ['required', 'boolean']]);

        $content = $this->referenced($request->input('reference'));
        abort_unless($content !== null, 404);

        $redirects->remember((string) $content->id(), $request->boolean('create'));

        return response()->json(['saved' => true]);
    }

    /**
     * The saved entry or term a publish form's reference points at, if this user may see it.
     */
    private function referenced(string $reference): EntryContract|TermContract|null
    {
        $content = Draft::stored($reference);

        abort_if($content && User::current()?->cant('view', $content), 403);

        return $content;
    }

    /**
     * The site a new rule is for when the form names none: every site (null),
     * unless the user may not work on every site.
     */
    private static function defaultSite(): ?string
    {
        if (Sites::accessesAll()) {
            return null;
        }

        $selected = Site::selected()->handle();

        return in_array($selected, Sites::accessible(), true) ? $selected : (Sites::accessible()[0] ?? null);
    }

    private function form(Redirect $redirect, string $title, string $submitUrl, string $method): Response
    {
        $fields = SaveRedirect::blueprint()->fields()->addValues([
            ...Campaign::tags((string) $redirect->target),
            'source' => $redirect->source,
            'target' => $redirect->target,
            'status' => (string) $redirect->status,
            'active' => $redirect->active,
            'site' => $redirect->site,
        ])->preProcess();

        return Inertia::render('marketing-toolkit::RedirectForm', [
            'title' => $title,
            'blueprint' => SaveRedirect::blueprint()->toPublishArray(),
            'values' => $fields->values()->all(),
            'meta' => $fields->meta()->all(),
            'submitUrl' => $submitUrl,
            'submitMethod' => $method,
            'listingUrl' => cp_route('mt.redirects.index'),
            'stats' => $redirect->exists ? ['hits' => $redirect->hits, 'last_hit_at' => $redirect->last_hit_at?->toIso8601String(), 'automatic' => $redirect->automatic] : null,
        ]);
    }
}
