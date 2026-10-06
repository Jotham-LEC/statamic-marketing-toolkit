<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which site a redirect applies on (a Statamic site handle), or null for
 * every site, as all rules made before did. One rule per address per site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_redirects', function (Blueprint $table) {
            $table->dropUnique(['source']);
        });

        Schema::table('seo_redirects', function (Blueprint $table) {
            // MySQL keeps a unique index under 3072 bytes: 32 + 736 characters of four bytes.
            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $table->string('source', 736)->change();
            }

            $table->string('site', 32)->nullable()->after('id')->index();
            $table->unique(['site', 'source']);
        });
    }

    public function down(): void
    {
        Schema::table('seo_redirects', function (Blueprint $table) {
            $table->dropUnique(['site', 'source']);
            $table->dropIndex(['site']);
            $table->dropColumn('site');
        });

        Schema::table('seo_redirects', function (Blueprint $table) {
            $table->unique('source');
        });
    }
};
