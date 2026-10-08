<?php

namespace JothamLec\MarketingToolkit\Og;

use SimonHamp\TheOg\Image;

/**
 * Describes a share-card design. Register templates under keys in `marketing-toolkit.og.templates`,
 * and a collection picks one with `og_template`.
 * Build the card with simonhamp/the-og, using its built-in layouts or your own
 * AbstractLayout subclass for a design of your own.
 */
abstract class Template
{
    abstract public function image(Card $card): Image;

    /**
     * Returns the design's version. Change it when the design changes, so cached cards are redrawn.
     */
    public function version(): string
    {
        return '1';
    }
}
