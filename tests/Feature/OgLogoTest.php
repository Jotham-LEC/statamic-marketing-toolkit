<?php

use Illuminate\Validation\ValidationException;
use JothamLec\MarketingToolkit\Og\Card;
use JothamLec\MarketingToolkit\Og\DefaultTemplate;
use JothamLec\MarketingToolkit\Og\Generator;
use JothamLec\MarketingToolkit\Og\LogoBox;
use JothamLec\MarketingToolkit\Og\Shape;
use JothamLec\MarketingToolkit\Og\Template;
use JothamLec\MarketingToolkit\UpdateScripts\AddNewBrandFields;
use SimonHamp\TheOg\Image;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;

beforeEach(function () {
    $disk = AssetContainer::find('assets')->disk();

    foreach (['logo-wide.png', 'logo-square.png', 'logo.svg'] as $file) {
        $disk->put($file, file_get_contents(__DIR__.'/../fixtures/'.$file));
    }

    seoGlobal(['og_background' => '#fafaf9', 'og_text' => '#111111', 'og_accent' => '#2563eb']);
});

/**
 * Pretends cards can be drawn, and records which entry and shape each card request asks for.
 */
function recordingGenerator(): Generator
{
    return tap(new class extends Generator
    {
        /** @var list<string> */
        public array $drawn = [];

        public function available(): bool
        {
            return true;
        }

        public function png(EntryContract $entry, Shape $shape = Shape::Landscape): string
        {
            $this->drawn[] = $entry->slug().' '.$shape->name;

            return 'png';
        }
    }, fn ($generator) => app()->instance(Generator::class, $generator));
}

test('the three shapes, their sizes and their URL suffixes', function () {
    expect(collect(Shape::cases())->map(fn (Shape $shape) => [$shape->width(), $shape->height(), $shape->suffix()])->all())
        ->toBe([[1200, 630, ''], [1200, 1200, '.1x1'], [1200, 900, '.4x3']])
        ->and(Shape::fromSuffix('1x1'))->toBe(Shape::Square)
        ->and(Shape::fromSuffix('4x3'))->toBe(Shape::Classic)
        ->and(Shape::fromSuffix(null))->toBe(Shape::Landscape);
});

test('the logo\'s box is 40% of the card\'s width and 12% of its height, inside the padding', function () {
    expect(LogoBox::area(Shape::Landscape))->toBe(['width' => 480, 'height' => 76])
        ->and(LogoBox::area(Shape::Square))->toBe(['width' => 480, 'height' => 144])
        ->and(LogoBox::area(Shape::Classic))->toBe(['width' => 480, 'height' => 108]);
});

test('a logo is fitted whole in its box, never enlarged', function (int $width, int $height, Shape $shape, array $size) {
    expect(LogoBox::fit($width, $height, $shape))->toBe(['x' => LogoBox::PADDING, 'y' => LogoBox::PADDING, ...$size]);
})->with([
    'wide, landscape' => [600, 100, Shape::Landscape, ['width' => 456, 'height' => 76]],
    'wide, square' => [600, 100, Shape::Square, ['width' => 480, 'height' => 80]],
    'wide, 4:3' => [600, 100, Shape::Classic, ['width' => 480, 'height' => 80]],
    'square, landscape' => [300, 300, Shape::Landscape, ['width' => 76, 'height' => 76]],
    'square, square' => [300, 300, Shape::Square, ['width' => 144, 'height' => 144]],
    'square, 4:3' => [300, 300, Shape::Classic, ['width' => 108, 'height' => 108]],
    'tall, landscape' => [100, 300, Shape::Landscape, ['width' => 25, 'height' => 76]],
    'tall, square' => [100, 300, Shape::Square, ['width' => 48, 'height' => 144]],
    'tall, 4:3' => [100, 300, Shape::Classic, ['width' => 36, 'height' => 108]],
    'small, kept at its size' => [200, 60, Shape::Landscape, ['width' => 200, 'height' => 60]],
]);

test('a logo at the bottom sits above the accent border', function () {
    expect(LogoBox::fit(300, 300, Shape::Landscape, 'bottom'))->toBe(['x' => 56, 'y' => 630 - 16 - 56 - 76, 'width' => 76, 'height' => 76])
        ->and(LogoBox::fit(300, 300, Shape::Square, 'bottom')['y'])->toBe(1200 - 16 - 56 - 144);
});

