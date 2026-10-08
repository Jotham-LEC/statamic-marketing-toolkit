<?php

namespace JothamLec\MarketingToolkit\Og;

/**
 * Holds what goes on a generated share card. A Template decides where each part
 * sits and how it looks.
 *
 * The brand is `logo` (a local image file, already checked to fit the shape's logo box), or
 * else `brandText` in type. `shape` is the size to draw; a template draws the shapes its
 * shapes() lists.
 */
final readonly class Card
{
    public function __construct(
        public string $title,
        public ?string $description,
        public string $label,
        public string $siteName,
        public ?string $picture,
        public string $background,
        public string $text,
        public string $accent,
        public ?string $logo = null,
        public string $brandText = '',
        public Shape $shape = Shape::Landscape,
    ) {}
}
