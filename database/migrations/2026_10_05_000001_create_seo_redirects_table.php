<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where another package already has `seo_redirects`, the table is made under its
 * later name, `mt_redirects`, from the start: the migrations after this one change
 * `mt_redirects` where it exists, and the rename to it leaves `seo_redirects` alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Schema::hasTable('seo_redirects') ? 'mt_redirects' : 'seo_redirects', function (Blueprint $table) {
            $table->id();
            // 768 characters keeps the unique index inside MySQL's 3072-byte limit.
            $table->string('source', 768)->unique();
            $table->string('target', 2048)->nullable();
            $table->unsignedSmallInteger('status')->default(301);
            $table->boolean('active')->default(true);
            $table->boolean('automatic')->default(false);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Schema::hasTable('mt_redirects') ? 'mt_redirects' : 'seo_redirects');
    }
};
