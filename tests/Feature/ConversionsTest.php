<?php

use JothamLec\MarketingToolkit\Conversions\Attribution;
use JothamLec\MarketingToolkit\Listeners\CountConversion;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Form;

function contactForm(bool $withSource = true): Statamic\Contracts\Forms\Form
{
    $form = Form::make('contact')->title('Contact')->store(true);
    $form->save();

    Blueprint::make('contact')->setNamespace('forms')->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
        ['handle' => 'email', 'field' => ['type' => 'text', 'validate' => ['required']]],
    ]]]]]])->save();

    if ($withSource) {
        Attribution::addToForms();
    }

    return Form::find('contact');
}

function sourceCookie(array $values): string
{
    return json_encode($values);
}

test('install adds the lead source fields to every form, once', function () {
    contactForm(withSource: false);

    $this->artisan('statamic:seo:install', ['--forms' => true])->expectsOutputToContain('Lead source fields added to: contact.')->assertSuccessful();
    $this->artisan('statamic:seo:install', ['--forms' => true])->expectsOutputToContain('Every form has the lead source fields.');

    $fields = Form::find('contact')->blueprint()->fields()->all();
    expect($fields->keys()->all())->toBe(['email', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'referrer', 'landing_page'])
        ->and($fields->get('utm_source')->type())->toBe('hidden');
});

test('a submission keeps where the lead first came from, and leaves a conversion cookie', function () {
    seoGlobal(['attribution' => true]);
    contactForm();

    $response = $this->withUnencryptedCookie(Attribution::COOKIE, sourceCookie([
        'source' => 'linkedin', 'medium' => 'paid', 'campaign' => 'autumn<script>', 'referrer' => 'https://www.linkedin.com/', 'landing' => '/offer?utm_source=linkedin',
    ]))->post('https://example.test/!/forms/contact', ['email' => 'a@example.test']);

    $response->assertRedirect()->assertCookie(CountConversion::COOKIE, 'contact', encrypted: false);

    expect(Form::find('contact')->submissions()->first()->data()->all())->toMatchArray([
        'email' => 'a@example.test',
        'utm_source' => 'linkedin',
        'utm_medium' => 'paid',
        'utm_campaign' => 'autumn',
        'referrer' => 'https://www.linkedin.com/',
        'landing_page' => '/offer?utm_source=linkedin',
    ]);
});

test('a submission that fails validation leaves no conversion cookie', function () {
    contactForm();

    $this->post('https://example.test/!/forms/contact', [])->assertSessionHasErrors(errorBag: 'form.contact')->assertCookieMissing(CountConversion::COOKIE);
});

test('turned off in SEO & brand: no source saved, no conversion', function () {
    seoGlobal(['conversions' => false, 'attribution' => false]);
    contactForm();

    $this->withUnencryptedCookie(Attribution::COOKIE, sourceCookie(['source' => 'linkedin']))
        ->post('https://example.test/!/forms/contact', ['email' => 'a@example.test'])
        ->assertCookieMissing(CountConversion::COOKIE);

    expect(Form::find('contact')->submissions()->first()->get('utm_source'))->toBeNull();
});

test('the head sends a lead to each tool set, and remembers where visitors first came from', function () {
    seoGlobal(['gtm_id' => 'GTM-ABC1234', 'ga4_id' => 'G-ABCDE12345', 'posthog_key' => 'phc_abcdefghijklmnopqrstuvwxyz0123', 'meta_pixel_id' => '123456789012', 'linkedin_partner_id' => '1234567', 'linkedin_conversion_id' => '7654321', 'attribution' => true]);
    $head = renderAt('/', '<s:seo:head />');

    expect($head)->toContain('w.mtConversion=function(form){')
        ->toContain("w.dataLayer.push({event:'generate_lead',form_name:form});")
        ->toContain("gtag('event','generate_lead',{form_name:form});")
        ->toContain("posthog.capture('form submitted',{form:form});")
        ->toContain("fbq('track','Lead',{content_name:form});")
        ->toContain("w.lintrk('track',{conversion_id:7654321});")
        ->toContain("d.cookie='mt_source='+encodeURIComponent(JSON.stringify(v))")
        // No Consent Mode: saved straight away.
        ->toContain("save();\n})(window,document);");
});

test('with Consent Mode, the source is remembered once analytics is granted', function () {
    seoGlobal(['consent_mode' => true, 'attribution' => true]);

    expect(renderAt('/', '<s:seo:head />'))->toContain('w.mtConsent=function')
        ->toContain("mtConsent(function(s){if(s.analytics_storage==='granted')save();});");
});

test('no tool set: no conversion script; lead source only once it is turned on', function () {
    seoGlobal([]);
    expect(renderAt('/', '<s:seo:head />'))->not->toContain('mtConversion')->not->toContain('mt_source=');

    seoGlobal(['attribution' => true]);
    expect(renderAt('/', '<s:seo:head />'))->toContain('mt_source=');
});
