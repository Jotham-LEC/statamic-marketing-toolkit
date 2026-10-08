<?php

use Illuminate\Support\Facades\File;
use JothamLec\MarketingToolkit\Favicons\Favicons;
use JothamLec\MarketingToolkit\Favicons\Raster;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\GlobalSet;

const ICON_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10" fill="#c00"/></svg>';

beforeEach(fn () => app(Favicons::class)->flush());

function iconGlobal(string $file, string $bytes, array $values = []): void
{
    AssetContainer::find('assets')->disk()->put($file, $bytes);
    seoGlobal(['favicon' => $file, ...$values]);
}

/** Where the icons are kept, as Favicons keeps them. */
function faviconsRoot(): string
{
    return storage_path('app/marketing-toolkit/favicons');
}

function pngSize(string $bytes): array
{
    return array_slice(getimagesizefromstring($bytes), 0, 2);
}

test('without an icon there are no icon files or links', function () {
    seoGlobal([]);

    $this->get('https://example.test/favicon.ico')->assertNotFound();
    $this->get('https://example.test/site.webmanifest')->assertNotFound();
    expect(renderAt('/', '<s:mt:head />'))->not->toContain('rel="icon"');
});

test('a PNG makes favicon.ico, the touch icon, two manifest icons and the manifest, served without a session', function () {
    iconGlobal('icon.png', file_get_contents(__DIR__.'/../fixtures/share.png'), ['theme_color' => '#112233', 'background_color' => '#FFEEDD', 'site_alternate_name' => 'Acme']);

    $ico = $this->get('https://example.test/favicon.ico')->assertOk()->assertHeader('Content-Type', 'image/x-icon')->assertHeaderMissing('Set-Cookie');
    $bytes = $ico->getContent();
    expect(unpack('vreserved/vtype/vcount', substr($bytes, 0, 6)))->toBe(['reserved' => 0, 'type' => 1, 'count' => 3])
        // The first entry: 16 × 16, its PNG where the entry says.
        ->and(unpack('Cwidth/Cheight', substr($bytes, 6, 2)))->toBe(['width' => 16, 'height' => 16])
        ->and(substr($bytes, unpack('V', substr($bytes, 6 + 12, 4))[1], 8))->toBe("\x89PNG\r\n\x1a\n");

    expect(pngSize($this->get('https://example.test/apple-touch-icon.png')->assertOk()->getContent()))->toBe([180, 180])
        ->and(pngSize($this->get('https://example.test/icon-192.png')->getContent()))->toBe([192, 192])
        ->and(pngSize($this->get('https://example.test/icon-512.png')->getContent()))->toBe([512, 512]);

    $this->get('https://example.test/favicon.svg')->assertNotFound();
    $this->get('https://example.test/site.webmanifest')->assertOk()->assertHeader('Content-Type', 'application/manifest+json')->assertJson([
        'name' => 'Acme',
        'short_name' => 'Acme',
        'theme_color' => '#112233',
        'background_color' => '#ffeedd',
        'icons' => [['src' => '/icon-192.png', 'sizes' => '192x192'], ['src' => '/icon-512.png', 'sizes' => '512x512']],
    ]);

    $version = app(Favicons::class)->version();
    expect(renderAt('/', '<s:mt:head />'))
        ->toContain('<link rel="icon" href="/favicon.ico?v='.$version.'" sizes="32x32">')
        ->toContain('<link rel="apple-touch-icon" href="/apple-touch-icon.png?v='.$version.'">')
        ->toContain('<link rel="manifest" href="/site.webmanifest?v='.$version.'">')
        ->toContain('<meta name="theme-color" content="#112233">')
        ->and(renderAt('/', '<s:mt:meta />'))->not->toContain('rel="icon"')
        ->and(renderAt('/', '<s:mt:favicons />'))->toContain('rel="apple-touch-icon"');
});

test('an SVG is served as it is, unable to run script, and drawn as the PNGs with Imagick', function () {
    iconGlobal('icon.svg', ICON_SVG);

    $this->get('https://example.test/favicon.svg')->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'")
        ->assertContent(ICON_SVG);

    expect(renderAt('/', '<s:mt:favicons />'))->toContain('type="image/svg+xml"');

    if (extension_loaded('imagick')) {
        expect(pngSize($this->get('https://example.test/icon-512.png')->assertOk()->getContent()))->toBe([512, 512]);
    }
});

test('without Imagick, GD draws a PNG, and an SVG becomes favicon.svg alone', function () {
    app()->bind(Raster::class, fn () => new class extends Raster
    {
        protected function imagickAvailable(): bool
        {
            return false;
        }
    });

    // Both on disk before the first request: the container's file list is cached once read.
    AssetContainer::find('assets')->disk()->put('icon.svg', ICON_SVG);
    iconGlobal('icon.png', file_get_contents(__DIR__.'/../fixtures/share.png'));
    expect(pngSize($this->get('https://example.test/apple-touch-icon.png')->assertOk()->getContent()))->toBe([180, 180]);

    seoGlobal(['favicon' => 'icon.svg']);
    $this->get('https://example.test/favicon.svg')->assertOk();
    $this->get('https://example.test/favicon.ico')->assertNotFound();
    $this->get('https://example.test/site.webmanifest')->assertOk()->assertJsonPath('icons.0.src', '/favicon.svg');
    expect(renderAt('/', '<s:mt:favicons />'))->not->toContain('apple-touch-icon')->toContain('rel="manifest"');
});

test('saving SEO & brand makes the icons again, under a new version', function () {
    iconGlobal('icon.png', file_get_contents(__DIR__.'/../fixtures/share.png'), ['background_color' => '#000000']);
    $before = app(Favicons::class)->version();
    $this->get('https://example.test/apple-touch-icon.png')->assertOk();

    GlobalSet::findByHandle('seo')->in('default')->set('background_color', '#ffffff')->save();
    $after = app(Favicons::class)->version();

    expect($after)->not->toBe($before)
        ->and(File::exists(faviconsRoot().'/default/'.$before))->toBeFalse()
        // Made on save, before anyone asks.
        ->and(File::exists(faviconsRoot().'/default/'.$after.'/apple-touch-icon.png'))->toBeTrue();
});

test('the icon links on a page never read the icon files', function () {
    iconGlobal('icon.png', file_get_contents(__DIR__.'/../fixtures/share.png'));
    // The first page makes the icons.
    expect(app(Favicons::class)->links())->toHaveCount(3);

    File::partialMock()->shouldNotReceive('get');

    expect(app(Favicons::class)->links())->toHaveCount(3);
});
