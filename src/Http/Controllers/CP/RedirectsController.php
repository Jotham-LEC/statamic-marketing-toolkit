<?php

namespace JothamLec\Seo\Http\Controllers\CP;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use JothamLec\Seo\Cp\Listing;
use JothamLec\Seo\Preview\Draft;
use JothamLec\Seo\Redirects\AutoRedirects;
use JothamLec\Seo\Redirects\Csv;
use JothamLec\Seo\Redirects\Redirect;
use JothamLec\Seo\Support\Edition;
use JothamLec\Seo\Support\Sites;
use JothamLec\Seo\Support\Uris;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Taxonomies\Term as TermContract;
use Statamic\Facades\Action;
use Statamic\Facades\Blueprint;
use Statamic\Facades\User;
use Statamic\Fields\Blueprint as BlueprintObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tools → SEO → Redirects: the list, the create and edit forms (Statamic's
 * publish form, on a blueprint defined here), CSV in and out, and the two
 * endpoints behind the "add a redirect?" question when content is saved.
 */
class RedirectsController
{
    public function index(): Response
    {
        $this->authorize();

        return Inertia::render('seo::Redirects', [
            'listingUrl' => cp_route('seo.redirects.listing'),
            'actionUrl' => cp_route('seo.actions.run'),
            'createUrl' => cp_route('seo.redirects.create'),
            // CSV in and out is Pro: the free edition shows what it would add.
            'exportUrl' => Edition::pro() ? cp_route('seo.redirects.export') : null,
            'importUrl' => Edition::pro() ? cp_route('seo.redirects.import') : null,
            'upgradeUrl' => Edition::marketplaceUrl(),
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
            Redirect::query(),
            $request,
            [
                'source' => __('seo::cp.listing.from'), 'target' => __('seo::cp.listing.to'),
                // The site column only where there is more than one.
                ...(Sites::multiple() ? ['site' => __('seo::cp.listing.site')] : []),
                'status' => __('seo::cp.listing.status'), 'active' => __('seo::cp.listing.active'),
                'hits' => __('seo::cp.listing.hits'), 'last_hit_at' => __('seo::cp.listing.last_used'),
            ],
            ['source', 'target'],
            fn (Redirect $redirect) => [
                'id' => $redirect->id,
                'site' => $redirect->site === null ? __('seo::cp.listing.all_sites') : ($sites[$redirect->site] ?? $redirect->site),
                'source' => $redirect->source,
                'target' => $redirect->target,
                'status' => $redirect->status,
                'active' => $redirect->active,
                'automatic' => $redirect->automatic,
                'hits' => $redirect->hits,
                'last_hit_at' => $redirect->last_hit_at?->toIso8601String(),
                'edit_url' => cp_route('seo.redirects.edit', $redirect),
                'actions' => Action::for($redirect, ['type' => 'redirects']),
            ],
        );
    }

    public function create(Request $request): Response
    {
        $this->authorize();

        return $this->form(
            new Redirect([
                'source' => (string) $request->query('source', ''),
                'site' => Sites::scope($request->query('site') === null ? null : (string) $request->query('site')),
                'status' => 301,
                'active' => true,
            ]),
            title: __('seo::cp.redirects.create'),
            submitUrl: cp_route('seo.redirects.store'),
            method: 'post',
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize();

        $redirect = Redirect::query()->create($this->validated($request));

        return response()->json(['redirect' => cp_route('seo.redirects.edit', $redirect)]);
    }

    public function edit(Redirect $redirect): Response
    {
        $this->authorize();

        return $this->form($redirect, title: $redirect->source, submitUrl: cp_route('seo.redirects.update', $redirect), method: 'patch');
    }

    public function update(Request $request, Redirect $redirect): JsonResponse
    {
        $this->authorize();

        // Edited by hand, it is no longer one the content made.
        $redirect->update([...$this->validated($request, $redirect->id), 'automatic' => false]);

        return response()->json(['saved' => true]);
    }

    public function export(Csv $csv): StreamedResponse
    {
        $this->authorize();

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
        $this->authorize();

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
        if (! config('seo.redirects.automatic') || ! User::current()?->can('manage seo redirects')) {
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

        $draft = Draft::fromRequest(Request::create('/', 'POST', [
            'blueprint' => $stored->blueprint()->fullyQualifiedHandle(),
            'reference' => $request->input('reference'),
            'site' => $stored->locale(),
            'values' => (array) $request->input('values', []),
        ]));

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
        $this->authorize();
        $request->validate(['reference' => ['required', 'string'], 'create' => ['required', 'boolean']]);

        $content = $this->referenced($request->input('reference'));
        abort_unless($content !== null, 404);

        $redirects->remember((string) $content->id(), $request->boolean('create'));

        return response()->json(['saved' => true]);
    }

    private function authorize(): void
    {
        abort_unless(User::current()?->can('manage seo redirects'), 403);
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
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $values = $this->blueprint()->fields()->addValues($request->all())->process()->values()->only(['source', 'target', 'status', 'active', 'site'])->all();
        $values['status'] = (int) ($values['status'] ?? 301);
        $values['active'] = (bool) ($values['active'] ?? false);
        // Without a choice (or on a single site): every site.
        $values['site'] = is_string($values['site'] ?? null) && $values['site'] !== '' && Sites::multiple() ? $values['site'] : null;

        return Redirect::validator($values, $ignoreId)->validate();
    }

    private function form(Redirect $redirect, string $title, string $submitUrl, string $method): Response
    {
        $fields = $this->blueprint()->fields()->addValues([
            'source' => $redirect->source,
            'target' => $redirect->target,
            'status' => (string) $redirect->status,
            'active' => $redirect->active,
            'site' => $redirect->site,
        ])->preProcess();

        return Inertia::render('seo::RedirectForm', [
            'title' => $title,
            'blueprint' => $this->blueprint()->toPublishArray(),
            'values' => $fields->values()->all(),
            'meta' => $fields->meta()->all(),
            'submitUrl' => $submitUrl,
            'submitMethod' => $method,
            'listingUrl' => cp_route('seo.redirects.index'),
            'stats' => $redirect->exists ? ['hits' => $redirect->hits, 'last_hit_at' => $redirect->last_hit_at?->toIso8601String(), 'automatic' => $redirect->automatic] : null,
        ]);
    }

    private function blueprint(): BlueprintObject
    {
        return Blueprint::make('seo_redirect')->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'source', 'field' => [
                'type' => 'text', 'display' => __('seo::cp.redirect_form.source'),
                'instructions' => __('seo::cp.redirect_form.source_instructions'),
            ]],
            ['handle' => 'target', 'field' => [
                'type' => 'text', 'display' => __('seo::cp.redirect_form.target'),
                'instructions' => __('seo::cp.redirect_form.target_instructions'),
            ]],
            ['handle' => 'status', 'field' => [
                'type' => 'button_group', 'display' => __('seo::cp.redirect_form.status'), 'width' => 66, 'default' => '301',
                'options' => [
                    '301' => __('seo::cp.redirect_form.status_301'),
                    '302' => __('seo::cp.redirect_form.status_302'),
                    '410' => __('seo::cp.redirect_form.status_410'),
                ],
            ]],
            ['handle' => 'active', 'field' => ['type' => 'toggle', 'display' => __('seo::cp.redirect_form.active'), 'width' => 33, 'default' => true]],
            // Only where there is more than one site to choose from.
            ...(Sites::multiple() ? [['handle' => 'site', 'field' => [
                'type' => 'select', 'display' => __('seo::cp.redirect_form.site'), 'options' => Sites::options(), 'clearable' => true,
                'placeholder' => __('seo::cp.redirect_form.all_sites'), 'instructions' => __('seo::cp.redirect_form.site_instructions'),
            ]]] : []),
        ]]]]]]);
    }
}
