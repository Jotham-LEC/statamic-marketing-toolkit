<?php

namespace JothamLec\MarketingToolkit\UpdateScripts;

use Composer\Semver\Comparator;
use Composer\Semver\VersionParser;
use Statamic\UpdateScripts\UpdateScript as StatamicUpdateScript;
use UnexpectedValueException;

/**
 * The addon's update scripts. Each one decides from the versions Statamic
 * passes to shouldUpdate(), not from Statamic's isUpdatingTo(), which reads
 * the composer.lock files again and so can't be tested with given versions.
 *
 * Statamic passes versions normalized (0.21.0.0); Composer's Comparator reads
 * 0.21.0 and 0.21.0.0 as the same version, which PHP's version_compare()
 * doesn't. A branch (dev-main) is no release: it is neither before nor after
 * any version, so no script runs for it.
 */
abstract class UpdateScript extends StatamicUpdateScript
{
    /**
     * Whether $version is a release, as opposed to a branch such as dev-main.
     */
    protected static function isRelease(string $version): bool
    {
        try {
            $normal = (new VersionParser)->normalize($version);
        } catch (UnexpectedValueException) {
            return false;
        }

        return preg_match('/^\d/', $normal) === 1 && ! str_contains($normal, 'dev');
    }

    /**
     * Whether $version is a release older than $than.
     */
    protected static function before(string $version, string $than): bool
    {
        return self::isRelease($version) && Comparator::lessThan($version, $than);
    }

    /**
     * Whether $version is a release newer than $than.
     */
    protected static function after(string $version, string $than): bool
    {
        return self::isRelease($version) && Comparator::greaterThan($version, $than);
    }
}
