<?php

use Illuminate\Support\Facades\Vite;
use Inertia\Testing\AssertableInertia;
use JothamLec\MarketingToolkit\Tracking\Tracking;
use Statamic\Facades\Blueprint;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Token;
use Statamic\Tokens\Handlers\LivePreview;

function trackingHead(): string
{
    return renderAt('/', '<s:seo:head />');
}

function trackingBody(): string
{
    return renderAt('/', '<s:seo:body />');
}

test('no tags until an ID is set; then each prints in the head, and GTM\'s noscript in the body', function () {
    seoGlobal([]);
    expect(app(Tracking::class)->head())->toBe('')->and(trackingHead())->toContain('<title>');

    seoGlobal([
        'gtm_id' => 'gtm-abc1234',
        'ga4_id' => 'G-ABCDE12345',
        'posthog_key' => 'phc_abcdefghijklmnopqrstuvwxyz0123',
        'posthog_host' => 'https://eu.i.posthog.com',
        'meta_pixel_id' => '123456789012',
        'linkedin_partner_id' => '1234567',
    ]);
    $head = trackingHead();
    $body = trackingBody();

    expect($head)
        ->toContain("'dataLayer',\"GTM-ABC1234\"")
        ->toContain('https://www.googletagmanager.com/gtag/js?id=G-ABCDE12345')
        ->toContain("gtag('config',\"G-ABCDE12345\")")
        ->toContain('posthog.init("phc_abcdefghijklmnopqrstuvwxyz0123",{"api_host":"https:\/\/eu.i.posthog.com","ui_host":"https:\/\/eu.posthog.com","person_profiles":"identified_only"})')
        ->toContain("fbq('init',\"123456789012\")")
        ->toContain('window._linkedin_data_partner_ids.push("1234567")')
        ->not->toContain('mtConsent')
        // Before the meta tags: Google's tags want to be as high in the <head> as they can.
        ->and(strpos($head, 'gtm.js'))->toBeLessThan(strpos($head, '<title>'))
        ->and($body)->toContain('https://www.googletagmanager.com/ns.html?id=GTM-ABC1234')
        ->toContain('https://www.facebook.com/tr?id=123456789012')
        ->toContain('https://px.ads.linkedin.com/collect/?pid=1234567');
});

test('.env wins over the control panel', function () {
    seoGlobal(['gtm_id' => 'GTM-FROMCP1', 'ga4_id' => 'G-FROMCP123']);
    config(['seo.tracking.gtm' => 'GTM-FROMENV']);

    expect(app(Tracking::class)->ids())->toMatchArray(['gtm' => 'GTM-FROMENV', 'ga4' => 'G-FROMCP123'])
        ->and(app(Tracking::class)->fromConfig())->toMatchArray(['gtm' => true, 'ga4' => false]);
});

test('an ID that doesn\'t look like one is never printed', function (string $field, string $value) {
    seoGlobal([$field => $value]);

    expect(app(Tracking::class)->head())->toBe('')
        ->and(collect(app(Tracking::class)->ids())->filter()->all())->toBe([]);
})->with([
    ['gtm_id', 'GTM-1234\');alert(1)//'],
    ['ga4_id', '</script><script>alert(1)</script>'],
    ['meta_pixel_id', '123abc'],
    ['linkedin_partner_id', '12 34'],
    ['posthog_key', 'phc_short'],
]);

test('PostHog\'s host must be an https address; anything else is the US cloud', function () {
    seoGlobal(['posthog_key' => 'phc_abcdefghijklmnopqrstuvwxyz0123', 'posthog_host' => 'javascript:alert(1)']);

    expect(app(Tracking::class)->posthogHost())->toBe('https://us.i.posthog.com');
});

test('nothing prints outside production, or in Live Preview', function () {
    seoGlobal(['gtm_id' => 'GTM-ABC1234']);

    $this->app['env'] = 'local';
    expect(trackingHead())->not->toContain('gtm.js')->and(trackingBody())->toBe('');

    config(['seo.tracking.environments' => ['production', 'local']]);
    expect(trackingHead())->toContain('gtm.js');

    $this->app['env'] = 'production';
    $token = Token::make(null, LivePreview::class);
    $token->save();
    expect(renderAt('/?token='.$token->token(), '<s:seo:head />'))->not->toContain('gtm.js')
        ->and(request()->isLivePreview())->toBeTrue();
});

