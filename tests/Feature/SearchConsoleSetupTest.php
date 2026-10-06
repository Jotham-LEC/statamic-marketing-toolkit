<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use JothamLec\MarketingToolkit\SearchConsole\Client;
use JothamLec\MarketingToolkit\SearchConsole\Connection;
use JothamLec\MarketingToolkit\SearchConsole\SearchStat;
use JothamLec\MarketingToolkit\ServiceProvider;
use JothamLec\MarketingToolkit\Support\Edition;
use Statamic\Facades\Addon;

beforeEach(function () {
    config(['seo.search_console' => ['credentials' => null, 'property' => null, 'days' => 28]]);
    File::delete((new Connection)->keyPath());
});

afterEach(function () {
    File::delete((new Connection)->keyPath());
    File::delete(resource_path('addons/marketing-toolkit.yaml'));
});

function googleKey(): string
{
    openssl_pkey_export(openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]), $private);

    return json_encode(['type' => 'service_account', 'client_email' => 'seo@project.iam.gserviceaccount.com', 'private_key' => $private]);
}

function fakeGoogle(int $status = 200, array $body = ['siteUrl' => 'sc-domain:example.test', 'permissionLevel' => 'siteRestrictedUser']): void
{
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'token-1', 'expires_in' => 3599]),
        'www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response(['rows' => [
            ['keys' => ['https://example.test/'], 'clicks' => 3, 'impressions' => 40, 'ctr' => 0.075, 'position' => 4.2],
        ]], $status, ),
        'www.googleapis.com/webmasters/v3/sites/*' => Http::response($body, $status),
    ]);
}

