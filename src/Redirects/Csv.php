<?php

namespace JothamLec\Seo\Redirects;

use Illuminate\Support\Facades\DB;

/**
 * Redirects as CSV: `source,target,status,active`, one rule per row, with that
 * header. Import adds new sources and updates existing ones; a row that fails
 * the form's checks is skipped and reported by its line number.
 */
class Csv
{
    public const array HEADER = ['source', 'target', 'status', 'active'];

    /**
     * @param  resource  $out
     */
    public function export($out): void
    {
        fputcsv($out, self::HEADER, escape: '');

        Redirect::query()->orderBy('source')->each(function (Redirect $redirect) use ($out) {
            fputcsv($out, [$redirect->source, $redirect->target, $redirect->status, $redirect->active ? 1 : 0], escape: '');
        });
    }

    /**
     * @return array{created: int, updated: int, errors: list<string>}
     */
    public function import(string $contents): array
    {
        $result = ['created' => 0, 'updated' => 0, 'errors' => []];
        $lines = preg_split('/\r\n|\n|\r/', ltrim($contents, "\u{FEFF}"));

        DB::transaction(function () use ($lines, &$result) {
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
                ];

                $existing = Redirect::query()->where('source', Redirect::normalize($row['source']))->first();
                $validator = Redirect::validator($row, $existing?->id);

                if ($validator->fails()) {
                    $result['errors'][] = 'Line '.($index + 1).': '.$validator->errors()->first();

                    continue;
                }

                $existing ? $existing->update($row) : Redirect::query()->create($row);
                $result[$existing ? 'updated' : 'created']++;
            }
        });

        return $result;
    }
}
