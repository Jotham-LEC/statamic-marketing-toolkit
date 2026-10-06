<?php

use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;

beforeEach(function () {
    Collection::make('baskets')->routes('basket/{slug}')->save();
    Blueprint::make('basket')->setNamespace('collections.baskets')->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
        ['handle' => 'title', 'field' => ['type' => 'text']],
        ['handle' => 'price', 'field' => ['type' => 'float']],
        ['handle' => 'in_stock', 'field' => ['type' => 'toggle']],
        ['handle' => 'code', 'field' => ['type' => 'text']],
    ]]]]]])->save();
    config(['seo.collections.baskets.product' => ['price_field' => 'price', 'availability_field' => 'in_stock', 'sku_field' => 'code', 'brand' => 'Acme']]);
});

test('a product has its price in the shop\'s currency, availability, SKU and brand', function () {
    seoGlobal(['currency' => 'MYR']);

    $product = nodeOf(metaFor(entryIn('baskets', 'joy', ['price' => 189.5, 'in_stock' => false, 'code' => 'J-1'])), 'Product');

    expect($product)->toMatchArray([
        'name' => 'Joy',
        'sku' => 'J-1',
        'brand' => ['@type' => 'Brand', 'name' => 'Acme'],
        'offers' => [
            '@type' => 'Offer',
            'url' => 'https://example.test/basket/joy',
            'price' => 189.5,
            'priceCurrency' => 'MYR',
            'availability' => 'https://schema.org/OutOfStock',
            'itemCondition' => 'https://schema.org/NewCondition',
        ],
    ])->and($product['image'])->not->toBeEmpty();
});

test('there is no product without a price above zero or a currency', function () {
    seoGlobal(['currency' => 'MYR']);
    expect(nodeOf(metaFor(entryIn('baskets', 'free', ['price' => 0])), 'Product'))->toBeNull();

    seoGlobal([]);
    expect(nodeOf(metaFor(entryIn('baskets', 'priced', ['price' => 10])), 'Product'))->toBeNull();
});

test('the shop\'s return and shipping policies are the publisher\'s, for every product', function () {
    $publisher = publisherOf([
        'publisher_type' => ['Store'],
        'currency' => 'MYR',
        'return_category' => 'MerchantReturnFiniteReturnWindow', 'return_days' => 7, 'return_country' => 'MY',
        'shipping_rates' => [
            ['country' => 'MY', 'region' => 'Selangor', 'max_order' => 300, 'rate' => 20, 'min_days' => 1, 'max_days' => 2],
            ['country' => 'MY', 'min_order' => 300, 'rate' => 0],
        ],
    ]);

    expect($publisher['hasMerchantReturnPolicy'])->toBe([
        '@type' => 'MerchantReturnPolicy',
        'applicableCountry' => 'MY',
        'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
        'merchantReturnDays' => 7,
    ])->and($publisher['hasShippingService']['shippingConditions'])->toBe([
        [
            '@type' => 'ShippingConditions',
            'shippingDestination' => ['@type' => 'DefinedRegion', 'addressCountry' => 'MY', 'addressRegion' => 'Selangor'],
            'orderValue' => ['@type' => 'MonetaryAmount', 'maxValue' => 300.0, 'currency' => 'MYR'],
            'shippingRate' => ['@type' => 'MonetaryAmount', 'value' => 20.0, 'currency' => 'MYR'],
            'transitTime' => ['@type' => 'ServicePeriod', 'duration' => ['@type' => 'QuantitativeValue', 'minValue' => 1, 'maxValue' => 2, 'unitCode' => 'DAY']],
        ],
        [
            '@type' => 'ShippingConditions',
            'shippingDestination' => ['@type' => 'DefinedRegion', 'addressCountry' => 'MY'],
            'orderValue' => ['@type' => 'MonetaryAmount', 'minValue' => 300.0, 'currency' => 'MYR'],
            'shippingRate' => ['@type' => 'MonetaryAmount', 'value' => 0.0, 'currency' => 'MYR'],
        ],
    ]);
});

test('a return policy can be just its page', function () {
    expect(publisherOf(['return_policy_link' => 'https://example.test/returns'])['hasMerchantReturnPolicy'])
        ->toBe(['@type' => 'MerchantReturnPolicy', 'merchantReturnLink' => 'https://example.test/returns']);
});
