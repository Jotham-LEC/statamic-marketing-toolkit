<?php

namespace JothamLec\MarketingToolkit\Support;

use Composer\Semver\VersionParser;
use UnexpectedValueException;

/**
 * Version numbers as the update scripts are given them. Statamic passes them
 * normalized (0.21.0.0), and PHP's version_compare() calls 0.21.0 older than
 * 0.21.0.0, so both sides are normalized first. A branch (dev-main) is no
 * release: neither before nor after any version.
 */
final class Version
{
    public static function before(string $version, string $than): bool
    {
        return ($normal = self::normal($version)) !== null && version_compare($normal, (string) self::normal($than), '<');
    }

    public static function after(string $version, string $than): bool
    {
        return ($normal = self::normal($version)) !== null && version_compare($normal, (string) self::normal($than), '>');
    }

    public static function isRelease(string $version): bool
    {
        return self::normal($version) !== null;
    }

    private static function normal(string $version): ?string
    {
        try {
            $normal = (new VersionParser)->normalize($version);
        } catch (UnexpectedValueException) {
            return null;
        }

        return preg_match('/^\d/', $normal) && ! str_contains($normal, 'dev') ? $normal : null;
    }
}
