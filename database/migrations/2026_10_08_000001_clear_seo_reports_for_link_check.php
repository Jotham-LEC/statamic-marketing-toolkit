<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * 0.18 replaced the scored SEO reports with the link check. Reports from
 * before hold checks and scores the screens no longer show: clear them, and
 * keep the tables, which the link check uses.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('seo_report_pages')) {
            DB::table('seo_report_pages')->delete();
        }

        if (Schema::hasTable('seo_reports')) {
            DB::table('seo_reports')->delete();
        }
    }

    public function down(): void
    {
        // The old reports are gone; nothing to bring back.
    }
};
