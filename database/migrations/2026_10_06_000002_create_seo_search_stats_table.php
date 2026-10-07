<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where another package already has `seo_search_stats`, the table is made under its
 * later name, `mt_search_stats`, from the start: the migrations after this one change
 * `mt_search_stats` where it exists, and the rename to it leaves `seo_search_stats` alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Schema::hasTable('seo_search_stats') ? 'mt_search_stats' : 'seo_search_stats', function (Blueprint $table) {
            $table->id();
            $table->string('url', 2048);
            $table->unsignedInteger('clicks');
            $table->unsignedInteger('impressions');
            $table->float('ctr');
            $table->float('position');
            $table->date('from');
            $table->date('to');
            $table->timestamp('fetched_at');
            $table->index('clicks');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Schema::hasTable('mt_search_stats') ? 'mt_search_stats' : 'seo_search_stats');
    }
};