test('a logo that would draw shorter than the minimum gets no box, so the name stands in', function () {
    expect(LogoBox::minHeight(Shape::Landscape))->toBe(32)
        ->and(LogoBox::minHeight(Shape::Square))->toBe(61)
        ->and(LogoBox::fit(1000, 50, Shape::Landscape))->toBeNull()
        ->and(LogoBox::fit(1000, 50, Shape::Square))->toBeNull()
        ->and(LogoBox::fit(100, 30, Shape::Landscape))->toBeNull();
});

test('contrast is WCAG\'s; a logo below 3:1 against the card gets a pill in the text colour or its complement', function () {
    expect(round(LogoBox::ratio('#ffffff', '#000000'), 1))->toBe(21.0)
        ->and(round(LogoBox::ratio('#777777', '#ffffff'), 2))->toBe(4.48)
        ->and(LogoBox::pill([255, 255, 255], '#fafaf9', '#111111'))->toBe('#111111')
        ->and(LogoBox::pill([29, 53, 87], '#fafaf9', '#111111'))->toBeNull()
        // A dark logo on a dark card, with dark text: the text's complement contrasts more.
        ->and(LogoBox::pill([20, 20, 20], '#101010', '#222222'))->toBe('#dddddd');
});

test('the logo is the share-card Logo, else the publisher logo, else the site\'s name in type', function () {
    $generator = app(Generator::class);
    $entry = entryIn('pages', 'about');

    seoGlobal(['site_name' => 'Acme', 'publisher_logo' => ['logo-square.png'], 'og_logo' => ['logo-wide.png']]);
    expect($generator->card($entry)->logo)->toEndWith('logo-wide.png');

    seoGlobal(['site_name' => 'Acme', 'publisher_logo' => ['logo-square.png']]);
    expect($generator->card($entry)->logo)->toEndWith('logo-square.png');

    seoGlobal(['site_name' => 'Acme', 'title_brand' => 'Acme Co']);
    $card = $generator->card($entry);
    expect($card->logo)->toBeNull()
        ->and($card->brandText)->toBe('Acme Co')
        ->and($card->shape)->toBe(Shape::Landscape);
});

test('an SVG or a logo too wide to read is passed over', function () {
    $generator = app(Generator::class);
    $entry = entryIn('pages', 'about');
    AssetContainer::find('assets')->disk()->put('wordmark.png', (function () {
        $image = imagecreatetruecolor(1000, 50);
        ob_start();
        imagepng($image);

        return ob_get_clean();
    })());

    seoGlobal(['og_logo' => ['logo.svg'], 'publisher_logo' => ['logo-square.png']]);
    expect($generator->card($entry)->logo)->toEndWith('logo-square.png');

    seoGlobal(['og_logo' => ['wordmark.png']]);
    expect($generator->card($entry)->logo)->toBeNull();
});

test('the share-card Logo field refuses an SVG with a message naming the types it takes', function () {
    seoGlobal([]);
    $fields = Blueprint::find('globals.seo')->fields();

    expect($fields->get('og_logo')->type())->toBe('assets');

    try {
        $fields->addValues(['og_logo' => ['assets::logo.svg']])->validator()->validate();
        $this->fail('An SVG logo was accepted.');
    } catch (ValidationException $e) {
        expect($e->errors()['og_logo'][0])->toContain('png')->toContain('webp');
    }

    $fields->addValues(['og_logo' => ['assets::logo-wide.png']])->validator()->validate();
});

test('the Logo field comes with 0.25.0', function () {
    expect(AddNewBrandFields::FIELDS['0.25.0'])->toBe(['og_logo'])
        ->and(__('marketing-toolkit::fields.brand.og_logo.display'))->toBe('Logo');
});

test('the old card URLs answer as before, and the shapes answer at their suffix', function () {
    $generator = recordingGenerator();
    entryIn('home', 'home');
    entryIn('pages', 'about');
    // A route can give a page an address that ends like a shape's suffix.
    Collection::make('formats')->routes('{slug}.1x1')->save();
    entryIn('formats', 'ratio');

    $this->get('https://example.test/og.png')->assertOk();
    $this->get('https://example.test/og.1x1.png')->assertOk();
    $this->get('https://example.test/og/about.png')->assertOk();
    $this->get('https://example.test/og/about.4x3.png')->assertOk();
    $this->get('https://example.test/og/about.1x1.png')->assertOk();
    $this->get('https://example.test/og/ratio.1x1.png')->assertOk();
    $this->get('https://example.test/og/about.2x1.png')->assertNotFound();

    expect($generator->drawn)->toBe(['home Landscape', 'home Square', 'about Landscape', 'about Classic', 'about Square', 'ratio Landscape']);
});

