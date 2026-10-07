<?php

use JothamLec\MarketingToolkit\Og\Card;
use JothamLec\MarketingToolkit\Og\DefaultTemplate;
use JothamLec\MarketingToolkit\Og\Generator;
use JothamLec\MarketingToolkit\Og\Template;
use SimonHamp\TheOg\Image;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blueprint;
use Statamic\Facades\GlobalSet;

beforeEach(fn () => seoGlobal(['og_background' => '#282828', 'og_text' => '#fbf1c7', 'og_accent' => '#fad03a']));

test('a published page has a card, served as a cacheable PNG without a cookie', function () {
    entryIn('pages', 'about', ['description' => 'Who we are.']);

    $response = $this->get('/og/about.png')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'max-age=2592000, public');

    expect(substr($response->getContent(), 0, 8))->toBe("\x89PNG\r\n\x1a\n")
        ->and(getimagesizefromstring($response->getContent())[0])->toBe(1200)
        ->and($response->headers->get('Set-Cookie'))->toBeNull();
})->skip(! extension_loaded('imagick'), 'Share cards need PHP\'s imagick extension.');

test('home has its card at /og.png; drafts and unknown pages have none', function () {
    entryIn('home', 'home');
    entryIn('pages', 'draft')->published(false)->save();

    $this->get('/og.png')->assertOk();
    $this->get('/og/draft.png')->assertNotFound();
    $this->get('/og/nothing.png')->assertNotFound();
})->skip(! extension_loaded('imagick'), 'Share cards need PHP\'s imagick extension.');

test('the card says the title and description, or the editor\'s card text instead', function () {
    $generator = app(Generator::class);

    $plain = $generator->card(entryIn('pages', 'about', ['description' => 'Who we are.']));
    $custom = $generator->card(entryIn('pages', 'team', ['seo' => ['og_title' => 'Meet us', 'og_subtitle' => 'Five people.']]));

    expect([$plain->title, $plain->description, $plain->label, $plain->background])->toBe(['About', 'Who we are.', 'Acme', '#282828'])
        ->and([$custom->title, $custom->description])->toBe(['Meet us', 'Five people.']);
});

test('the template comes from the collection, else the default', function () {
    config(['marketing-toolkit.og.templates.quiet' => QuietTemplate::class, 'marketing-toolkit.collections.essays.og_template' => 'quiet']);
    $generator = app(Generator::class);

    expect($generator->template(entryIn('pages', 'about')))->toBeInstanceOf(DefaultTemplate::class)
        ->and($generator->template(entryIn('essays', 'first', [], '2026-01-02')))->toBeInstanceOf(QuietTemplate::class);
});

test('a template key that names nothing falls back to the default; a missing default is a setup error', function () {
    config(['marketing-toolkit.collections.pages.og_template' => 'nope']);

    expect(app(Generator::class)->template(entryIn('pages', 'odd')))->toBeInstanceOf(DefaultTemplate::class);

    config(['marketing-toolkit.og.templates' => []]);

    app(Generator::class)->template(entryIn('pages', 'odder'));
})->throws(InvalidArgumentException::class, 'No share-card template is registered as [default]');

test('an uploaded share image replaces the card in the meta tags', function () {
    Blueprint::make('page')->setNamespace('collections.pages')->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
        ['handle' => 'title', 'field' => ['type' => 'text']],
        ['import' => 'marketing-toolkit::seo'],
    ]]]]]])->save();
    AssetContainer::find('assets')->disk()->put('share.png', file_get_contents(__DIR__.'/../fixtures/share.png'));
    $entry = entryIn('pages', 'about', ['seo' => ['image' => 'share.png']]);

    expect(metaFor(entryIn('pages', 'plain'))->image['url'])->toStartWith('https://example.test/og/plain.png?v=')
        ->and(metaFor($entry)->image['url'])->toStartWith('https://example.test/img/')
        ->and(metaFor($entry)->image['url'])->not->toContain('/og/');
});

test('an uploaded share image is served at the size the meta tags give, cropped on its focal point', function (?string $focus) {
    Blueprint::make('page')->setNamespace('collections.pages')->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
        ['handle' => 'title', 'field' => ['type' => 'text']],
        ['import' => 'marketing-toolkit::seo'],
    ]]]]]])->save();
    $container = AssetContainer::find('assets');
    $container->disk()->put('share.png', file_get_contents(__DIR__.'/../fixtures/share.png'));
    $container->makeAsset('share.png')->set('focus', $focus)->save();

    $image = metaFor(entryIn('pages', 'about', ['seo' => ['image' => 'share.png']]))->image;
    $served = $this->get($image['url'])->assertOk();

    // The 1600×900 fixture is wider than 1200×630: only a crop fills both sides.
    expect(getimagesizefromstring($served->streamedContent() ?: $served->getContent()))
        ->toMatchArray([0 => $image['width'], 1 => $image['height'], 'mime' => 'image/jpeg'])
        ->and([$image['width'], $image['height']])->toBe([1200, 630]);
})->with(['no focal point' => null, 'a focal point' => '20-70-1']);

test('cards can be turned off, leaving the site default', function () {
    config(['marketing-toolkit.og.enabled' => false]);

    expect(metaFor(entryIn('pages', 'about'))->image)->toBeNull();
});

class QuietTemplate extends Template
{
    public function image(Card $card): Image
    {
        return (new Image)->title($card->title);
    }
}

test('a field that takes several photos gives its first as the share image', function () {
    Blueprint::make('page')->setNamespace('collections.pages')->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
        ['handle' => 'title', 'field' => ['type' => 'text']],
        ['handle' => 'photos', 'field' => ['type' => 'assets', 'container' => 'assets']],
    ]]]]]])->save();
    config(['marketing-toolkit.collections.pages.image_fields' => ['photos']]);
    $disk = AssetContainer::find('assets')->disk();
    $disk->put('first.png', file_get_contents(__DIR__.'/../fixtures/share.png'));
    $disk->put('second.png', file_get_contents(__DIR__.'/../fixtures/share.png'));

    expect(metaFor(entryIn('pages', 'gallery', ['photos' => ['first.png', 'second.png']]))->image['url'])
        ->toStartWith('https://example.test/img/')
        ->toContain('/first.png');
});

test('a brand image field that takes several files gives its first', function () {
    config(['marketing-toolkit.og.enabled' => false]);
    Blueprint::make('seo')->setNamespace('globals')->setContents(['tabs' => ['brand' => ['sections' => [['fields' => [
        ['handle' => 'default_image', 'field' => ['type' => 'assets', 'container' => 'assets']],
    ]]]]]])->save();
    AssetContainer::find('assets')->disk()->put('brand.png', file_get_contents(__DIR__.'/../fixtures/share.png'));
    GlobalSet::findByHandle('seo')->in('default')->data(['default_image' => ['brand.png']])->save();

    expect(metaFor(entryIn('pages', 'plain'))->image['url'])->toContain('/brand.png');
});

test('a card subtitle typed on several lines is drawn as one paragraph', function () {
    $entry = entryIn('pages', 'launch', ['seo' => ['og_subtitle' => "Spring launch\n\n  now open "]]);

    expect(app(Generator::class)->card($entry)->description)->toBe('Spring launch now open');
});
