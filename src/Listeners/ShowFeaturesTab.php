<?php

namespace JothamLec\MarketingToolkit\Listeners;

use JothamLec\MarketingToolkit\Support\Features;
use JothamLec\MarketingToolkit\Support\Package;
use Statamic\Events\GlobalVariablesBlueprintFound;
use Statamic\Facades\Addon;
use Statamic\Facades\Site;

/**
 * The Features tab of Marketing settings is for the whole install, so it is shown only on the default
 * site's localization, to whoever may change the addon's settings. A module that
 * config/marketing-toolkit.php switches off is shown as off and can't be switched on there.
 */
final class ShowFeaturesTab
{
    public function handle(GlobalVariablesBlueprintFound $event): void
    {
        $variables = $event->globals;

        if ($variables?->handle() !== config('marketing-toolkit.settings_global') || ! $event->blueprint->hasTab('features')) {
            return;
        }

        if ($variables->locale() !== Site::default()->handle() || ! Package::canEditSettings()) {
            $event->blueprint->removeTab('features');

            return;
        }

        foreach (Features::offInConfig() as $module) {
            $event->blueprint->ensureFieldHasConfig('feature_'.$module, ['visibility' => 'read_only', 'default' => false]);
        }
    }
}
