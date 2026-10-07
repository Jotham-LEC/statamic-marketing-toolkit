<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which site a redirect applies on (a Statamic site handle), or null for
 * every site, as all rules made before did. One rule per address per site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table($this->table(), function (Blueprint $table) {
            $table->dropUnique(['source']);
        });

        Schema::table($this->table(), function (Blueprint $table) {
            // MySQL keeps a unique index under 3072 bytes: 32 + 736 characters of four bytes.
            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $table->string('source', 736)->change();
            }

            $table->string('site', 32)->nullable()->after('id')->index();
            $table->unique(['site', 'source']);
        });
    }

    /**
     * Refuses while two sites share a source: the old unique index on source
     * alone couldn't be made, and the sites would be lost first.
     */
    public function down(): void
    {
        if (DB::table($this->table())->select('source')->groupBy('source')->havingRaw('count(*) > 1')->exists()) {
            throw new RuntimeException("Can't roll back: some redirects share a source across sites. Delete all but one of each first, then roll back again.");
        }

        Schema::table($this->table(), function (Blueprint $table) {
            $table->dropUnique(['site', 'source']);
            $table->dropIndex(['site']);
            $table->dropColumn('site');
        });

        Schema::table($this->table(), function (Blueprint $table) {
            $table->unique('source');
        });
    }

    /**
     * `mt_redirects` where the create migration made it under that name, as it does
     * where another package has `seo_redirects`.
     */
    private function table(): string
    {
        return Schema::hasTable('mt_redirects') ? 'mt_redirects' : 'seo_redirects';
    }
};
