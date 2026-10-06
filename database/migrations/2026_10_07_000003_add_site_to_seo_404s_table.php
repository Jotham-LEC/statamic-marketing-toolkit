<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The site a missing path was asked of (a Statamic site handle), or null on
 * a single site, as every row logged before. One row per path per site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_404s', function (Blueprint $table) {
            $table->dropUnique(['path']);
        });

        Schema::table('seo_404s', function (Blueprint $table) {
            // MySQL keeps a unique index under 3072 bytes: 32 + 736 characters of four bytes.
            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $table->string('path', 736)->change();
            }

            $table->string('site', 32)->nullable()->after('id')->index();
            $table->unique(['site', 'path']);
        });
    }

    public function down(): void
    {
        Schema::table('seo_404s', function (Blueprint $table) {
            $table->dropUnique(['site', 'path']);
            $table->dropIndex(['site']);
            $table->dropColumn('site');
        });

        Schema::table('seo_404s', function (Blueprint $table) {
            $table->unique('path');
        });
    }
};
