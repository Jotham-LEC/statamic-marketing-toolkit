<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The site whose Search Console property a row came from (a Statamic site
 * handle), or null on a single site, as every row imported before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table($this->table(), function (Blueprint $table) {
            $table->string('site', 32)->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table($this->table(), function (Blueprint $table) {
            $table->dropIndex(['site']);
            $table->dropColumn('site');
        });
    }

    /**
     * `mt_search_stats` where the create migration made it under that name, as it does
     * where another package has `seo_search_stats`.
     */
    private function table(): string
    {
        return Schema::hasTable('mt_search_stats') ? 'mt_search_stats' : 'seo_search_stats';
    }
};
