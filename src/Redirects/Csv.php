<?php

namespace JothamLec\MarketingToolkit\Redirects;

use Illuminate\Support\Facades\DB;
use JothamLec\MarketingToolkit\Support\Sites;

/**
 * Redirects as CSV: `source,target,status,active`, one rule per row, with that
 * header, and a fifth column `site` on a multi-site install (a site's handle,
 * or empty for every site). Import adds new sources and updates existing ones
 * on the row's site (in any letter case, when matching ignores it); a row
 * that fails the form's checks is skipped and reported by its line number.
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

        Redirect::query()->orderBy('source')->orderBy('site')->each(function (Redirect $redirect) use ($out, $sites) {
            $row = [$redirect->source, $redirect->target, $redirect->status, $redirect->active ? 1 : 0];

            fputcsv($out, $sites ? [...$row, $redirect->site] : $row, escape: '');
        });
    }

    /**
     * @return array{created: int, updated: int, errors: list<string>}
     */
    public function import(string $contents): array
    {
        $lines = preg_split('/\r\n|\n|\r/', ltrim($contents, "\u{FEFF}"));

        return Matcher::flushAfter(fn () => DB::transaction(function () use ($lines) {
            $result = ['created' => 0, 'updated' => 0, 'errors' => []];
            // Matching in any letter case means folding every stored source to find
            // a row's rule: done once here, not for each row and again in its checks.
            $folded = Redirect::ignoresCase() ? $this->folded() : null;

            foreach ($lines as $index => $line) {
                $cells = array_map('trim', str_getcsv($line, escape: ''));

                if ($cells === [''] || ($index === 0 && strtolower($cells[0]) === 'source')) {
                    continue;
                }

                $row = [
                    'source' => $cells[0],
                    'target' => ($cells[1] ?? '') ?: null,
                    'status' => (int) (($cells[2] ?? '') ?: 301),
                    'active' => filter_var(($cells[3] ?? '') === '' ? true : $cells[3], FILTER_VALIDATE_BOOLEAN),
                    'site' => Sites::multiple() ? (($cells[4] ?? '') ?: null) : null,
                ];

                if ($folded === null) {
                    $existing = Redirect::forSource($row['source'], site: $row['site']);
                    $taken = null;
                } else {
                    $key = Redirect::key(Redirect::normalize($row['source']));
                    $ids = $folded[$row['site'] ?? ''][$key] ?? [];
                    $existing = $ids ? Redirect::query()->find($ids[0]) : null;
                    $taken = count($ids) > 1;
                }

                $validator = Redirect::validator($row, $existing?->id, $taken);

                if ($validator->fails()) {
                    $result['errors'][] = __('seo::validation.csv_line', ['line' => $index + 1, 'message' => $validator->errors()->first()]);

                    continue;
                }

                if ($existing) {
                    $existing->update($row);
                    $result['updated']++;
                } else {
                    $created = Redirect::query()->create($row);
                    $result['created']++;

                    if ($folded !== null) {
                        $folded[$row['site'] ?? ''][$key][] = $created->id;
                    }
                }
            }

            return $result;
        }));
    }

    /**
     * The stored rules' ids by site ('' for every site) and case-folded source, oldest first.
     *
     * @return array<string, array<string, list<int>>>
     */
    private function folded(): array
    {
        $folded = [];

        foreach (Redirect::query()->select(['id', 'site', 'source'])->orderBy('id')->cursor() as $redirect) {
            $folded[$redirect->site ?? ''][Redirect::key(Redirect::normalize($redirect->source))][] = $redirect->id;
        }

        return $folded;
    }
}
