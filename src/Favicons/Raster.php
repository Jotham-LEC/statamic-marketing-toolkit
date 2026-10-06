<?php

namespace JothamLec\MarketingToolkit\Favicons;

use GdImage;
use Imagick;
use ImagickPixel;
use Throwable;

/**
 * Draws an uploaded image as a square PNG of a given size: fitted whole,
 * centred, on transparency or a colour. Imagick when PHP has it (it also
 * reads SVG), else GD, which every PHP host has.
 */
class Raster
{
    public function canRead(string $bytes): bool
    {
        return ! $this->isSvg($bytes) || $this->imagickAvailable();
    }

    public function isSvg(string $bytes): bool
    {
        return str_contains(substr($bytes, 0, 1024), '<svg');
    }

    /**
     * A $size × $size PNG of the image, or null if it can't be read.
     *
     * @param  ?string  $background  `#rrggbb`, or null for transparent
     * @param  float  $padding  the share of each side left empty
     */
    public function square(string $bytes, int $size, ?string $background = null, float $padding = 0.0): ?string
    {
        try {
            return $this->imagickAvailable() ? $this->imagick($bytes, $size, $background, $padding) : $this->gd($bytes, $size, $background, $padding);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    protected function imagickAvailable(): bool
    {
        return extension_loaded('imagick');
    }

    private function imagick(string $bytes, int $size, ?string $background, float $padding): string
    {
        $image = new Imagick;
        $image->setBackgroundColor(new ImagickPixel('transparent'));

        if ($this->isSvg($bytes)) {
            // Read at a resolution that gives at least $size pixels, rather than scaling up a small drawing.
            $image->setResolution(max(96, $size), max(96, $size));
        }

        $image->readImageBlob($bytes);
        $image->setIteratorIndex(0);
        $inner = (int) round($size * (1 - 2 * $padding));
        $image->thumbnailImage($inner, $inner, true, false);

        $canvas = new Imagick;
        $canvas->newImage($size, $size, new ImagickPixel($background ?? 'transparent'));
        $canvas->compositeImage($image, Imagick::COMPOSITE_OVER, intdiv($size - $image->getImageWidth(), 2), intdiv($size - $image->getImageHeight(), 2));
        $canvas->setImageFormat('png32');

        return $canvas->getImageBlob();
    }

    private function gd(string $bytes, int $size, ?string $background, float $padding): ?string
    {
        $source = @imagecreatefromstring($bytes);

        if (! $source instanceof GdImage) {
            return null;
        }

        [$width, $height] = [imagesx($source), imagesy($source)];
        $inner = $size * (1 - 2 * $padding);
        $scale = min($inner / $width, $inner / $height);
        [$w, $h] = [max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale))];

        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        [$r, $g, $b] = $background ? sscanf($background, '#%02x%02x%02x') : [0, 0, 0];
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, (int) $r, (int) $g, (int) $b, $background ? 0 : 127));
        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $source, intdiv($size - $w, 2), intdiv($size - $h, 2), 0, 0, $w, $h, $width, $height);

        ob_start();
        imagepng($canvas);

        return (string) ob_get_clean();
    }
}
