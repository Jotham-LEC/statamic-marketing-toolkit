<?php

use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\User;

describe('the publisher', function () {
    test('a Person has a job title and a portrait, but no organisation or business properties', function () {
        $person = publisherOf(['publisher_type' => 'Person', 'job_title' => 'Writer', 'area_served' => 'Perth', 'price_range' => '$$', 'founding_date' => '1998-01-01']);

        expect($person['@type'])->toBe('Person')
            ->and($person['jobTitle'])->toBe('Writer')
            ->and($person)->not->toHaveKeys(['areaServed', 'priceRange', 'foundingDate', 'logo', 'openingHoursSpecification']);
    });

    test('an organisation has its address, area, founding date and contact points, but no price range or hours', function () {
        $organization = publisherOf([
            'publisher_type' => ['EducationalOrganization'],
            'publisher_alternate_name' => 'BE',
            'founding_date' => '1998-03-01',
            'area_served' => 'Western Australia',
            'street_address' => '1 Hay St', 'address_locality' => 'Perth', 'address_country' => 'AU',
            'contact_points' => [['contact_type' => 'admissions', 'telephone' => '+61 8 0000 0000']],
            'price_range' => '$$',
            'opening_hours' => [['days' => ['Monday'], 'opens' => '09:00', 'closes' => '17:00']],
        ]);

        expect($organization['@type'])->toBe('EducationalOrganization')
            ->and($organization['alternateName'])->toBe('BE')
            ->and($organization['foundingDate'])->toBe('1998-03-01')
            ->and($organization['address'])->toBe(['@type' => 'PostalAddress', 'streetAddress' => '1 Hay St', 'addressLocality' => 'Perth', 'addressCountry' => 'AU'])
            ->and($organization['contactPoint'])->toBe([['@type' => 'ContactPoint', 'contactType' => 'admissions', 'telephone' => '+61 8 0000 0000']])
            ->and($organization)->not->toHaveKeys(['priceRange', 'openingHoursSpecification', 'jobTitle']);
    });

    test('a store, or two types with a local business, has hours, coordinates and a price range', function () {
        $store = publisherOf([
            'publisher_type' => ['EducationalOrganization', 'LocalBusiness'],
            'price_range' => '$$',
            'latitude' => '-31.95', 'longitude' => '115.86',
            'opening_hours' => [['days' => ['Monday', 'Tuesday'], 'opens' => '09:00', 'closes' => '17:00'], ['days' => [], 'opens' => '10:00']],
        ]);

        expect($store['@type'])->toBe(['EducationalOrganization', 'LocalBusiness'])
            ->and($store['priceRange'])->toBe('$$')
            ->and($store['geo'])->toBe(['@type' => 'GeoCoordinates', 'latitude' => -31.95, 'longitude' => 115.86])
            ->and($store['openingHoursSpecification'])->toBe([['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['Monday', 'Tuesday'], 'opens' => '09:00', 'closes' => '17:00']]);

        expect(publisherOf(['publisher_type' => ['GardenStore'], 'price_range' => '$'])['priceRange'])->toBe('$');
    });

    test('an organisation has its legal name, and several other names or areas as a list; one stays a text', function () {
        $organization = publisherOf([
            'publisher_type' => ['Organization'],
            'legal_name' => 'Acme Holdings Ltd',
            'publisher_alternate_name' => ['Acme', 'ACME Co'],
            'area_served' => ['Malaysia'],
            'founding_date' => '2014',
        ]);

        expect($organization['legalName'])->toBe('Acme Holdings Ltd')
            ->and($organization['alternateName'])->toBe(['Acme', 'ACME Co'])
            ->and($organization['areaServed'])->toBe('Malaysia')
            ->and($organization['foundingDate'])->toBe('2014')
            ->and(publisherOf(['publisher_type' => 'Person', 'legal_name' => 'Acme Holdings Ltd', 'area_served' => ['Perth']]))->not->toHaveKeys(['legalName', 'areaServed']);
    });

    test('a single type saved before types could be several still reads', function () {
        expect(publisherOf(['publisher_type' => 'LocalBusiness', 'price_range' => '$$'])['@type'])->toBe('LocalBusiness');
    });

    test('the site has its other name for search engines', function () {
        seoGlobal(['site_alternate_name' => 'Acme Co']);

        expect(nodeOf(metaFor(entryIn('pages', 'about')), 'WebSite')['alternateName'])->toBe('Acme Co');
    });
});

describe('the page', function () {
    test('names its share image as the page\'s main image, and the card says what it shows', function () {
        seoGlobal([]);
        $meta = metaFor(entryIn('pages', 'about'));

        expect(nodeOf($meta, 'WebPage')['primaryImageOfPage'])->toMatchArray(['@type' => 'ImageObject', 'url' => $meta->image['url'], 'width' => 1200, 'height' => 630])
            ->and($meta->image['alt'])->toBe('About');
    });

    test('the card\'s alt text is printed for X too', function () {
        seoGlobal([]);
        $entry = entryIn('pages', 'about');

        $this->get('https://example.test/about')->assertOk()
            ->assertSee('<meta property="og:image:alt" content="About">', false)
            ->assertSee('<meta name="twitter:image:alt" content="About">', false);
    });

    test('a profile page is about someone', function () {
        seoGlobal([]);
        config(['marketing-toolkit.collections.pages.page_schema' => 'ProfilePage']);

        expect(nodeOf(metaFor(entryIn('pages', 'jo')), 'ProfilePage')['mainEntity'])
            ->toMatchArray(['@type' => 'Person', 'name' => 'Jo', 'url' => 'https://example.test/jo']);
    });

    test('a page whose links aren\'t followed keeps its snippet and image previews', function () {
        seoGlobal([]);

        expect(metaFor(entryIn('pages', 'about', ['seo' => ['nofollow' => true]]))->robots)
            ->toBe('nofollow, max-snippet:-1, max-image-preview:large, max-video-preview:-1');
    });
});

describe('articles', function () {
    beforeEach(function () {
        seoGlobal([]);
        config(['marketing-toolkit.collections.essays.schema' => 'Article', 'marketing-toolkit.collections.essays.author_field' => 'authors']);
        Collection::make('people')->routes('people/{slug}')->save();
        Blueprint::make('essay')->setNamespace('collections.essays')->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'authors', 'field' => ['type' => 'entries', 'collections' => ['people']]],
            ['import' => 'marketing-toolkit::seo'],
        ]]]]]])->save();
    });

    test('are written by the people the collection names, else by the publisher', function () {
        $jo = entryIn('people', 'jo-tan', ['title' => 'Jo Tan']);

        $byJo = nodeOf(metaFor(entryIn('essays', 'first', ['authors' => [$jo->id()]], '2026-01-02')), 'Article');
        $unsigned = nodeOf(metaFor(entryIn('essays', 'second', [], '2026-01-03')), 'Article');

        expect($byJo['author'])->toBe([['@type' => 'Person', 'name' => 'Jo Tan', 'url' => 'https://example.test/people/jo-tan']])
            ->and($unsigned['author'])->toBe(['@id' => 'https://example.test/#publisher']);
    });

    test('can be written by users', function () {
        config(['marketing-toolkit.collections.essays.author_field' => 'writer']);
        Blueprint::make('essay')->setNamespace('collections.essays')->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'writer', 'field' => ['type' => 'users', 'max_items' => 1]],
        ]]]]]])->save();
        $user = tap(User::make()->email('jo@example.test')->data(['name' => 'Jo Tan']))->save();

        expect(nodeOf(metaFor(entryIn('essays', 'third', ['writer' => [$user->id()]], '2026-01-04')), 'Article')['author'])
            ->toBe([['@type' => 'Person', 'name' => 'Jo Tan']]);
    });

    test('carry an uploaded image in the three shapes Google asks for', function () {
        AssetContainer::find('assets')->disk()->put('wide.png', file_get_contents(__DIR__.'/../fixtures/share.png'));

        $images = nodeOf(metaFor(entryIn('essays', 'pictured', ['seo' => ['image' => 'wide.png']], '2026-01-05')), 'Article')['image'];

        expect($images)->toHaveCount(3)
            ->and($images[0])->toContain('w=1200&h=675')
            ->and($images[1])->toContain('w=1200&h=900')
            ->and($images[2])->toContain('w=1200&h=1200');
    });
});
