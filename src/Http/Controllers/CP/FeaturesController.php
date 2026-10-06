<?php

namespace JothamLec\MarketingToolkit\Http\Controllers\CP;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use JothamLec\MarketingToolkit\Support\Edition;
use JothamLec\MarketingToolkit\Support\Features;
use Statamic\Facades\Addon;
use Statamic\Facades\Blueprint;
use Statamic\Facades\User;
use Statamic\Fields\Blueprint as BlueprintObject;

/**
 * Tools → SEO → Features (Pro): a switch per module, for whoever may change
 * the addon's settings. What's off is off from the next request.
 */
class FeaturesController
{
    public function index(): Response
    {
        $this->authorize();
        $off = Features::off();
        $fields = $this->blueprint()->fields()->addValues(collect(Features::MODULES)->map(fn ($config, string $module) => ! in_array($module, $off, true))->all())->preProcess();

        return Inertia::render('seo::Features', [
            'blueprint' => $this->blueprint()->toPublishArray(),
            'values' => $fields->values()->all(),
            'meta' => $fields->meta()->all(),
            'submitUrl' => cp_route('seo.features.update'),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorize();

        $on = $this->blueprint()->fields()->addValues($request->all())->process()->values();
        Features::save(array_values(array_filter(array_keys(Features::MODULES), fn (string $module) => ! $on->get($module, true))));

        return response()->json(['saved' => true]);
    }

    private function blueprint(): BlueprintObject
    {
        $toggle = fn (string $module) => ['handle' => $module, 'field' => [
            'type' => 'toggle',
            'display' => __('seo::cp.features.modules.'.$module.'.display'),
            'instructions' => __('seo::cp.features.modules.'.$module.'.instructions'),
            'default' => true,
            'width' => 50,
        ]];
        $section = fn (string $group, array $modules) => ['display' => __('seo::cp.features.groups.'.$group), 'fields' => array_map($toggle, $modules)];

        return Blueprint::make('seo_features')->setContents(['tabs' => ['main' => ['sections' => [
            $section('search', ['sitemap', 'robots_txt', 'llms_txt', 'hreflang', 'indexnow', 'share_cards']),
            $section('redirects', ['redirects', 'automatic_redirects', 'not_found', 'reports']),
            $section('marketing', ['tracking', 'leads', 'favicons', 'ads_txt']),
        ]]]]);
    }

    private function authorize(): void
    {
        abort_unless(Edition::pro() && User::current()?->can('editSettings', Addon::get(Edition::PACKAGE)), 403);
    }
}
