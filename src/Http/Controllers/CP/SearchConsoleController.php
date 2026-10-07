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
use JothamLec\MarketingToolkit\Support\Package;
use JothamLec\MarketingToolkit\Support\Sites;
use Statamic\Facades\Addon;
use Statamic\Facades\Site;
use Throwable;

/**
 * Marketing → Search Console, and setting it up there: the service account key, the
 * property, a check that Google lets the key read it, and the first import.
 * For whoever may change the addon's settings; `.env` values win and can't
 * be changed here. The property, the check and the import are of the site
 * selected in the control panel; the key serves every site.
 */
class SearchConsoleController
{
    public function __construct(private Connection $connection) {}

    /**
     * Marketing → Search Console: the steps to connect it, then where the
     * connection stands: the key, each site's property, the last import.
     * Anyone who may view SEO sees it, for the sites they may work on; only
     * whoever may change the addon's settings gets the steps, the buttons and
     * the properties (the others, whether each site is connected).
     */
    public function index(Client $client): Response
    {
        $site = Site::selected()->handle();
        $stats = fn () => SearchStat::query()->shownOn($site);

        return Inertia::render('marketing-toolkit::SearchConsole', [
            'setup' => $this->setup($client, $site),
            'sites' => Sites::multiple() ? Site::authorized()->map(fn ($each) => [
                'name' => (string) $each->name(),
                'property' => Package::canEditSettings() ? $this->connection->property($each->handle()) : null,
                'connected' => $client->configured($each->handle()),
                'selected' => $each->handle() === $site,
            ])->values()->all() : [],
            'imported' => [
                'fetched_at' => $stats()->latest('fetched_at')->first()?->fetched_at?->toIso8601String(),
                'pages' => $stats()->count(),
            ],
            'overviewUrl' => cp_route('mt.index'),
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
        $canSetUp = Package::canEditSettings();

        return [
            'configured' => $client->configured($site),
            'can_set_up' => $canSetUp,
            'email' => $canSetUp ? $this->connection->email() : null,
            'key_source' => $this->connection->keySource(),
            'property' => $canSetUp ? $this->connection->property($site) : null,
            'property_source' => $this->connection->propertySource($site),
            'suggested_property' => $this->connection->suggestedProperty($site),
            'urls' => $canSetUp ? [
                'key' => cp_route('mt.search-console.key'),
                'forget_key' => cp_route('mt.search-console.key.forget'),
                'property' => cp_route('mt.search-console.property'),
                'check' => cp_route('mt.search-console.check'),
                'import' => cp_route('mt.search-console.import'),
            ] : null,
            'guides' => ['key_policy' => Connection::KEY_POLICY, 'keys' => Connection::KEYS_GUIDE],
        ];
    }

    public function key(Request $request): JsonResponse
    {
        $this->authorize();

        if ($this->connection->keySource() === 'env') {
            abort(409, __('marketing-toolkit::cp.search_console.messages.key_in_env'));
        }

        $request->validate(['key' => ['required_without:file', 'nullable', 'string'], 'file' => ['required_without:key', 'nullable', 'file', 'max:16']]);
        $json = $request->hasFile('file') ? (string) $request->file('file')->get() : (string) $request->input('key');

        if (Connection::parseKey($json) === null) {
            throw ValidationException::withMessages(['key' => __('marketing-toolkit::cp.search_console.messages.not_a_key')]);
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
            abort(409, __('marketing-toolkit::cp.search_console.messages.property_in_env'));
        }

        $property = trim((string) $request->validate(['property' => ['required', 'string', 'max:255']])['property']);

        if (! preg_match('#^(sc-domain:[a-z0-9.-]+|https?://[^\s]+/)$#i', $property)) {
            throw ValidationException::withMessages(['property' => __('marketing-toolkit::cp.search_console.messages.property_format')]);
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
            return response()->json(['ok' => false, 'message' => __('marketing-toolkit::cp.search_console.messages.add_first')]);
        }

        try {
            $count = $importer->import($site);
        } catch (Throwable $e) {
            // The error itself goes to the log. The editor is told the import failed, and
            // what the check makes of it when the check fails too: a key that still reads
            // the property says nothing about why the import did not work.
            report($e);

            $check = $this->connection->check($client, $site);

            return response()->json(['ok' => false, 'message' => $check['ok']
                ? __('marketing-toolkit::cp.search_console.messages.import_failed_log')
                : __('marketing-toolkit::cp.search_console.messages.import_failed').' '.$check['message'],
            ]);
        }

        return response()->json(['ok' => true, 'message' => trans_choice('marketing-toolkit::cp.search_console.messages.imported', $count, ['count' => $count])]);
    }

    private function authorize(): void
    {
        abort_unless(Package::canEditSettings(), 403);
    }
}
