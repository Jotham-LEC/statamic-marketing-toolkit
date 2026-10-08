<?php

namespace JothamLec\MarketingToolkit\Og;

/**
 * Works out where a logo sits on a card, and whether it needs a backing to be seen. These are
 * plain sums, so a template of your own can place a logo the same way.
 *
 * The box is 40% of the card's width and 12% of its height, inside the padding, at the top left
 * (or the bottom left). A logo is fitted whole in it, keeping its shape, and never drawn larger
 * than its own pixels. A logo that would come out shorter than 32 pixels on a 630-pixel card
 * (more on taller cards), such as a very wide wordmark, isn't drawn: the brand's name is.
 */
final class LogoBox
{
    /** The space between the card's edge and its content. */
    public const int PADDING = 56;

    /** The accent border along the bottom of the default card. */
    public const int BORDER = 16;

    /** WCAG's minimum contrast for graphics: a logo below it against the card gets a pill. */
    public const float CONTRAST = 3.0;

    /**
     * @return array{width: int, height: int}
     */
    public static function area(Shape $shape): array
    {
        return ['width' => (int) round($shape->width() * 0.4), 'height' => (int) round($shape->height() * 0.12)];
    }

    public static function minHeight(Shape $shape): int
    {
        return (int) round(32 * $shape->height() / 630);
    }

    /**
     * Fits a logo of $width × $height pixels in the shape's box. It returns where to draw it and
     * at what size, or null when it would be drawn shorter than the minimum.
     *
     * @param  'top'|'bottom'|string  $position
     * @return array{x: int, y: int, width: int, height: int}|null
     */
    public static function fit(int $width, int $height, Shape $shape, string $position = 'top'): ?array
    {
        $area = self::area($shape);

        if ($width < 1 || $height < 1) {
            return null;
        }

        $scale = min($area['width'] / $width, $area['height'] / $height, 1);
        $fitted = ['width' => (int) round($width * $scale), 'height' => (int) round($height * $scale)];

        if ($fitted['height'] < self::minHeight($shape)) {
            return null;
        }

        $y = $position === 'bottom' ? $shape->height() - self::BORDER - self::PADDING - $fitted['height'] : self::PADDING;

        return ['x' => self::PADDING, 'y' => $y, ...$fitted];
    }

    /**
     * Returns the colour of a pill to draw the logo on, or null when the logo stands out from
     * the card's background by 3:1 or more. The pill is the card's text colour or its
     * complement, whichever stands out more from the logo.
     *
     * @param  array{int, int, int}|string  $logo  the logo's average colour
     */
    public static function pill(array|string $logo, string $background, string $text): ?string
    {
        if (self::ratio($logo, $background) >= self::CONTRAST) {
            return null;
        }

        $complement = self::hex(array_map(fn (int $channel) => 255 - $channel, self::rgb($text)));

        return self::ratio($logo, $text) >= self::ratio($logo, $complement) ? self::hex(self::rgb($text)) : $complement;
    }

    /**
     * Returns the contrast ratio of two colours, from 1 to 21, as WCAG defines it.
     *
     * @param  array{int, int, int}|string  $a
     * @param  array{int, int, int}|string  $b
     */
    public static function ratio(array|string $a, array|string $b): float
    {
        [$light, $dark] = [max(self::luminance($a), self::luminance($b)), min(self::luminance($a), self::luminance($b))];

        return ($light + 0.05) / ($dark + 0.05);
    }

    /**
     * Returns a colour's relative luminance, as WCAG defines it.
     *
     * @param  array{int, int, int}|string  $colour
     */
    public static function luminance(array|string $colour): float
    {
        $linear = array_map(function (int $channel) {
            $value = $channel / 255;

            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, self::rgb($colour));

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }

    /**
     * Reads `#rgb` or `#rrggbb` (or an RGB list as it is). Anything else reads as black.
     *
     * @param  array{int, int, int}|string  $colour
     * @return array{int, int, int}
     */
    public static function rgb(array|string $colour): array
    {
        if (is_array($colour)) {
            return [(int) $colour[0], (int) $colour[1], (int) $colour[2]];
        }

        $hex = ltrim(trim($colour), '#');
        $hex = strlen($hex) === 3 ? $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2] : $hex;

        if (! preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            return [0, 0, 0];
        }

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }

    /**
     * @param  array{int, int, int}  $rgb
     */
    private static function hex(array $rgb): string
    {
        return sprintf('#%02x%02x%02x', ...$rgb);
    }
}