test('a template that draws only the landscape card answers 404 for the other shapes', function () {
    config(['marketing-toolkit.og.templates.default' => LandscapeOnlyTemplate::class]);
    $generator = recordingGenerator();
    entryIn('pages', 'about');

    $this->get('https://example.test/og/about.png')->assertOk();
    $this->get('https://example.test/og/about.1x1.png')->assertNotFound();

    expect($generator->drawn)->toBe(['about Landscape']);
});

test('an article without an uploaded image lists the generated cards its template draws', function () {
    config(['marketing-toolkit.collections.essays' => ['schema' => 'Article']]);
    $entry = entryIn('essays', 'first', [], '2026-01-02');
    $v = '?v='.$entry->lastModified()->timestamp;

    expect(nodeOf(metaFor($entry), 'Article')['image'])->toBe([
        'https://example.test/og/essays/first.png'.$v,
        'https://example.test/og/essays/first.4x3.png'.$v,
        'https://example.test/og/essays/first.1x1.png'.$v,
    ])->and(metaFor($entry)->image['url'])->toBe('https://example.test/og/essays/first.png'.$v);

    config(['marketing-toolkit.og.templates.default' => LandscapeOnlyTemplate::class]);

    expect(nodeOf(metaFor($entry), 'Article')['image'])->toBe(['https://example.test/og/essays/first.png'.$v]);
});

test('each shape is drawn at its size, with the logo or the name in the brand box', function (?string $logo, Shape $shape) {
    seoGlobal(['site_name' => 'Acme', 'og_background' => '#fafaf9', 'og_text' => '#111111', 'og_accent' => '#2563eb', 'og_logo' => $logo ? [$logo] : null]);
    $entry = entryIn('pages', 'about', ['description' => 'Who we are.']);

    $png = app(Generator::class)->png($entry, $shape);
    [$width, $height] = getimagesizefromstring($png);

    $image = new Imagick;
    $image->readImageBlob($png);
    $box = LogoBox::area($shape);
    $differs = false;

    // Some pixel in the brand box isn't the background: the logo, its pill, or the name.
    for ($x = LogoBox::PADDING; $x < LogoBox::PADDING + $box['width'] && ! $differs; $x += 4) {
        for ($y = LogoBox::PADDING; $y < LogoBox::PADDING + $box['height'] && ! $differs; $y += 4) {
            $differs = strtolower($image->getImagePixelColor($x, $y)->getColorAsString()) !== 'srgb(250,250,249)';
        }
    }

    expect([$width, $height])->toBe([$shape->width(), $shape->height()])
        ->and($differs)->toBeTrue();
})->with(['a wide logo' => 'logo-wide.png', 'a square logo' => 'logo-square.png', 'no logo' => null])
    ->with([Shape::Landscape, Shape::Square, Shape::Classic])
    ->skip(! extension_loaded('imagick'), 'Share cards need PHP\'s imagick extension.');

test('the card URL with a shape suffix answers a PNG of that size', function () {
    entryIn('pages', 'about');

    expect(getimagesizefromstring($this->get('https://example.test/og/about.1x1.png')->assertOk()->getContent())[1])->toBe(1200)
        ->and(getimagesizefromstring($this->get('https://example.test/og/about.4x3.png')->assertOk()->getContent())[1])->toBe(900);
})->skip(! extension_loaded('imagick'), 'Share cards need PHP\'s imagick extension.');

test('a logo that can\'t be drawn leaves the name in its place, never a broken card', function () {
    AssetContainer::find('assets')->disk()->put('broken.png', "\x89PNG\r\n\x1a\nnot really");
    seoGlobal(['og_logo' => ['broken.png']]);

    $card = new Card('About', null, 'Acme', 'Acme', null, '#fafaf9', '#111111', '#2563eb', AssetContainer::find('assets')->disk()->path('broken.png'), 'Acme', Shape::Landscape);

    expect(getimagesizefromstring(app(DefaultTemplate::class)->image($card)->toString())[0])->toBe(1200);
})->skip(! extension_loaded('imagick'), 'Share cards need PHP\'s imagick extension.');

class LandscapeOnlyTemplate extends Template
{
    public function image(Card $card): Image
    {
        return (new Image)->title($card->title);
    }
}
