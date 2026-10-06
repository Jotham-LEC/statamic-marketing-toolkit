<?php

namespace JothamLec\Seo\Og;

use SimonHamp\TheOg\Image;

/**
 * A share-card design. Register templates under keys in `seo.og.templates`;
 * a collection picks one with `og_template`.
 * Build the card with simonhamp/the-og: its built-in layouts, or your own
 * AbstractLayout subclass for a design of your own.
 */
abstract class Template
{
    abstract public function image(Card $card): Image;

    /**
     * Change when the design changes, so cached cards are redrawn.
     */
    public function version(): string
    {
        return '1';
    }
}
