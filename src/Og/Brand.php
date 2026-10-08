<?php

namespace JothamLec\MarketingToolkit\Og;

use JothamLec\MarketingToolkit\Favicons\Raster;
use JothamLec\MarketingToolkit\Settings;
use Statamic\Contracts\Assets\Asset;
use Throwable;

/**
 * Picks what stands for the brand on a card: the Share cards tab's Logo, else the publisher's
 * logo, else the name in page titles in type. A logo is passed over when it is an SVG (most
 * hosts' Imagick can't read one), when it can't be read, or when it would be drawn shorter
 * than LogoBox allows in this shape.
 */
class Brand
{
    public const array FIELDS = ['og_logo', 'publisher_logo'];

    public function __construct(private Settings $settings, private Raster $raster) {}

    /**
     * Returns the logo's local file, or null for the name in type.
     */
    public function logo(Shape $shape): ?string
    {
        foreach (self::FIELDS as $field) {
            $asset = $this->settings->asset($field);
            $path = $asset ? $this->localPath($asset) : null;
            $size = $path ? @getimagesize($path) : false;

            if ($size && LogoBox::fit($size[0], $size[1], $shape) !== null) {
                return $path;
            }
        }

        return null;
    }

    public function text(): string
    {
        return $this->settings->titleName();
    }

    /**
     * Returns the average colour of a logo's visible pixels, each weighed by how opaque it is,
     * or null for a logo without transparency, which brings its own background.
     *
     * @return array{int, int, int}|null
     */
    public function colour(string $path): ?array
    {
        $image = new \Imagick($path);
        $image->thumbnailImage(64, 64, true);

        if (! $image->getImageAlphaChannel()) {
            return null;
        }

        $pixels = $image->exportImagePixels(0, 0, $image->getImageWidth(), $image->getImageHeight(), 'RGBA', \Imagick::PIXEL_CHAR);
        $sum = [0, 0, 0];
        $weight = 0;
        $transparent = false;

        foreach (array_chunk($pixels, 4) as [$r, $g, $b, $a]) {
            $transparent = $transparent || $a < 255;
            $sum = [$sum[0] + $r * $a, $sum[1] + $g * $a, $sum[2] + $b * $a];
            $weight += $a;
        }

        if (! $transparent || $weight === 0) {
            return null;
        }

        return [(int) round($sum[0] / $weight), (int) round($sum[1] / $weight), (int) round($sum[2] / $weight)];
    }

    /**
     * Returns a path the-og can read the image from. An image on a disk that isn't local (S3,
     * say) is copied to the cache folder once per version of the file.
     */
    private function localPath(Asset $asset): ?string
    {
        try {
            $path = $asset->resolvedPath();

            if (! is_file($path)) {
                $copy = storage_path('framework/cache/mt-og/'.md5($asset->id().'@'.$asset->lastModified()->timestamp).'.'.$asset->extension());

                if (! is_file($copy)) {
                    @mkdir(dirname($copy), 0755, true);
                    file_put_contents($copy, (string) $asset->contents());
                }

                $path = $copy;
            }

            $head = (string) file_get_contents($path, length: 1024);

            return $this->raster->isSvg($head) ? null : $path;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
