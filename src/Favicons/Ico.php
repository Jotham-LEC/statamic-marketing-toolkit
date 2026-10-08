<?php

namespace JothamLec\MarketingToolkit\Favicons;

/**
 * Builds a favicon.ico that holds PNG images, which every browser since IE 11 reads. It has
 * a 6-byte header, a 16-byte entry per image, and then the PNGs themselves.
 */
final class Ico
{
    /**
     * @param  array<int, string>  $pngs  size in pixels => PNG bytes
     */
    public static function fromPngs(array $pngs): string
    {
        ksort($pngs);
        $header = pack('vvv', 0, 1, count($pngs));
        $entries = '';
        $data = '';
        $offset = 6 + 16 * count($pngs);

        foreach ($pngs as $size => $png) {
            // A 0 stands for 256 in the one-byte width and height.
            $side = $size >= 256 ? 0 : $size;
            $entries .= pack('CCCCvvVV', $side, $side, 0, 0, 1, 32, strlen($png), $offset + strlen($data));
            $data .= $png;
        }

        return $header.$entries.$data;
    }
}
