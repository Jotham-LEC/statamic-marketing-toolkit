<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_search_stats', function (Blueprint $table) {
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
        Schema::dropIfExists('seo_search_stats');
    }
};
