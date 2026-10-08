<?php

namespace JothamLec\MarketingToolkit\Redirects;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use JothamLec\MarketingToolkit\Support\Sites;
use SplFileObject;
use SplTempFileObject;

/**
 * This class exports and imports redirects as CSV, with one rule per row under the header
 * `source,target,status,active`. A multi-site install adds a fifth column, `site`, which holds a site's
 * handle, or is empty for every site. Import adds new sources and updates existing ones on the row's site
 * (in any letter case, when matching ignores case). A row that fails the form's checks is skipped and
 * reported by its row number. Both export and import keep to the sites the signed-in user may work on,
 * and they include the rules for every site only for a user who may work on every site.
 */
class Csv
{
    public const array HEADER = ['source', 'target', 'status', 'active'];

    /**
     * @param  resource  $out
     */
    public function export($out): void
    {
        $sites = Sites::multiple();

        fputcsv($out, $sites ? [...self::HEADER, 'site'] : self::HEADER, escape: '');

        // Each address's rule for every site (with no site) comes first, because databases sort a null differently.
        Redirect::query()->accessible()->orderBy('source')->orderByRaw('site is not null')->orderBy('site')->each(function (Redirect $redirect) use ($out, $sites) {
            $row = [$redirect->source, $redirect->target, $redirect->status, $redirect->active ? 1 : 0];

            fputcsv($out, $sites ? [...$row, $redirect->site] : $row, escape: '');
        });
    }

    /**
     * @return array{created: int, updated: int, errors: list<string>}
     */
    public function import(string $contents): array
    {
        // We read the file as CSV records rather than lines, because a quoted cell may hold a line break.
        $file = new SplTempFileObject;
        $file->fwrite(ltrim($contents, "\u{FEFF}"));
        $file->rewind();
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);
        $file->setCsvControl(',', '"', '');

        return Matcher::flushAfter(fn () => DB::transaction(function () use ($file) {
            $result = ['created' => 0, 'updated' => 0, 'errors' => []];
            $sites = Sites::accessible();
            // We read every rule once, rather than for each row and again in its checks. The rules are
            // grouped by site ('' for every site) and by source as rules compare it (case-folded when
            // matching ignores case), oldest first. The active rules, which the loop check follows, are
            // kept apart. Both are kept current as rows are saved, so each row sees the rows before it.
            $rules = Redirect::query()->orderBy('id')->get();
            $bySource = [];
            $active = $rules->where('active', true)->keyBy('id');

            foreach ($rules as $rule) {
                $bySource[$rule->site ?? ''][Redirect::key(Redirect::normalize($rule->source))][] = $rule;
            }

            $number = 0;

            foreach ($file as $record) {
                $cells = array_map(fn ($cell) => trim((string) $cell), $record);

                // A blank line is not a record, so it is not counted as one; the header row is skipped too.
                if ($cells === [''] || (++$number === 1 && strtolower($cells[0]) === 'source')) {
                    continue;
                }

                $row = [
                    'source' => $cells[0],
                    'target' => ($cells[1] ?? '') ?: null,
                    'status' => (int) (($cells[2] ?? '') ?: 301),
                    'active' => filter_var(($cells[3] ?? '') === '' ? true : $cells[3], FILTER_VALIDATE_BOOLEAN),
                    'site' => Sites::multiple() ? (($cells[4] ?? '') ?: null) : null,
                ];

                $key = Redirect::key(Redirect::normalize($row['source']));
                $matches = $bySource[$row['site'] ?? ''][$key] ?? [];
                $existing = $matches[0] ?? null;
                $validator = Redirect::validator($row, ignoreId: $existing?->id, taken: count($matches) > 1, active: $active, sites: $sites);

                if ($validator->fails()) {
                    $result['errors'][] = __('marketing-toolkit::validation.csv_row', ['row' => $number, 'message' => $validator->errors()->first()]);

                    continue;
                }

                try {
                    // Each row gets its own savepoint, because on Postgres a failed statement aborts the
                    // whole transaction, and the rows before it would be lost with it.
                    $saved = DB::transaction(fn () => $existing
                        // An imported rule belongs to the user, just as a rule saved in the form does.
                        ? tap($existing)->update([...$row, 'automatic' => false])
                        : Redirect::query()->create($row));
                } catch (QueryException $exception) {
                    report($exception);
                    $result['errors'][] = __('marketing-toolkit::validation.csv_row_failed', ['row' => $number]);

                    continue;
                }

                if ($existing) {
                    $result['updated']++;
                } else {
                    $bySource[$row['site'] ?? ''][$key][] = $saved;
                    $result['created']++;
                }

                $saved->active ? $active->put($saved->id, $saved) : $active->forget($saved->id);
            }

            return $result;
        }));
    }
}
