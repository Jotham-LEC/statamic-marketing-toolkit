<?php

// scripts/release-notes.php turns a CHANGELOG section into the body of a GitHub
// Release, which the Statamic Marketplace shows as the version's release notes.

require_once __DIR__.'/../../scripts/release-notes.php';

const SAMPLE_CHANGELOG = <<<'MD'
# Changelog

## 2.1.0 – 2026-11-01

Faster reports and a fix for redirects.

### Added
- **Reports run twice as fast.** They now check two pages at a time,
  which halves the time a report takes.
  - A nested item stays in the CHANGELOG.

### Changed
- **The toolbar opens with a fixed shortcut.** It was editable before.

### Fixed
- **A redirect to `/a.b` works again.** It was ignored.
- A bullet without a bold lead keeps its first sentence. Not the second.

### Upgrading
- **Run `php artisan migrate`.** It adds an index.

## 2.0.0 – 2026-10-01

### Fixed
- **Something older.**
MD;

test('a version becomes Marketplace release notes, one line per item', function () {
    expect(releaseNotes(SAMPLE_CHANGELOG, 'v2.1.0'))->toBe(<<<'MD'
        Faster reports and a fix for redirects.

        - [new] Reports run twice as fast.
        - [new] The toolbar opens with a fixed shortcut.
        - [fix] A redirect to `/a.b` works again.
        - [fix] A bullet without a bold lead keeps its first sentence.

        **Upgrading:**

        - Run `php artisan migrate`.

        Full notes: https://github.com/Jotham-LEC/statamic-marketing-toolkit/blob/v2.1.0/CHANGELOG.md

        MD);
});

test('the last version in the file ends at the end of the file', function () {
    expect(releaseNotes(SAMPLE_CHANGELOG, '2.0.0'))->toStartWith("- [fix] Something older.\n");
});

test('a version the CHANGELOG lacks fails', function () {
    releaseNotes(SAMPLE_CHANGELOG, '2.2.0');
})->throws(RuntimeException::class, 'no section for 2.2.0');

test('a heading the Marketplace notes don\'t know fails, unless lenient', function () {
    $changelog = "## 1.0.0\n\n### Security\n- **Fixed a hole.**\n";

    expect(fn () => releaseNotes($changelog, '1.0.0'))->toThrow(RuntimeException::class, '"### Security"')
        ->and(releaseNotes($changelog, '1.0.0', lenient: true))->toStartWith("- [fix] Fixed a hole.\n");
});

test('every version from 0.24.0 on follows the CHANGELOG convention', function () {
    $changelog = file_get_contents(__DIR__.'/../../CHANGELOG.md');
    preg_match_all('/^## (\d+\.\d+\.\d+)/m', $changelog, $matches);

    foreach (array_filter($matches[1], fn (string $version) => version_compare($version, '0.24.0', '>=')) as $version) {
        expect(releaseNotes($changelog, $version))->toContain('Full notes:');
    }
})->throwsNoExceptions();
