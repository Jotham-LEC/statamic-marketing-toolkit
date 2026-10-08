<?php

use JothamLec\MarketingToolkit\Commands\Install;
use JothamLec\MarketingToolkit\Reports\ReportSettings;
use JothamLec\MarketingToolkit\Reports\Runner;
use JothamLec\MarketingToolkit\Support\Features;
use JothamLec\MarketingToolkit\Tracking\Tracking;
use Statamic\Facades\YAML;

/*
 * The blueprints are YAML, so they repeat lists and defaults the code owns. These keep the two in step.
 */

/**
 * @return array<string, array<string, mixed>> handle => field config
 */
function blueprintFields(array $tabs): array
{
    return collect($tabs)->flatMap(fn (array $tab) => $tab['sections'])->flatMap(fn (array $section) => $section['fields'])
        ->mapWithKeys(fn (array $field) => [$field['handle'] => $field['field']])->all();
}

function settingsFields(): array
{
    return blueprintFields(YAML::file(__DIR__.'/../../resources/blueprints/settings.yaml')->parse()['tabs']);
}

test('the report settings have a switch for each check the runner knows, and no other', function () {
    $switches = array_values(array_filter(array_keys(settingsFields()), fn (string $handle) => str_starts_with($handle, 'rule_')));
    $rules = array_map(fn (string $class) => 'rule_'.$class::handle(), Runner::RULES);

    expect($switches)->toEqualCanonicalizing($rules);
});

test('the report settings fall back to the blueprint\'s defaults', function () {
    $defaults = (new ReportSettings([]))->all();

    expect($defaults)->toMatchArray([
        // Off until asked for: it sends requests to the sites a page links to.
        'rule_external_links' => false,
        'rule_title_length' => true,
        'title_min' => 30,
        'title_max' => 60,
        'chunk_size' => 25,
        'keep_reports' => 10,
        'schedule' => 'off',
        'schedule_day' => 'monday',
        'schedule_time' => '03:00',
    ]);

    expect(array_keys(settingsFields()['schedule_day']['options']))->toEqualCanonicalizing(ReportSettings::DAYS);
});

test('the Features tab has a switch for each module, and no other', function () {
    $switches = array_values(array_filter(array_keys(blueprintFields(Install::tabs('assets', 'marketing'))), fn (string $handle) => str_starts_with($handle, 'feature_')));

    expect($switches)->toEqualCanonicalizing(array_map(fn (string $module) => 'feature_'.$module, array_keys(Features::MODULES)));
});

test('the Tracking tab checks each ID as the tracking code reads it', function () {
    $fields = blueprintFields(Install::tabs('assets', 'marketing'));

    foreach (Tracking::FIELDS as $tracker => $handle) {
        $regex = collect($fields[$handle]['validate'] ?? [])->first(fn (string $rule) => str_starts_with($rule, 'regex:'));

        // The code takes GTM and GA4 IDs in capitals, so the form may take them in any case.
        expect($regex === null ? null : preg_replace('#/i$#', '/', substr($regex, 6)))->toBe(Tracking::PATTERNS[$tracker], $tracker);
    }
});
