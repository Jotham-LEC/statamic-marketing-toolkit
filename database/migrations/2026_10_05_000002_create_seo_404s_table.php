<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_404s', function (Blueprint $table) {
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
        Schema::dropIfExists('seo_404s');
    }
};
