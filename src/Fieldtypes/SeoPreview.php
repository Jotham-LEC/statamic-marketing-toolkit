<?php

namespace JothamLec\MarketingToolkit\Fieldtypes;

use JothamLec\MarketingToolkit\Reports\ReportSettings;
use JothamLec\MarketingToolkit\Support\Features;
use Statamic\Fields\Fieldtype;

/**
 * Shows the search result and the share cards that this entry will produce,
 * drawn live from the publish form. The field only displays them and stores nothing.
 */
final class SeoPreview extends Fieldtype
{
    /** @var string */
    protected static $handle = 'mt_preview';

    /** @var string */
    protected static $title = 'marketing-toolkit::fields.seo.seo_preview.title';

    /** @var list<string> */
    protected $categories = ['special'];

    /**
     * @return array{urls: array{meta: string, card: string}, limits: array{title: array{int, int}, description: array{int, int}}, og: bool}
     */
    public function preload(): array
    {
        $settings = app(ReportSettings::class);

        return [
            'urls' => [
                'meta' => cp_route('mt.preview.meta'),
                'card' => cp_route('mt.preview.card'),
            ],
            // These are the report's thresholds (Marketing → Reports → Settings), so the counters and reports agree.
            'limits' => [
                'title' => [$settings->int('title_min'), $settings->int('title_max')],
                'description' => [$settings->int('description_min'), $settings->int('description_max')],
            ],
            'og' => Features::on('share_cards'),
        ];
    }

    /**
     * @param  mixed  $data
     * @return null
     */
    public function process($data)
    {
        return null;
    }

    /**
     * @param  mixed  $data
     * @return null
     */
    public function preProcess($data)
    {
        return null;
    }
}
