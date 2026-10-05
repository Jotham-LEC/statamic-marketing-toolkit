<?php

namespace JothamLec\Seo\Fieldtypes;

use Statamic\Fields\Fieldtype;

/**
 * The search result and the share cards this entry will produce, drawn live
 * from the publish form. It shows; it stores nothing.
 */
class SeoPreview extends Fieldtype
{
    protected static $handle = 'seo_preview';

    protected $categories = ['special'];

    public function preload(): array
    {
        return [
            'urls' => [
                'meta' => cp_route('seo.preview.meta'),
                'card' => cp_route('seo.preview.card'),
            ],
            'limits' => [
                'title' => [(int) config('seo.title.min', 30), (int) config('seo.title.max', 60)],
                'description' => [(int) config('seo.description.min', 50), (int) config('seo.description.length', 155)],
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
