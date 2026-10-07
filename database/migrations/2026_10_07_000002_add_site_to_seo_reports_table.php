<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The site a report checked (a Statamic site handle), or null on a single
 * site, as every report made before.
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
     * `mt_reports` where the create migration made it under that name, as it does
     * where another package has `seo_reports`.
     */
    private function table(): string
    {
        return Schema::hasTable('mt_reports') ? 'mt_reports' : 'seo_reports';
    }
};
