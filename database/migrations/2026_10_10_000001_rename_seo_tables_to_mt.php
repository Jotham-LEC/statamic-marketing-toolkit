<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/*
 * The addon's last `seo` names on the server: its tables become `mt_*`, the
 * translation keys stored in reports move to the `marketing-toolkit::`
 * namespace, and a Search Console key uploaded in the control panel moves to
 * storage/app/private/marketing-toolkit. (The site's own files, in git, are
 * renamed by the RenameFromSeo update script.)
 */
return new class extends Migration
{
    private const array TABLES = ['seo_redirects' => 'mt_redirects', 'seo_404s' => 'mt_404s', 'seo_reports' => 'mt_reports', 'seo_report_pages' => 'mt_report_pages', 'seo_search_stats' => 'mt_search_stats'];

    public function up(): void
    {
        foreach (self::TABLES as $old => $new) {
            if (Schema::hasTable($old) && ! Schema::hasTable($new)) {
                Schema::rename($old, $new);
            }
        }

        $this->rewrite('mt_reports', ['summary', 'error'], 'seo::', 'marketing-toolkit::');
        $this->rewrite('mt_report_pages', ['facts', 'results'], 'seo::', 'marketing-toolkit::');

        $old = storage_path('app/private/seo/search-console-key.json');
        $new = storage_path('app/private/marketing-toolkit/search-console-key.json');

        if (File::exists($old) && ! File::exists($new)) {
            File::ensureDirectoryExists(dirname($new));
            File::move($old, $new);

            if (File::isEmptyDirectory(dirname($old))) {
                File::deleteDirectory(dirname($old));
            }
        }
    }

    public function down(): void
    {
        $this->rewrite('mt_reports', ['summary', 'error'], 'marketing-toolkit::', 'seo::');
        $this->rewrite('mt_report_pages', ['facts', 'results'], 'marketing-toolkit::', 'seo::');

        foreach (self::TABLES as $old => $new) {
            if (Schema::hasTable($new) && ! Schema::hasTable($old)) {
                Schema::rename($new, $old);
            }
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function rewrite(string $table, array $columns, string $from, string $to): void
    {
        DB::table($table)->select(['id', ...$columns])->orderBy('id')->chunkById(500, function ($rows) use ($table, $columns, $from, $to) {
            foreach ($rows as $row) {
                $changes = [];

                foreach ($columns as $column) {
                    if (is_string($row->{$column}) && str_contains($row->{$column}, $from)) {
                        $changes[$column] = str_replace($from, $to, $row->{$column});
                    }
                }

                if ($changes !== []) {
                    DB::table($table)->where('id', $row->id)->update($changes);
                }
            }
        });
    }
};
