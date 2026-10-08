<?php

namespace JothamLec\MarketingToolkit\Support;

/**
 * Recognises the paths Statamic answers itself, outside the front end, which are the control
 * panel and its action routes (`/!/…`).
 */
final class StatamicRoutes
{
    public static function owns(string $path): bool
    {
        $prefixes = [config('statamic.cp.route', 'cp'), config('statamic.routes.action', '!')];

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
