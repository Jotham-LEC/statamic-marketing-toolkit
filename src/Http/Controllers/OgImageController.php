<?php

namespace JothamLec\Seo\Http\Controllers;

use Illuminate\Http\Response;
use JothamLec\Seo\Og\Generator;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Structures\Page;

/**
 * /og.png (home) and /og/{uri}.png: the generated share card of a published
 * entry. The meta tags only point here when the entry has no uploaded share
 * image, but the card is served either way so editors can preview it.
 */
class OgImageController
{
    public function __invoke(Generator $generator, ?string $path = null): Response
    {
        $entry = Entry::findByUri('/'.trim((string) $path, '/'), Site::current()->handle());
        $entry = $entry instanceof Page ? $entry->entry() : $entry;

        abort_unless($entry?->status() === 'published', 404);

        return new Response($generator->png($entry), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age='.(int) config('seo.og.max_age'),
        ]);
    }
}
