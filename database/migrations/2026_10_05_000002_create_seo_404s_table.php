<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where another package already has `seo_404s`, the table is made under its
 * later name, `mt_404s`, from the start: the migrations after this one change
 * `mt_404s` where it exists, and the rename to it leaves `seo_404s` alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Schema::hasTable('seo_404s') ? 'mt_404s' : 'seo_404s', function (Blueprint $table) {
            $table->id();
            $table->string('path', 768)->unique();
            $table->unsignedInteger('hits')->default(1);
            $table->string('referrer', 2048)->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Schema::hasTable('mt_404s') ? 'mt_404s' : 'seo_404s');
    }
};