test('its own screen offers the steps to whoever may change the addon\'s settings, with the site\'s domain suggested', function () {
    $this->actingAs(cpUser(super: true))->get(cp_route('seo.search-console.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('seo::SearchConsole')
        ->where('setup.configured', false)
        ->where('setup.can_set_up', true)
        ->where('setup.suggested_property', 'sc-domain:example.test')
        ->where('setup.urls.key', cp_route('seo.search-console.key'))
        ->where('imported', ['fetched_at' => null, 'pages' => 0])
        ->where('sites', []));
});

test('the overview links to that screen rather than holding the steps', function () {
    $this->actingAs(cpUser(super: true))->get(cp_route('seo.index'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('search', null)
        ->where('searchConsole', ['url' => cp_route('seo.search-console.index')])
        ->missing('searchSetup'));
});

test('someone who may only view SEO is told it isn\'t connected, without the steps', function () {
    $this->actingAs(cpUser(['view seo']))->get(cp_route('seo.search-console.index'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('setup.can_set_up', false)
        ->where('setup.urls', null));
});

test('saving the report settings keeps the properties set up on the Search Console screen', function () {
    $this->actingAs(cpUser(super: true));
    $this->postJson(cp_route('seo.search-console.property'), ['property' => 'sc-domain:example.test'])->assertOk();

    $addon = Addon::get(Edition::PACKAGE);
    $values = collect($addon->settingsBlueprint()->fields()->addValues($addon->settings()->raw())->preProcess()->values())->all();

    $this->patchJson(cp_route('addons.settings.update', $addon->slug()), $values)->assertOk();

    expect((new Connection)->savedProperty('default'))->toBe('sc-domain:example.test');
});

test('an uploaded key is kept privately and connects with a saved property', function () {
    $this->actingAs(cpUser(super: true));

    $this->post(cp_route('seo.search-console.key'), ['file' => UploadedFile::fake()->createWithContent('key.json', googleKey())])
        ->assertOk()->assertJson(['email' => 'seo@project.iam.gserviceaccount.com']);
    $this->postJson(cp_route('seo.search-console.property'), ['property' => 'sc-domain:example.test'])->assertOk();

    $path = (new Connection)->keyPath();

    expect(substr(sprintf('%o', fileperms($path)), -4))->toBe('0600')
        ->and(Addon::get(Edition::PACKAGE)->settings()->get(Connection::SETTING))->toBe('sc-domain:example.test');

    // A later request boots with what was saved.
    config(['seo.search_console.credentials' => null, 'seo.search_console.property' => null]);
    Connection::apply();

    expect(config('seo.search_console.credentials'))->toBe($path)
        ->and(app(Client::class)->configured())->toBeTrue();

    $this->deleteJson(cp_route('seo.search-console.key.forget'))->assertOk();

    expect(File::exists($path))->toBeFalse();
});

test('an uploaded key is encrypted on disk, and one saved before that still reads', function () {
    $connection = new Connection;
    $key = googleKey();
    config(['seo.search_console.credentials' => $connection->keyPath()]);

    $connection->saveKey($key);

    expect(File::get($connection->keyPath()))->not->toContain('private_key')
        ->and($connection->readKey($connection->keyPath()))->toBe($key)
        ->and($connection->email())->toBe('seo@project.iam.gserviceaccount.com');

    File::put($connection->keyPath(), $key);

    expect($connection->readKey($connection->keyPath()))->toBe($key)
        ->and($connection->email())->toBe('seo@project.iam.gserviceaccount.com');
});

test('a check that fails before Google answers is logged, and says where to look', function () {
    config(['seo.search_console.credentials' => '{"not": "a key"}', 'seo.search_console.property' => 'sc-domain:example.test']);
    Exceptions::fake();

    expect((new Connection)->check(app(Client::class)))->toBe(['ok' => false, 'message' => __('seo::cp.search_console.messages.unexpected')]);

    Exceptions::assertReported(RuntimeException::class);
});

test('what isn\'t a key or a property is refused, saying what to give instead', function () {
    $this->actingAs(cpUser(super: true));

    $this->postJson(cp_route('seo.search-console.key'), ['key' => '{"client_email": "x@y.z"}'])
        ->assertUnprocessable()->assertJsonValidationErrors(['key' => 'service account key']);

    $markup = json_decode(googleKey(), true);
    $markup['client_email'] = '<img src=x onerror=alert(1)>';
    $this->postJson(cp_route('seo.search-console.key'), ['key' => json_encode($markup)])
        ->assertUnprocessable()->assertJsonValidationErrors(['key' => 'service account key']);
    $this->postJson(cp_route('seo.search-console.property'), ['property' => 'example.test'])
        ->assertUnprocessable()->assertJsonValidationErrors(['property' => 'sc-domain:example.com']);

    expect(File::exists((new Connection)->keyPath()))->toBeFalse();
});

test('values in .env win and can\'t be changed from the control panel', function () {
    config(['seo.search_console.credentials' => googleKey(), 'seo.search_console.property' => 'https://example.test/']);
    $this->actingAs(cpUser(super: true));

    $this->postJson(cp_route('seo.search-console.key'), ['key' => googleKey()])->assertStatus(409);
    $this->postJson(cp_route('seo.search-console.property'), ['property' => 'sc-domain:example.test'])->assertStatus(409);

    $this->get(cp_route('seo.search-console.index'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('setup.key_source', 'env')
        ->where('setup.property_source', 'env')
        ->where('setup.email', 'seo@project.iam.gserviceaccount.com'));
});

test('only whoever may change the addon\'s settings can set it up', function () {
    $this->actingAs(cpUser(['view seo']));

    $this->postJson(cp_route('seo.search-console.key'), ['key' => googleKey()])->assertForbidden();
    $this->postJson(cp_route('seo.search-console.check'))->assertForbidden();
    $this->postJson(cp_route('seo.search-console.import'))->assertForbidden();
});

test('checking the connection says what to fix in Google\'s words turned into steps', function (int $status, array $body, string $says) {
    config(['seo.search_console.credentials' => googleKey(), 'seo.search_console.property' => 'sc-domain:example.test']);
    fakeGoogle($status, $body);

    $this->actingAs(cpUser(super: true))->postJson(cp_route('seo.search-console.check'))
        ->assertOk()->assertJson(['ok' => $status === 200])->assertJsonPath('message', fn (string $message) => str_contains($message, $says));
})->with([
    'connected' => [200, ['siteUrl' => 'sc-domain:example.test', 'permissionLevel' => 'siteRestrictedUser'], 'Connected'],
    'not a user' => [403, ['error' => ['code' => 403, 'message' => 'User does not have sufficient permission', 'status' => 'PERMISSION_DENIED']], 'seo@project.iam.gserviceaccount.com is not a user of sc-domain:example.test'],
    'API off' => [403, ['error' => ['code' => 403, 'message' => 'Google Search Console API has not been used in project 1 before or it is disabled.', 'details' => [['reason' => 'SERVICE_DISABLED']]]], 'API is not enabled'],
    'no such property' => [404, ['error' => ['code' => 404, 'message' => 'Not found']], 'has no property sc-domain:example.test'],
]);

test('a key Google has disabled gets its own explanation, with the guide', function () {
    config(['seo.search_console.credentials' => googleKey(), 'seo.search_console.property' => 'sc-domain:example.test']);
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Invalid grant: account disabled'], 400)]);

    $this->actingAs(cpUser(super: true))->postJson(cp_route('seo.search-console.check'))
        ->assertOk()->assertJson(['ok' => false])
        ->assertJsonPath('message', fn (string $message) => str_contains($message, 'is disabled') && str_contains($message, 'https://docs.cloud.google.com/iam/docs/keys-disable-enable'));
});

test('importing from the control panel brings in the numbers', function () {
    config(['seo.search_console.credentials' => googleKey(), 'seo.search_console.property' => 'sc-domain:example.test']);
    fakeGoogle();

    $this->actingAs(cpUser(super: true))->postJson(cp_route('seo.search-console.import'))
        ->assertOk()->assertJson(['ok' => true, 'message' => 'Imported 1 page.']);

    expect(SearchStat::query()->sum('clicks'))->toEqual(3);
});

test('the daily import runs once it is set up, however that was done', function () {
    $schedule = new Schedule;
    (fn () => $this->schedule($schedule))->call(app()->getProvider(ServiceProvider::class));
    $event = collect($schedule->events())->first(fn ($event) => str_contains((string) $event->command, 'seo:search-console'));

    expect($event)->not->toBeNull()
        ->and($event->filtersPass(app()))->toBeFalse();

    config(['seo.search_console.credentials' => googleKey(), 'seo.search_console.property' => 'sc-domain:example.test']);

    expect($event->filtersPass(app()))->toBeTrue();
});
