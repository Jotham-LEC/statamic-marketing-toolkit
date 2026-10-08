<?php

namespace JothamLec\MarketingToolkit\Support;

use Facades\Statamic\CP\LivePreview;
use Illuminate\Http\Request;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Tokens\Handlers\LivePreview as LivePreviewHandler;
use WeakMap;

/**
 * Reads a field's stored value from an entry or a term the way the page shows it.
 *
 * In Live Preview, Statamic keeps the form's unsaved values on the entry as supplements, and
 * only augmented values read them, so the body shows the edits while value() and get() still
 * return what was saved. This reads the supplement for the previewed entry alone, as
 * augmentation does: a field cleared in the form reads as cleared. Anything else reads
 * value(), which falls back to a translation's origin.
 */
final class Fields
{
    /** @var WeakMap<Request, string|false>|null */
    private static ?WeakMap $previewed = null;

    public static function value(Entry|Term $content, string $handle): mixed
    {
        // Checking for the supplement first keeps the token lookup off every other read.
        if (method_exists($content, 'hasSupplement') && $content->hasSupplement($handle) && self::isPreviewed($content)) {
            return $content->getSupplement($handle);
        }

        return $content->value($handle);
    }

    /**
     * Determines whether this request is a Live Preview of the content. Statamic's
     * isLivePreviewOf() reads the token and the previewed item from storage on each call, so
     * the answer is kept for the request.
     */
    public static function isPreviewed(Entry|Term $content): bool
    {
        $request = request();
        self::$previewed ??= new WeakMap;

        if (! isset(self::$previewed[$request])) {
            $token = $request->statamicToken();
            $item = $token?->handler() === LivePreviewHandler::class ? LivePreview::item($token) : null;
            self::$previewed[$request] = is_object($item) && method_exists($item, 'reference') ? (string) $item->reference() : false;
        }

        return self::$previewed[$request] !== false && self::$previewed[$request] === $content->reference();
    }
}
