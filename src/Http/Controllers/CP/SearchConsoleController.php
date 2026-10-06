<?php

namespace JothamLec\MarketingToolkit\Http\Controllers\CP;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use JothamLec\MarketingToolkit\SearchConsole\Client;
use JothamLec\MarketingToolkit\SearchConsole\Connection;
use JothamLec\MarketingToolkit\SearchConsole\Importer;
use JothamLec\MarketingToolkit\SearchConsole\SearchStat;
use JothamLec\MarketingToolkit\Support\Edition;
use JothamLec\MarketingToolkit\Support\Sites;
use Statamic\Facades\Addon;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Throwable;

/**
 * Tools → SEO → Search Console, and setting it up there: the service account key, the
 * property, a check that Google lets the key read it, and the first import.
 * For whoever may change the addon's settings; `.env` values win and can't
 * be changed here. The property, the check and the import are of the site
 * selected in the control panel; the key serves every site.
 */
class SearchConsoleController
{
    public function __construct(private Connection $connection) {}

    /**
     * Tools → SEO → Search Console: the steps to connect it, then where the
     * connection stands: the key, each site's property, the last import.
     * Anyone who may view SEO sees it, for the sites they may work on; only
     * whoever may change the addon's settings gets the steps, the buttons and
     * the properties (the others, whether each site is connected).
     */
    public function index(Client $client): Response
    {
        $site = Site::selected()->handle();
        $stats = fn () => SearchStat::query()->shownOn($site);

        return Inertia::render('seo::SearchConsole', [
            'setup' => $this->setup($client, $site),
            'sites' => Sites::multiple() ? Site::authorized()->map(fn ($each) => [
                'name' => (string) $each->name(),
                'property' => $this->canSetUp() ? $this->connection->property($each->handle()) : null,
                'connected' => $client->configured($each->handle()),
                'selected' => $each->handle() === $site,
            ])->values()->all() : [],
            'imported' => [
                'fetched_at' => $stats()->latest('fetched_at')->first()?->fetched_at?->toIso8601String(),
                'pages' => $stats()->count(),
            ],
            'overviewUrl' => cp_route('seo.index'),
        ]);
    }

    /**
     * Where the setup stands for the selected site. Without permission to
     * change it, only whether it is connected and where the values come from.
     *
     * @return array<string, mixed>
     */
    private function setup(Client $client, string $site): array
    {
        $canSetUp = $this->canSetUp();

        return [
            'configured' => $client->configured($site),
            'can_set_up' => $canSetUp,
            'email' => $canSetUp ? $this->connection->email() : null,
            'key_source' => $this->connection->keySource(),
            'property' => $canSetUp ? $this->connection->property($site) : null,
            'property_source' => $this->connection->propertySource($site),
            'suggested_property' => $this->connection->suggestedProperty($site),
            'urls' => $canSetUp ? [
                'key' => cp_route('seo.search-console.key'),
                'forget_key' => cp_route('seo.search-console.key.forget'),
                'property' => cp_route('seo.search-console.property'),
                'check' => cp_route('seo.search-console.check'),
                'import' => cp_route('seo.search-console.import'),
            ] : null,
            'guides' => ['key_policy' => Connection::KEY_POLICY, 'keys' => Connection::KEYS_GUIDE],
        ];
    }

    public function key(Request $request): JsonResponse
    {
        $this->authorize();

        if ($this->connection->keySource() === 'env') {
            abort(409, __('seo::cp.search_console.messages.key_in_env'));
        }

        $request->validate(['key' => ['required_without:file', 'nullable', 'string'], 'file' => ['required_without:key', 'nullable', 'file', 'max:16']]);
        $json = $request->hasFile('file') ? (string) $request->file('file')->get() : (string) $request->input('key');

        if (Connection::parseKey($json) === null) {
            throw ValidationException::withMessages(['key' => __('seo::cp.search_console.messages.not_a_key')]);
        }

        $this->connection->saveKey($json);
        Connection::apply();

        return response()->json(['email' => $this->connection->email()]);
    }

    public function forgetKey(): JsonResponse
    {
        $this->authorize();
        $this->connection->forgetKey();

        return response()->json(['email' => null]);
    }

    public function property(Request $request): JsonResponse
    {
        $this->authorize();

        $site = Site::selected()->handle();

        if ($this->connection->propertySource($site) === 'env') {
            abort(409, __('seo::cp.search_console.messages.property_in_env'));
        }

        $property = trim((string) $request->validate(['property' => ['required', 'string', 'max:255']])['property']);

        if (! preg_match('#^(sc-domain:[a-z0-9.-]+|https?://[^\s]+/)$#i', $property)) {
            throw ValidationException::withMessages(['property' => __('seo::cp.search_console.messages.property_format')]);
        }

        $this->connection->saveProperty($property, $site);

        return response()->json(['property' => $property]);
    }

    public function check(Client $client): JsonResponse
    {
        $this->authorize();

        return response()->json($this->connection->check($client, Site::selected()->handle()));
    }

    public function import(Client $client, Importer $importer): JsonResponse
    {
        $this->authorize();

        $site = Site::selected()->handle();

        if (! $client->configured($site)) {
            return response()->json(['ok' => false, 'message' => __('seo::cp.search_console.messages.add_first')]);
        }

        try {
            $count = $importer->import($site);
        } catch (Throwable $e) {
            // The editor sees what the check makes of it; the error itself goes to the log.
            report($e);

            return response()->json($this->connection->check($client, $site));
        }

        return response()->json(['ok' => true, 'message' => trans_choice('seo::cp.search_console.messages.imported', $count, ['count' => $count])]);
    }

    private function authorize(): void
    {
        abort_unless($this->canSetUp(), 403);
    }

    private function canSetUp(): bool
    {
        return (bool) User::current()?->can('editSettings', Addon::get(Edition::PACKAGE));
    }
}
