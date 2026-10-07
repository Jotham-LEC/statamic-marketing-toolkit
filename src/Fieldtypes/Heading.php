<?php

namespace JothamLec\MarketingToolkit\Fieldtypes;

use Statamic\Fields\Fieldtype;

/**
 * A heading between the fields of the SEO tab (Sharing, Advanced), drawn
 * like the headings of a blueprint's own sections. Statamic's Section
 * fieldtype draws a boxed card instead, and real sections can't sit inside
 * the `seo` group. It shows its label; it stores nothing.
 */
class Heading extends Fieldtype
{
    protected static $handle = 'mt_heading';

    protected static $title = 'Heading';

    protected $categories = ['special'];

    protected $selectable = false;

    public function process($data)
    {
        return null;
    }

    public function preProcess($data)
    {
        return null;
    }
}
