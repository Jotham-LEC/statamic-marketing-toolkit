<?php

namespace JothamLec\Seo\Redirects;

use Closure;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * One rule: requests for `source` go to `target` with `status` (301, 302),
 * or are answered 410 Gone. A `*` in the source matches anything, and the
 * target takes what it matched as $1, $2… Rules apply only to addresses the
 * site would otherwise answer with a 404.
 *
 * @property int $id
 * @property string $source
 * @property ?string $target
 * @property int $status
 * @property bool $active
 * @property bool $automatic
 * @property int $hits
 * @property ?Carbon $last_hit_at
 */
class Redirect extends Model
{
    public const array STATUSES = [301, 302, 410];

    protected $table = 'seo_redirects';

    protected $guarded = ['id'];

    protected $attributes = ['status' => 301, 'active' => true, 'automatic' => false, 'hits' => 0];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'active' => 'boolean',
            'automatic' => 'boolean',
            'hits' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $redirect) {
            $redirect->source = self::normalize($redirect->source);
            $redirect->target = $redirect->status === 410 ? null : self::normalizeTarget($redirect->target);
        });

        static::saved(fn () => Matcher::flush());
        static::deleted(fn () => Matcher::flush());
    }

    /**
     * A site path as rules store and compare it: a leading slash, no trailing
     * slash (except the home page), no query string.
     */
    public static function normalize(string $path): string
    {
        $path = strtok(trim($path), '?#') ?: '/';

        return '/'.trim($path, '/');
    }

    public static function normalizeTarget(?string $target): ?string
    {
        $target = trim((string) $target);

        if ($target === '') {
            return null;
        }

        // Another site's address is kept as typed.
        if (preg_match('#^https?://#i', $target)) {
            return $target;
        }

        $query = str_contains($target, '?') ? '?'.substr($target, strpos($target, '?') + 1) : '';

        return self::normalize($target).$query;
    }

    /**
     * Checks a redirect's fields, from the form or a CSV row.
     *
     * @param  array<string, mixed>  $data
     */
    public static function validator(array $data, ?int $ignoreId = null): ValidatorContract
    {
        return Validator::make($data, self::rules((string) ($data['source'] ?? ''), $ignoreId), [
            'source.required' => 'Which address should be redirected?',
            'source.starts_with' => 'Start with / — the part of the address after the domain.',
            'source.not_regex' => 'Leave out the query string (?…); addresses are matched without it.',
            'target.required_unless' => 'Where should it go? Only “410 Gone” needs no target.',
            'target.regex' => 'Start with / for a page on this site, or https:// for another site.',
            'status.in' => 'Choose 301, 302 or 410.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function rules(string $source, ?int $ignoreId): array
    {
        return [
            'source' => [
                'required', 'string', 'max:768', 'starts_with:/', 'not_regex:/[?#]/',
                function (string $attribute, mixed $value, Closure $fail) use ($ignoreId) {
                    $taken = self::query()->where('source', self::normalize((string) $value))
                        ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                        ->exists();

                    if ($taken) {
                        $fail('Another redirect already starts from this address.');
                    }
                },
            ],
            'target' => [
                'nullable', 'required_unless:status,410', 'string', 'max:2048', 'regex:#^(/|https?://)#i',
                function (string $attribute, mixed $value, Closure $fail) use ($source) {
                    $wildcards = substr_count($source, '*');
                    preg_match_all('/\$(\d+)/', (string) $value, $used);

                    if ($used[1] !== [] && max(array_map('intval', $used[1])) > $wildcards) {
                        $fail('The target uses a $ number the source has no * for.');
                    }
                },
            ],
            'status' => ['required', 'in:301,302,410'],
            'active' => ['boolean'],
        ];
    }

    public function isWildcard(): bool
    {
        return str_contains($this->source, '*');
    }
}
