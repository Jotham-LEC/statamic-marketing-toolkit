<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One redirect per source and one 404 row per path among the rows without a
 * site (every row on a single site; rules for every site). The unique index on
 * (site, source) doesn't see them: MySQL, MariaDB, Postgres and SQLite all
 * count each NULL as different, so two requests missing the same path at once
 * made two rows. A second index treats a NULL site as ''. Postgres and SQLite
 * index the expression; MySQL and MariaDB index a generated `site_key` column.
 * SQL Server already counts NULLs as equal.
 *
 * 404 paths that already repeat are merged first: they are a log, so their
 * hits are added up and their dates widened. Redirects are the site's own
 * rules, so none is deleted: while two without a site share a source, the
 * migration stops before changing anything and names them, to be deleted
 * under Marketing → Redirects first.
 */
return new class extends Migration
{
    /** Table => the column that must be unique per site. */
    private const array TABLES = ['mt_redirects' => 'source', 'mt_404s' => 'path'];

    public function up(): void
    {
        $repeated = $this->repeated('mt_redirects', 'source');

        if ($repeated !== []) {
            throw new RuntimeException('Some redirects for every site share a source: '.implode(', ', array_slice($repeated, 0, 20)).(count($repeated) > 20 ? ' and '.(count($repeated) - 20).' more' : '').'. Keep one of each under Marketing → Redirects (the newest active one is the one that applies), then run the migration again.');
        }

        $this->mergeMissingPaths();

        foreach (self::TABLES as $table => $column) {
            match ($this->driver()) {
                'pgsql', 'sqlite' => DB::statement(sprintf(
                    'create unique index %s on %s ((coalesce(%s, \'\')), %s)',
                    $this->index($table), $this->grammar()->wrapTable($table), $this->grammar()->wrap('site'), $this->grammar()->wrap($column),
                )),
                'mysql', 'mariadb' => Schema::table($table, function (Blueprint $blueprint) use ($table, $column) {
                    $blueprint->string('site_key', 32)->storedAs("coalesce(`site`, '')")->after('site');
                    $blueprint->unique(['site_key', $column], $this->index($table));
                }),
                default => null,
            };
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => $column) {
            match ($this->driver()) {
                'pgsql', 'sqlite' => DB::statement(sprintf('drop index if exists %s', $this->index($table))),
                'mysql', 'mariadb' => Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    $blueprint->dropUnique($this->index($table));
                    $blueprint->dropColumn('site_key');
                }),
                default => null,
            };
        }
    }

    /**
     * Each path repeated without a site as one row: the first one, with every
     * one's hits, the earliest first sighting and the latest last one (and
     * its referrer).
     */
    private function mergeMissingPaths(): void
    {
        foreach ($this->repeated('mt_404s', 'path') as $path) {
            $rows = DB::table('mt_404s')->whereNull('site')->where('path', $path)->orderBy('id')->get();
            $kept = $rows->first();
            $latest = $rows->sortByDesc('last_seen_at')->first();

            DB::table('mt_404s')->where('id', $kept->id)->update([
                'hits' => $rows->sum('hits'),
                'first_seen_at' => $rows->min('first_seen_at'),
                'last_seen_at' => $latest->last_seen_at,
                'referrer' => $latest->referrer ?? $kept->referrer,
            ]);
            DB::table('mt_404s')->whereIn('id', $rows->pluck('id')->reject(fn ($id) => $id === $kept->id)->all())->delete();
        }
    }

    /**
     * Values of $column on more than one row without a site, as the database
     * compares them (MySQL's collations ignore case, like its index).
     *
     * @return list<string>
     */
    private function repeated(string $table, string $column): array
    {
        return DB::table($table)->whereNull('site')->groupBy($column)->havingRaw('count(*) > 1')->pluck($column)->all();
    }

    private function driver(): string
    {
        return Schema::getConnection()->getDriverName();
    }

    /** Named as Laravel names its own, with the connection's table prefix. */
    private function index(string $table): string
    {
        return Schema::getConnection()->getTablePrefix().$table.'_site_key_unique';
    }

    private function grammar(): Grammar
    {
        return Schema::getConnection()->getQueryGrammar();
    }
};
