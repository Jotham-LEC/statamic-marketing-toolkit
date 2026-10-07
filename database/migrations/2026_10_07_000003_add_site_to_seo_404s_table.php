<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The site a missing path was asked of (a Statamic site handle), or null on
 * a single site, as every row logged before. One row per path per site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table($this->table(), function (Blueprint $table) {
            $table->dropUnique(['path']);
        });

        Schema::table($this->table(), function (Blueprint $table) {
            // MySQL keeps a unique index under 3072 bytes: 32 + 736 characters of four bytes.
            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $table->string('path', 736)->change();
            }

            $table->string('site', 32)->nullable()->after('id')->index();
            $table->unique(['site', 'path']);
        });
    }

    /**
     * Refuses while two sites share a path: the old unique index on path
     * alone couldn't be made, and the sites would be lost first.
     */
    public function down(): void
    {
        if (DB::table($this->table())->select('path')->groupBy('path')->havingRaw('count(*) > 1')->exists()) {
            throw new RuntimeException("Can't roll back: some logged 404s share a path across sites. Delete all but one of each first, then roll back again.");
        }

        Schema::table($this->table(), function (Blueprint $table) {
            $table->dropUnique(['site', 'path']);
            $table->dropIndex(['site']);
            $table->dropColumn('site');
        });

        Schema::table($this->table(), function (Blueprint $table) {
            $table->unique('path');
        });
    }

    /**
     * `mt_404s` where the create migration made it under that name, as it does
     * where another package has `seo_404s`.
     */
    private function table(): string
    {
        return Schema::hasTable('mt_404s') ? 'mt_404s' : 'seo_404s';
    }
};
