<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The front-end toolbar finds a page's row in a report by its entry or term,
 * on every page view of a signed-in editor: an index for that lookup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mt_report_pages', function (Blueprint $table) {
            $table->index(['report_id', 'content_type', 'content_id']);
        });
    }

    public function down(): void
    {
        Schema::table('mt_report_pages', function (Blueprint $table) {
            $table->dropIndex(['report_id', 'content_type', 'content_id']);
        });
    }
};
