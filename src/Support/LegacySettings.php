<?php

use JothamLec\MarketingToolkit\Legacy\CoSeoSettings;

// This is the name that the shipped migration carry_over_co_seo_settings imports, because
// migrations are never edited once shipped. It will be removed in 1.0.

class_alias(CoSeoSettings::class, 'JothamLec\\MarketingToolkit\\Support\\LegacySettings');
