<?php

namespace JothamLec\MarketingToolkit\Redirects;

use Illuminate\Support\Facades\DB;
use JothamLec\MarketingToolkit\Support\Sites;
use SplFileObject;
use SplTempFileObject;

/**
 * Redirects as CSV: `source,target,status,active`, one rule per row, with that
 * header, and a fifth column `site` on a multi-site install (a site's handle,
 * or empty for every site). Import adds new sources and updates existing ones
 * on the row's site (in any letter case, when matching ignores it); a row
 * that fails the form's checks is skipped and reported by its row number.
 * Both keep to the sites the signed-in user may work on, and to the rules
 * for every site.
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

        // Each address's rule for every site (no site) first: databases sort a null differently.
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
        // Read as CSV records, not lines: a quoted cell may hold a line break.
        $file = new SplTempFileObject;
        $file->fwrite(ltrim($contents, "\u{FEFF}"));
        $file->rewind();
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);
        $file->setCsvControl(',', '"', '');

        return Matcher::flushAfter(fn () => DB::transaction(function () use ($file) {
            $result = ['created' => 0, 'updated' => 0, 'errors' => []];
            $sites = Sites::accessible();
            // Every rule read once, not for each row and again in its checks: by
            // site ('' for every site) and source as rules compare it (case-folded
            // when matching ignores case), oldest first; and the active ones, which
            // the loop check follows. Both kept current as rows are saved, so a
            // row sees the rows before it.
            $rules = Redirect::query()->orderBy('id')->get();
            $bySource = [];
            $active = $rules->where('active', true)->keyBy('id');

            foreach ($rules as $rule) {
                $bySource[$rule->site ?? ''][Redirect::key(Redirect::normalize($rule->source))][] = $rule;
            }

            $number = 0;

            foreach ($file as $record) {
                $cells = array_map(fn ($cell) => trim((string) $cell), $record);

                // A blank line is no record, and isn't counted as one.
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
                $validator = Redirect::validator($row, $existing?->id, count($matches) > 1, $active, $sites);

                if ($validator->fails()) {
                    $result['errors'][] = __('seo::validation.csv_row', ['row' => $number, 'message' => $validator->errors()->first()]);

                    continue;
                }

                if ($existing) {
                    $existing->update($row);
                    $saved = $existing;
                    $result['updated']++;
                } else {
                    $saved = $bySource[$row['site'] ?? ''][$key][] = Redirect::query()->create($row);
                    $result['created']++;
                }

                $saved->active ? $active->put($saved->id, $saved) : $active->forget($saved->id);
            }

            return $result;
        }));
    }
}
