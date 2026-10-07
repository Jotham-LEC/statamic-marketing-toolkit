<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where another package already has `seo_reports` or `seo_report_pages`, that
 * table is made under its later name, `mt_*`, from the start: the migrations
 * after this one change the `mt_*` table where it exists, and the rename to
 * it leaves the other package's alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        $reports = Schema::hasTable('seo_reports') ? 'mt_reports' : 'seo_reports';

        Schema::create($reports, function (Blueprint $table) {
            $table->id();
            $table->string('status', 16)->default('running')->index();
            $table->unsignedInteger('pages_total')->default(0);
            $table->unsignedInteger('pages_done')->default(0);
            $table->unsignedTinyInteger('score')->nullable();
            $table->json('settings');
            $table->json('summary')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create(Schema::hasTable('seo_report_pages') ? 'mt_report_pages' : 'seo_report_pages', function (Blueprint $table) use ($reports) {
            $table->id();
            $table->foreignId('report_id')->constrained($reports)->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('content_type', 8);
            $table->string('content_id');
            $table->string('title')->nullable();
            $table->boolean('in_sitemap')->default(false);
            $table->boolean('checked')->default(false);
            $table->json('facts')->nullable();
            $table->json('results')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            // ",title_length:warn,canonical:fail," – to list the pages a check flagged, on any database.
            $table->text('failing')->nullable();

            $table->index(['report_id', 'checked']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Schema::hasTable('mt_report_pages') ? 'mt_report_pages' : 'seo_report_pages');
        Schema::dropIfExists(Schema::hasTable('mt_reports') ? 'mt_reports' : 'seo_reports');
    }
};
