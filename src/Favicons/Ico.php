<?php

namespace JothamLec\MarketingToolkit\Favicons;

/**
 * A favicon.ico holding PNG images, as every browser since IE 11 reads it:
 * a 6-byte header, a 16-byte entry per image, then the PNGs themselves.
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
            // 0 stands for 256 in the one-byte width and height.
            $side = $size >= 256 ? 0 : $size;
            $entries .= pack('CCCCvvVV', $side, $side, 0, 0, 1, 32, strlen($png), $offset + strlen($data));
            $data .= $png;
        }

        return $header.$entries.$data;
    }
}
