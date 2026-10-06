<?php

namespace JothamLec\MarketingToolkit\Support;

use Statamic\Facades\Addon;
use Statamic\Facades\Blink;
use Throwable;

/**
 * The addon's edition: `free` (the default) or `pro`, as Statamic reads it
 * from `config/statamic/editions.php` (`'addons' => [PACKAGE => 'pro']`).
 * Pro adds several sites with hreflang, Consent Mode regions, leads and
 * their source, campaign links, Search Console, reports, generated
 * share images, automatic 301s, the 404 log, CSV import and export of
 * redirects, the Features switches and the dashboard widget.
 */
final class Edition
{
    public const string PACKAGE = 'jotham-lec/statamic-marketing-toolkit';

    /**
     * Read once (Blink), though a request asks many times: it is set in config.
     */
    public static function pro(): bool
    {
        return Blink::once('seo:edition', function () {
            try {
                return Addon::get(self::PACKAGE)?->edition() === 'pro';
            } catch (Throwable $exception) {
                // An edition the addon doesn't have. Logged, but never in the way of booting.
                rescue(fn () => report($exception), report: false);

                return false;
            }
        });
    }

    /**
     * Where to buy Pro: the addon's Marketplace page.
     */
    public static function marketplaceUrl(): string
    {
        return 'https://statamic.com/addons/jothamlec/marketing-toolkit';
    }
}
