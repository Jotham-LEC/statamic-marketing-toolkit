<?php

namespace JothamLec\MarketingToolkit\Conversions;

use Illuminate\Http\Request;
use Statamic\Contracts\Forms\Submission;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Form;

/**
 * Where a lead came from, the first time they reached the site: the UTM
 * tags of the address they landed on, the page that sent them, and that
 * landing page. A script in <s:seo:head /> keeps them in the `mt_source`
 * cookie for 90 days; a form submission copies them into its own fields, so
 * the control panel and the exports show them beside the message.
 */
class Attribution
{
    public const string COOKIE = 'mt_source';

    /** Submission field => the cookie's key. */
    public const array FIELDS = [
        'utm_source' => 'source',
        'utm_medium' => 'medium',
        'utm_campaign' => 'campaign',
        'utm_term' => 'term',
        'utm_content' => 'content',
        'referrer' => 'referrer',
        'landing_page' => 'landing',
    ];

    /**
     * The visitor's first-touch values, cleaned: text only, at most 255 characters each.
     *
     * @return array<string, string> submission field => value
     */
    public function fromRequest(Request $request): array
    {
        $cookie = json_decode((string) $request->cookie(self::COOKIE), true);

        if (! is_array($cookie)) {
            return [];
        }

        $values = [];

        foreach (self::FIELDS as $field => $key) {
            $value = $cookie[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $values[$field] = mb_substr(strip_tags(trim($value)), 0, 255);
            }
        }

        return $values;
    }

    /**
     * Fills the submission's attribution fields that its form has and the
     * visitor didn't fill in themselves.
     */
    public function apply(Submission $submission, Request $request): void
    {
        $fields = $submission->form()->blueprint()?->fields()->all()->keys()->all() ?? [];

        foreach ($this->fromRequest($request) as $field => $value) {
            if (in_array($field, $fields, true) && blank($submission->get($field))) {
                $submission->set($field, $value);
            }
        }
    }

    /**
     * The fields `php please seo:install --forms` adds to each form: hidden
     * on the site, listed in the control panel.
     *
     * @return list<array{handle: string, field: array<string, mixed>}>
     */
    public static function blueprintFields(): array
    {
        return array_map(fn (string $field) => ['handle' => $field, 'field' => [
            'type' => 'hidden',
            'display' => 'seo::fields.attribution.'.$field,
            'listable' => 'hidden',
        ]], array_keys(self::FIELDS));
    }

    /**
     * Adds the attribution fields to every form's blueprint that lacks them.
     *
     * @return list<string> the forms changed
     */
    public static function addToForms(): array
    {
        $changed = [];

        foreach (Form::all() as $form) {
            $blueprint = $form->blueprint() ?? Blueprint::make($form->handle())->setNamespace('forms');
            $existing = $blueprint->fields()->all()->keys()->all();
            $missing = array_values(array_filter(self::blueprintFields(), fn (array $field) => ! in_array($field['handle'], $existing, true)));

            if ($missing === []) {
                continue;
            }

            $contents = $blueprint->contents();
            $contents['tabs'] ??= ['main' => ['sections' => [['fields' => []]]]];
            $contents['tabs']['attribution'] = ['display' => 'seo::fields.attribution.tab', 'sections' => [['fields' => [
                ...($contents['tabs']['attribution']['sections'][0]['fields'] ?? []),
                ...$missing,
            ]]]];

            $blueprint->setContents($contents)->save();
            $changed[] = $form->handle();
        }

        return $changed;
    }
}
