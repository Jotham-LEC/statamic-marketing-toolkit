<?php

namespace JothamLec\Seo\Support;

/**
 * Paths Statamic answers itself, outside the front end: the control panel,
 * its action routes (`/!/…`) and, if asked, Glide's images.
 */
final class StatamicRoutes
{
    public static function owns(string $path, bool $images = false): bool
    {
        $prefixes = [config('statamic.cp.route', 'cp'), config('statamic.routes.action', '!')];

        if ($images) {
            $prefixes[] = config('statamic.assets.image_manipulation.route', 'img');
        }

        $path = '/'.ltrim($path, '/');

        foreach ($prefixes as $prefix) {
            $prefix = '/'.trim((string) $prefix, '/');

            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