test('Consent Mode: the defaults come before the tags, and the banner\'s wait', function () {
    seoGlobal(['gtm_id' => 'GTM-ABC1234', 'consent_mode' => true, 'consent_analytics_storage' => 'granted', 'consent_wait_for_update' => 800]);
    $head = trackingHead();

    expect($head)->toContain("gtag('consent','default',{\"ad_storage\":\"denied\",\"analytics_storage\":\"granted\",\"ad_user_data\":\"denied\",\"ad_personalization\":\"denied\",\"wait_for_update\":800});")
        ->and(strpos($head, "gtag('consent'"))->toBeLessThan(strpos($head, 'gtm.js'))
        // GTM and GA4 read Consent Mode themselves: no bridge for them.
        ->and($head)->not->toContain('mtConsent');
});

test('Consent Mode holds back Meta, LinkedIn and PostHog until the banner says yes, and drops their noscript pixels', function () {
    seoGlobal([
        'consent_mode' => true,
        'posthog_key' => 'phc_abcdefghijklmnopqrstuvwxyz0123',
        'meta_pixel_id' => '123456789012',
        'linkedin_partner_id' => '1234567',
    ]);
    $head = trackingHead();

    expect($head)->toContain('w.mtConsent=function')
        ->toContain("fbq('consent','revoke');mtConsent(")
        ->toContain('"opt_out_capturing_by_default":true,"persistence":"memory"')
        ->toContain("mtConsent(function(s){if(s.ad_storage==='granted')load();});")
        ->and(strpos($head, 'w.mtConsent=function'))->toBeLessThan(strpos($head, 'fbevents.js'))
        ->and(trackingBody())->not->toContain('facebook.com/tr')->not->toContain('px.ads.linkedin.com');
});

test('regions (Pro): granted everywhere, the defaults in those regions, and the bridge waits for the banner', function () {
    seoGlobal(['consent_mode' => true, 'consent_regions' => ['EEA', 'us-ca', 'nonsense!'], 'meta_pixel_id' => '123456789012']);
    $consent = app(Tracking::class)->consent();
    $head = trackingHead();

    expect($consent['regions'])->toContain('FR', 'GB', 'CH', 'NO', 'US-CA')->not->toContain('NONSENSE!')
        ->and($head)->toContain("gtag('consent','default',{\"ad_storage\":\"granted\",\"analytics_storage\":\"granted\",\"ad_user_data\":\"granted\",\"ad_personalization\":\"granted\"});")
        ->toContain('"region":["AT",')
        ->toContain('r=true');
});

test('the CP warns when GTM is set beside another tracker, on the overview', function () {
    seoGlobal(['gtm_id' => 'GTM-ABC1234', 'ga4_id' => 'G-ABCDE12345', 'meta_pixel_id' => '123456789012']);
    $this->actingAs(cpUser(super: true));

    expect(app(Tracking::class)->besideGtm())->toBe(['Google Analytics 4', 'Meta Pixel']);

    $this->get(cp_route('seo.index'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('tracking.overlap', ['Google Analytics 4', 'Meta Pixel'])
        ->where('tracking.tools.0', ['name' => 'Google Tag Manager', 'id' => 'GTM-ABC1234', 'from_env' => false])
        ->where('tracking.consent', false));

    seoGlobal(['ga4_id' => 'G-ABCDE12345']);
    expect(app(Tracking::class)->besideGtm())->toBe([]);
});

test('the Tracking tab shows its warning through a custom condition, and validates IDs', function () {
    seoGlobal([]);
    $fields = Blueprint::find('globals.seo')->fields();

    expect($fields->get('tracking_overlap')->config()['if'])->toBe('seoTrackingOverlap')
        ->and($fields->get('gtm_id')->rules()['gtm_id'])->toContain('regex:/^GTM-[A-Z0-9]{4,12}$/i');

    $this->actingAs(cpUser(super: true))
        ->get(GlobalSet::findByHandle('seo')->in('default')->editUrl())
        ->assertOk();
});

test('a CSP nonce is added to every script', function () {
    seoGlobal(['gtm_id' => 'GTM-ABC1234', 'consent_mode' => true]);
    Vite::useCspNonce('abc123');

    expect(substr_count(trackingHead(), '<script nonce="abc123">'))->toBe(2);
});

test('install adds the Tracking tab to an existing blueprint on request', function () {
    Blueprint::make('seo')->setNamespace('globals')->setContents(['tabs' => ['brand' => ['sections' => [['fields' => []]]]]])->save();

    $this->artisan('statamic:seo:install', ['--tab' => ['tracking']])->assertSuccessful();

    expect(Blueprint::find('globals.seo')->fields()->all()->keys())->toContain('gtm_id', 'consent_mode', 'consent_regions');
});
