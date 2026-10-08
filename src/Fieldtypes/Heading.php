<?php

namespace JothamLec\MarketingToolkit\Fieldtypes;

use Statamic\Fields\Fieldtype;

/**
 * A heading between the fields of the SEO tab (Sharing, Advanced), drawn
 * like the headings of a blueprint's own sections. Statamic's Section
 * fieldtype draws a boxed card instead, and real sections can't sit inside
 * the `seo` group. It shows its label; it stores nothing.
 */
final class Heading extends Fieldtype
{
    /** @var string */
    protected static $handle = 'mt_heading';

    /** @var string */
    protected static $title = 'marketing-toolkit::fields.seo.heading.title';

    /** @var list<string> */
    protected $categories = ['special'];

    /** @var bool */
    protected $selectable = false;

    /**
     * The label, translated: the field's config holds the translation key as
     * the blueprint has it, and the control panel's script doesn't load the
     * addon's field labels.
     *
     * @return array{display: string}
     */
    public function preload()
    {
        return ['display' => (string) __((string) $this->config('display'))];
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
