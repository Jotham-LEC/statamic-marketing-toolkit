<?php

namespace JothamLec\MarketingToolkit\Fieldtypes;

use JothamLec\MarketingToolkit\Reports\ReportSettings;
use JothamLec\MarketingToolkit\Support\Features;
use Statamic\Fields\Fieldtype;

/**
 * The search result and the share cards this entry will produce, drawn live
 * from the publish form. It shows; it stores nothing.
 */
final class SeoPreview extends Fieldtype
{
    protected static $handle = 'mt_preview';

    protected static $title = 'marketing-toolkit::fields.seo.seo_preview.title';

    protected $categories = ['special'];

    public function preload(): array
    {
        $settings = app(ReportSettings::class);

        return [
            'urls' => [
                'meta' => cp_route('mt.preview.meta'),
                'card' => cp_route('mt.preview.card'),
            ],
            // The report's thresholds (Marketing → Reports → Settings), so the counters and the reports agree.
            'limits' => [
                'title' => [$settings->int('title_min'), $settings->int('title_max')],
                'description' => [$settings->int('description_min'), $settings->int('description_max')],
            ],
            'og' => Features::on('share_cards'),
        ];
    }

    public function process($data)
    {
        return null;
    }

    public function preProcess($data)
    {
        return null;
    }
}
