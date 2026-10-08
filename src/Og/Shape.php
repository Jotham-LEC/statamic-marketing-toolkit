<?php

namespace JothamLec\MarketingToolkit\Og;

/**
 * The sizes a share card is drawn in. Landscape is what Facebook, LinkedIn and X show, and the
 * page's og:image. Square and 4:3 are the other shapes Google asks for an article's images.
 * Each is served at the card's address with its suffix: /og/about.1x1.png, /og.4x3.png.
 */
enum Shape: string
{
    case Landscape = '1200x630';
    case Square = '1x1';
    case Classic = '4x3';

    public function width(): int
    {
        return 1200;
    }

    public function height(): int
    {
        return match ($this) {
            self::Landscape => 630,
            self::Square => 1200,
            self::Classic => 900,
        };
    }

    /**
     * Returns what follows the page's path in the card's address, before `.png`.
     */
    public function suffix(): string
    {
        return $this === self::Landscape ? '' : '.'.$this->value;
    }

    /**
     * Returns the shape a suffix names (`1x1`, `4x3`, without the dot), or Landscape for none.
     */
    public static function fromSuffix(?string $suffix): self
    {
        return $suffix === null || $suffix === '' ? self::Landscape : self::from($suffix);
    }
}
