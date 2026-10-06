<?php

use Illuminate\Database\Migrations\Migration;
use JothamLec\MarketingToolkit\Support\LegacySettings;

/*
 * Co-SEO became Marketing Toolkit: its addon settings (the Search Console
 * property) are copied to the new name, from the YAML file or the
 * `addon_settings` table. The old ones stay where they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        LegacySettings::carryOver();
    }

    public function down(): void
    {
        // The copy is harmless to keep.
    }
};
