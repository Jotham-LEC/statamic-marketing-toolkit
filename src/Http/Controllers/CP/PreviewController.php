<?php

namespace JothamLec\MarketingToolkit\Http\Controllers\CP;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JothamLec\MarketingToolkit\Og\Generator;
use JothamLec\MarketingToolkit\Preview\Draft;
use JothamLec\MarketingToolkit\Preview\MetaPayload;
use JothamLec\MarketingToolkit\Support\Features;
use JothamLec\MarketingToolkit\Support\Sites;
use Statamic\Contracts\Entries\Entry;

/**
 * Feeds the `mt_preview` fieldtype. Both actions read the publish form's
 * current values, so the preview follows the editor's typing before the
 * entry is saved, and run them through the same SiteSeo rules the page uses.
 */
class PreviewController
{
    public function meta(Request $request, MetaPayload $payload): JsonResponse
    {
        $content = Draft::fromRequest($request);

        // Worked out on the content's site: its brand values, its locale, its address.
        return response()->json(Sites::as($content->locale(), fn () => $payload->forContent($content)));
    }

    /**
     * The generated card for the form as it stands. Drawn every time and
     * never cached: each draft differs, and only saved entries go public.
     */
    public function card(Request $request, Generator $generator): Response
    {
        $content = Draft::fromRequest($request);

        abort_unless($content instanceof Entry && Features::on('share_cards') && $generator->available(), 404);

        $png = Sites::as($content->locale(), fn () => $generator->template($content)->image($generator->card($content))->toString());

        return new Response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store',
        ]);
    }
}
