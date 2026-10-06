<?php

namespace JothamLec\Seo\Http\Controllers\CP;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use JothamLec\Seo\SearchConsole\Client;
use JothamLec\Seo\SearchConsole\Connection;
use JothamLec\Seo\SearchConsole\Importer;
use Statamic\Facades\Addon;
use Statamic\Facades\User;
use Throwable;

/**
 * Setting up Search Console from Tools → SEO: the service account key, the
 * property, a check that Google lets the key read it, and the first import.
 * For whoever may change the addon's settings; `.env` values win and can't
 * be changed here.
 */
class SearchConsoleController
{
    public function __construct(private Connection $connection) {}

    public function key(Request $request): JsonResponse
    {
        $this->authorize();

        if ($this->connection->keySource() === 'env') {
            abort(409, 'The key is set in .env (SEO_SEARCH_CONSOLE_CREDENTIALS).');
        }

        $request->validate(['key' => ['required_without:file', 'nullable', 'string'], 'file' => ['required_without:key', 'nullable', 'file', 'max:16']]);
        $json = $request->hasFile('file') ? (string) $request->file('file')->get() : (string) $request->input('key');

        if (Connection::parseKey($json) === null) {
            throw ValidationException::withMessages(['key' => 'That is not a service account key: download one as JSON from Google Cloud → IAM & Admin → Service accounts → Keys.']);
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

        if ($this->connection->propertySource() === 'env') {
            abort(409, 'The property is set in .env (SEO_SEARCH_CONSOLE_PROPERTY).');
        }

        $property = trim((string) $request->validate(['property' => ['required', 'string', 'max:255']])['property']);

        if (! preg_match('#^(sc-domain:[a-z0-9.-]+|https?://[^\s]+/)$#i', $property)) {
            throw ValidationException::withMessages(['property' => 'Type it as Search Console names it: sc-domain:example.com, or https://example.com/ with the slash at the end.']);
        }

        $this->connection->saveProperty($property);
        Connection::apply();

        return response()->json(['property' => $property]);
    }

    public function check(Client $client): JsonResponse
    {
        $this->authorize();

        return response()->json($this->connection->check($client));
    }

    public function import(Client $client, Importer $importer): JsonResponse
    {
        $this->authorize();

        if (! $client->configured()) {
            return response()->json(['ok' => false, 'message' => 'Add the key and the property first.']);
        }

        try {
            $count = $importer->import();
        } catch (Throwable) {
            return response()->json($this->connection->check($client));
        }

        return response()->json(['ok' => true, 'message' => "Imported {$count} pages."]);
    }

    private function authorize(): void
    {
        abort_unless(User::current()?->can('editSettings', Addon::get('jotham-lec/statamic-co-seo')), 403);
    }
}
