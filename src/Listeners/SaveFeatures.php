<?php

namespace JothamLec\MarketingToolkit\Listeners;

use JothamLec\MarketingToolkit\Support\Features;
use Statamic\Contracts\Globals\GlobalSet;
use Statamic\Events\GlobalVariablesSaved;
use Statamic\Facades\Site;

/**
 * The Features tab of Marketing settings, saved: its switches go to the
 * addon's settings (`features_off`), which Features::apply() reads at boot,
 * so a request never reads a global set to know what is on. Only the default
 * site's localization has the tab (ShowFeaturesTab). A module that
 * config/marketing-toolkit.php switches off stays as it was.
 */
class SaveFeatures
{
    public function handle(GlobalVariablesSaved $event): void
    {
        $variables = $event->variables;

        if ($variables->handle() !== config('marketing-toolkit.settings_global') || $variables->locale() !== Site::default()->handle()) {
            return;
        }

        $data = $variables->data();

        // Saved before the tab existed, or by code that didn't touch it.
        if (! collect(Features::MODULES)->keys()->contains(fn (string $module) => $data->has('feature_'.$module))) {
            return;
        }

        $locked = Features::offInConfig();
        $off = Features::off();

        Features::save(array_values(array_filter(array_keys(Features::MODULES), fn (string $module) => in_array($module, $locked, true)
            ? in_array($module, $off, true)
            : $data->get('feature_'.$module, true) === false)));
    }

    /**
     * The default site's switches, as the addon's settings have them: for a
     * set made by `mt:install` or the update that moved the settings here.
     */
    public static function seed(GlobalSet $set): void
    {
        $variables = $set->in(Site::default()->handle());

        if (! $variables) {
            return;
        }

        foreach (Features::off() as $module) {
            $variables->set('feature_'.$module, false);
        }

        if (Features::off() !== []) {
            $variables->saveQuietly();
        }
    }
}
