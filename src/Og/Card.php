<?php

namespace JothamLec\MarketingToolkit\Og;

/**
 * Holds what goes on a generated share card. A Template decides where each part
 * sits and how it looks.
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
    ) {}
}
