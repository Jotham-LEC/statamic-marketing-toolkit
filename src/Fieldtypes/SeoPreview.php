<?php

namespace JothamLec\MarketingToolkit\Fieldtypes;

use Statamic\Fields\Fieldtype;

/**
 * The search result and the share cards this entry will produce, drawn live
 * from the publish form. It shows; it stores nothing.
 */
class SeoPreview extends Fieldtype
{
    protected static $handle = 'seo_preview';

    protected static $title = 'seo::fields.seo.seo_preview.title';

    protected $categories = ['special'];

    private const int TITLE_MIN = 30;

    private const int DESCRIPTION_MIN = 50;

    private const int DESCRIPTION_MAX = 160;

    public function preload(): array
    {
        return [
            'urls' => [
                'meta' => cp_route('seo.preview.meta'),
                'card' => cp_route('seo.preview.card'),
            ],
            // Where the counters turn amber: what Google shows without cutting it short.
            'limits' => [
                'title' => [self::TITLE_MIN, (int) config('seo.title.max', 60)],
                'description' => [self::DESCRIPTION_MIN, self::DESCRIPTION_MAX],
            ],
            'og' => (bool) config('seo.og.enabled'),
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
