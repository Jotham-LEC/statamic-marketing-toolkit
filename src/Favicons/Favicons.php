<?php

namespace JothamLec\MarketingToolkit\Favicons;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use JothamLec\MarketingToolkit\Settings;
use Statamic\Contracts\Assets\Asset;
use Statamic\Facades\Site;

/**
 * The site's icons, made from one image in the SEO & brand global (`favicon`):
 * favicon.ico, the SVG as it is, the Apple touch icon, two PNGs for
 * site.webmanifest, and the manifest itself, named after the site in the
 * brand's colours. Made once per version of the image and colours, kept in
 * storage/app/marketing-toolkit/favicons/{site}, and served by
 * Http\Controllers\FaviconController without a session.
 */
class Favicons
{
    /** Address => content type. */
    public const array FILES = [
        'favicon.ico' => 'image/x-icon',
        'favicon.svg' => 'image/svg+xml',
        'apple-touch-icon.png' => 'image/png',
        'icon-192.png' => 'image/png',
        'icon-512.png' => 'image/png',
        'site.webmanifest' => 'application/manifest+json',
    ];

    public function __construct(protected Settings $settings, protected Raster $raster) {}

    public function source(): ?Asset
    {
        return $this->settings->asset('favicon');
    }

    /**
     * What the icons are made from, as a short hash: a new image, or new
     * colours or name, is a new version.
     */
    public function version(): ?string
    {
        $source = $this->source();

        return $source === null ? null : substr(md5(implode('|', [
            $source->id(), $source->size(), $source->lastModified()->timestamp, $this->themeColor(), $this->backgroundColor(), $this->settings->siteName(),
        ])), 0, 10);
    }

    /**
     * A file's bytes, made first if need be; null when there is no image or
     * this file can't be made from it (an SVG on a host without Imagick
     * becomes favicon.svg alone).
     */
    public function file(string $name): ?string
    {
        $directory = $this->directory();

        if ($directory === null || ! array_key_exists($name, self::FILES)) {
            return null;
        }

        if (! File::exists($directory.'/.made')) {
            $this->make($directory);
        }

        $path = $directory.'/'.$name;

        return File::exists($path) ? File::get($path) : null;
    }

    /**
     * The <link> tags, each with the version, so browsers fetch new icons.
     *
     * @return list<array{rel: string, href: string, type?: string, sizes?: string}>
     */
    public function links(): array
    {
        $version = $this->version();

        if ($version === null) {
            return [];
        }

        $href = fn (string $name) => '/'.$name.'?v='.$version;
        $has = fn (string $name) => $this->file($name) !== null;

        return array_values(array_filter([
            $has('favicon.ico') ? ['rel' => 'icon', 'href' => $href('favicon.ico'), 'sizes' => '32x32'] : null,
            $has('favicon.svg') ? ['rel' => 'icon', 'href' => $href('favicon.svg'), 'type' => 'image/svg+xml'] : null,
            $has('apple-touch-icon.png') ? ['rel' => 'apple-touch-icon', 'href' => $href('apple-touch-icon.png')] : null,
            ['rel' => 'manifest', 'href' => $href('site.webmanifest')],
        ]));
    }

    public function themeColor(): ?string
    {
        return $this->color('theme_color');
    }

    /**
     * Behind the Apple touch icon and the manifest's splash screen; white unless set.
     */
    public function backgroundColor(): string
    {
        return $this->color('background_color') ?? '#ffffff';
    }

    /**
     * Forgets the icons made for every site, so the next request makes them again.
     */
    public function flush(): void
    {
        File::deleteDirectory(self::root());
    }

    public static function root(): string
    {
        return storage_path('app/marketing-toolkit/favicons');
    }

    protected function directory(): ?string
    {
        $version = $this->version();

        return $version === null ? null : self::root().'/'.Site::current()->handle().'/'.$version;
    }

    protected function make(string $directory): void
    {
        $source = (string) $this->source()?->contents();
        $files = [];

        if ($this->raster->isSvg($source)) {
            $files['favicon.svg'] = $source;
        }

        if ($this->raster->canRead($source)) {
            $pngs = array_filter(array_map(fn (int $size) => $this->raster->square($source, $size), [16 => 16, 32 => 32, 48 => 48]));
            $files['favicon.ico'] = $pngs === [] ? null : Ico::fromPngs($pngs);
            // iOS shows transparency as black: on the brand's background, with a margin.
            $files['apple-touch-icon.png'] = $this->raster->square($source, 180, $this->backgroundColor(), 0.08);
            $files['icon-192.png'] = $this->raster->square($source, 192);
            $files['icon-512.png'] = $this->raster->square($source, 512);
        }

        $files = array_filter($files);
        $files['site.webmanifest'] = json_encode($this->manifest($files), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Into a fresh directory, then moved into place, so a request never reads half the files.
        File::ensureDirectoryExists(dirname($directory));
        $temporary = $directory.'.'.Str::random(8);
        File::ensureDirectoryExists($temporary);

        foreach ($files as $name => $bytes) {
            File::put($temporary.'/'.$name, $bytes);
        }

        File::put($temporary.'/.made', '');

        if (! File::exists($directory)) {
            File::moveDirectory($temporary, $directory);
        }

        File::deleteDirectory($temporary);
    }

    /**
     * @param  array<string, string>  $files
     * @return array<string, mixed>
     */
    protected function manifest(array $files): array
    {
        $name = $this->settings->siteName();

        return array_filter([
            'name' => $name,
            'short_name' => $this->settings->string('site_alternate_name') ?? Str::limit($name, 12, ''),
            'icons' => array_values(array_filter([
                isset($files['icon-192.png']) ? ['src' => '/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'] : null,
                isset($files['icon-512.png']) ? ['src' => '/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'] : null,
                isset($files['favicon.svg']) ? ['src' => '/favicon.svg', 'sizes' => 'any', 'type' => 'image/svg+xml'] : null,
            ])),
            'theme_color' => $this->themeColor(),
            'background_color' => $this->backgroundColor(),
            'display' => 'browser',
            'start_url' => '/',
        ]);
    }

    private function color(string $field): ?string
    {
        $value = strtolower(trim((string) $this->settings->string($field)));

        return preg_match('/^#[0-9a-f]{6}$/', $value) ? $value : null;
    }
}
