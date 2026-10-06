<?php

// What composer.json requires is what the README and Getting started say.

function composerJson(): array
{
    return json_decode(file_get_contents(__DIR__.'/../../composer.json'), true);
}

/** `^8.3` or `^6.31` as `8.3` or `6.31`. */
function minimum(string $constraint): string
{
    preg_match('/\d+\.\d+/', $constraint, $match);

    return $match[0];
}

test('the README and Getting started state the PHP and Statamic minimums', function (string $doc) {
    $text = file_get_contents(__DIR__.'/../../'.$doc);
    $require = composerJson()['require'];

    expect($text)->toContain('PHP '.minimum($require['php']).'+')
        ->toContain('Statamic '.minimum($require['statamic/cms']).'+');
})->with(['README.md', 'docs/getting-started.md']);

test('the README names every PHP extension required or suggested', function () {
    $composer = composerJson();
    $extensions = array_filter([...array_keys($composer['require']), ...array_keys($composer['suggest'])], fn (string $package) => str_starts_with($package, 'ext-'));
    $readme = file_get_contents(__DIR__.'/../../README.md');

    expect($extensions)->not->toBeEmpty();

    foreach ($extensions as $extension) {
        expect($readme)->toContain('`'.substr($extension, 4).'`');
    }
});
