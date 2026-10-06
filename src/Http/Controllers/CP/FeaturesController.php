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
 * the addon's settings. What's off is off from the next request, or, in a
 * process that boots once (Octane, a queue worker), once it restarts:
 * Features::apply() runs at boot, before the routes and listeners register,
 * and can't take back what registered, so it isn't run again here.
 */
class FeaturesController
{
    public function index(): Response
    {
        $this->authorize();
        $off = [...Features::off(), ...Features::offInConfig()];
        $blueprint = $this->blueprint(Features::offInConfig());
        $fields = $blueprint->fields()->addValues(collect(Features::MODULES)->map(fn ($config, string $module) => ! in_array($module, $off, true))->all())->preProcess();

        return Inertia::render('seo::Features', [
            'blueprint' => $blueprint->toPublishArray(),
            'values' => $fields->values()->all(),
            'meta' => $fields->meta()->all(),
            'submitUrl' => cp_route('seo.features.update'),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorize();

        // A module the request leaves out keeps its state, as does one config/seo.php switches off.
        $off = Features::off();
        $locked = Features::offInConfig();
        Features::save(array_values(array_filter(
            array_keys(Features::MODULES),
            fn (string $module) => $request->has($module) && ! in_array($module, $locked, true) ? ! $request->boolean($module) : in_array($module, $off, true),
        )));

        return response()->json(['saved' => true]);
    }

    /**
     * @param  list<string>  $locked  the modules config/seo.php switches off
     */
    private function blueprint(array $locked = []): BlueprintObject
    {
        $toggle = fn (string $module) => ['handle' => $module, 'field' => [
            'type' => 'toggle',
            'display' => __('seo::cp.features.modules.'.$module.'.display'),
            'instructions' => __('seo::cp.features.modules.'.$module.'.instructions')
                .(in_array($module, $locked, true) ? ' '.__('seo::cp.features.off_in_config') : ''),
            'default' => true,
            'width' => 50,
            ...(in_array($module, $locked, true) ? ['visibility' => 'read_only'] : []),
        ]];
        $section = fn (string $group, array $modules, ?string $instructions = null) => ['display' => __('seo::cp.features.groups.'.$group), 'instructions' => $instructions, 'fields' => array_map($toggle, $modules)];

        // The intro rides on the first section: PublishForm draws its own header, with no slot above the form.
        return Blueprint::make('seo_features')->setContents(['tabs' => ['main' => ['sections' => [
            $section('search', ['sitemap', 'robots_txt', 'llms_txt', 'hreflang', 'indexnow', 'share_cards'], __('seo::cp.features.intro')),
            $section('redirects', ['redirects', 'automatic_redirects', 'not_found', 'reports']),
            $section('marketing', ['tracking', 'leads', 'favicons', 'ads_txt']),
        ]]]]);
    }

    private function authorize(): void
    {
        abort_unless(Edition::pro() && User::current()?->can('editSettings', Addon::get(Edition::PACKAGE)), 403);
    }
}
