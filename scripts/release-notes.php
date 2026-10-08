<?php

/*
 * Prints the Statamic Marketplace's release notes for one version, from that
 * version's section of CHANGELOG.md.
 *
 *     php scripts/release-notes.php 0.24.0 > notes.md
 *
 * The Marketplace builds its Release Notes tab from each tag's GitHub Release
 * body, and keeps the body it first sees. A line that starts with `- [new]`
 * gets a "New" badge there, and one that starts with `- [fix]` a "Fix" badge.
 * Each bullet keeps only its bold lead sentence; the body ends with a link to
 * the full notes in the CHANGELOG.
 *
 * A version may only use the headings in RELEASE_NOTE_PREFIXES. With
 * --lenient, the headings of older versions are accepted too.
 */

const RELEASE_NOTE_PREFIXES = [
    'Added' => '[new]',
    'Changed' => '[new]',
    'Fixed' => '[fix]',
    'Upgrading' => null,
];

// Headings only versions before 0.24.0 used, for --lenient.
const OLD_RELEASE_NOTE_PREFIXES = [
    'Security' => '[fix]',
    'Removed' => '[new]',
    'Developers' => '[new]',
    'Docs' => '[new]',
];

const RELEASE_NOTE_REPOSITORY = 'https://github.com/Jotham-LEC/statamic-marketing-toolkit';

/**
 * Builds the release notes for $version from the text of a CHANGELOG.
 *
 * @throws RuntimeException when the CHANGELOG has no section for the version,
 *                          or the section uses a heading the Marketplace notes don't know
 */
function releaseNotes(string $changelog, string $version, bool $lenient = false): string
{
    $version = ltrim($version, 'v');
    $section = changelogSection($changelog, $version);
    $prefixes = $lenient ? RELEASE_NOTE_PREFIXES + OLD_RELEASE_NOTE_PREFIXES : RELEASE_NOTE_PREFIXES;

    $intro = [];
    $lines = [];
    $upgrading = [];
    $heading = null;

    foreach (preg_split('/\R/', $section) as $line) {
        if (preg_match('/^### (.+)$/', $line, $match)) {
            $heading = trim($match[1]);

            if (! array_key_exists($heading, $prefixes)) {
                throw new RuntimeException("CHANGELOG $version has a \"### $heading\" section. Use only: ".implode(', ', array_keys(RELEASE_NOTE_PREFIXES)).'.');
            }

            continue;
        }

        if ($heading === null) {
            if (trim($line) !== '') {
                $intro[] = trim($line);
            }

            continue;
        }

        // Only a bullet starts an item; its wrapped lines and sub-items stay in the CHANGELOG.
        if (! str_starts_with($line, '- ')) {
            continue;
        }

        $lead = leadSentence(substr($line, 2));

        if ($prefixes[$heading] === null) {
            $upgrading[] = "- $lead";
        } else {
            $lines[] = "- {$prefixes[$heading]} $lead";
        }
    }

    if ($lines === [] && $upgrading === []) {
        throw new RuntimeException("CHANGELOG $version has no items.");
    }

    $body = [];

    if ($intro !== []) {
        $body[] = implode(' ', $intro);
        $body[] = '';
    }

    array_push($body, ...$lines);

    if ($upgrading !== []) {
        if ($lines !== []) {
            $body[] = '';
        }

        $body[] = '**Upgrading:**';
        $body[] = '';
        array_push($body, ...$upgrading);
    }

    $body[] = '';
    $body[] = 'Full notes: '.RELEASE_NOTE_REPOSITORY."/blob/v$version/CHANGELOG.md";

    return implode("\n", $body)."\n";
}

/**
 * The text between "## $version" and the next version's heading.
 */
function changelogSection(string $changelog, string $version): string
{
    $pattern = '/^## '.preg_quote($version, '/').'(?:[ \t][^\n]*)?\n(.*?)(?=^## |\z)/ms';

    if (! preg_match($pattern, $changelog, $match)) {
        throw new RuntimeException("CHANGELOG.md has no section for $version.");
    }

    return trim($match[1]);
}

/**
 * The bold sentence a bullet starts with, without the bold. A bullet without
 * one keeps its first sentence.
 */
function leadSentence(string $bullet): string
{
    if (preg_match('/^\*\*(.+?)\*\*/', $bullet, $match)) {
        return trim($match[1]);
    }

    // The first full stop followed by a space ends the sentence, unless it is inside `code`.
    $inCode = false;

    for ($i = 0; $i < strlen($bullet); $i++) {
        if ($bullet[$i] === '`') {
            $inCode = ! $inCode;
        } elseif (! $inCode && $bullet[$i] === '.' && ($bullet[$i + 1] ?? ' ') === ' ') {
            return substr($bullet, 0, $i + 1);
        }
    }

    return trim($bullet);
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $arguments = array_values(array_filter(array_slice($argv, 1), fn ($argument) => $argument !== '--lenient'));
    $lenient = in_array('--lenient', $argv, true);

    if (count($arguments) !== 1) {
        fwrite(STDERR, "Usage: php scripts/release-notes.php [--lenient] <version>\n");
        exit(2);
    }

    try {
        echo releaseNotes(file_get_contents(dirname(__DIR__).'/CHANGELOG.md'), $arguments[0], $lenient);
    } catch (RuntimeException $exception) {
        fwrite(STDERR, $exception->getMessage()."\n");
        exit(1);
    }
}
