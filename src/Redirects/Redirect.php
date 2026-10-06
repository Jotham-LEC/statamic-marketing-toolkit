<?php

namespace JothamLec\Seo\Redirects;

use Closure;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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

    /** How far a chain of rules is followed when looking for a loop. */
    private const int MAX_HOPS = 10;

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
     * A site path as rules store and compare it: decoded (so `/caf%C3%A9` and
     * `/café` are one address, as requests are matched), a leading slash, no
     * trailing slash (except the home page), no query string or fragment.
     */
    public static function normalize(string $path): string
    {
        $path = trim($path);
        $path = substr($path, 0, strcspn($path, '?#'));

        return '/'.trim(rawurldecode($path), '/');
    }

    /**
     * Whether rules match an address in any letter case: `redirects.case_sensitive`
     * off, for a site whose old addresses worked in any case (Wix, IIS).
     */
    public static function ignoresCase(): bool
    {
        return ! config('seo.redirects.case_sensitive', true);
    }

    /**
     * A normalized path as rules compare it: case-folded when matching ignores
     * case (`/CAFÉ` and `/café` are one), else as it is. A path that isn't
     * valid UTF-8 is left alone.
     */
    public static function key(string $path): string
    {
        return self::ignoresCase() && mb_check_encoding($path, 'UTF-8') ? mb_convert_case($path, MB_CASE_FOLD_SIMPLE, 'UTF-8') : $path;
    }

    /**
     * The rule that starts from this address, in any letter case when matching
     * ignores it. Sources are stored as typed, and databases fold letters
     * differently (SQLite only A–Z, MySQL by its collation), so that comparison is made here.
     */
    public static function forSource(string $source, ?int $ignoreId = null): ?self
    {
        $source = self::normalize($source);
        $query = self::query()->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId));

        if (! self::ignoresCase()) {
            return $query->where('source', $source)->first();
        }

        $key = self::key($source);

        return $query->orderBy('id')->cursor()->first(fn (self $redirect) => self::key(self::normalize($redirect->source)) === $key);
    }

    /**
     * A target as typed, tidied: another site's address is kept as it is; a
     * path here loses its trailing slash and keeps its query and fragment,
     * still encoded, since it goes into the Location header as it is.
     */
    public static function normalizeTarget(?string $target): ?string
    {
        $target = trim((string) $target);

        if ($target === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $target)) {
            return $target;
        }

        $end = strcspn($target, '?#');

        return '/'.trim(substr($target, 0, $end), '/').substr($target, $end);
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
            'source.regex' => 'An address can’t contain line breaks or other control characters.',
            'target.required_unless' => 'Where should it go? Only “410 Gone” needs no target.',
            'target.regex' => 'Start with / for a page on this site, or https:// for another site.',
            'target.not_regex' => 'An address can’t contain line breaks or other control characters.',
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
                // Control characters would go into the Location header (a line break starts a new header).
                'required', 'string', 'max:768', 'starts_with:/', 'not_regex:/[?#]/', 'regex:/^[^\x00-\x1F\x7F]*$/',
                function (string $attribute, mixed $value, Closure $fail) use ($ignoreId) {
                    if (self::forSource((string) $value, $ignoreId)) {
                        $fail('Another redirect already starts from this address.');
                    }
                },
            ],
            'target' => [
                'nullable', 'required_unless:status,410', 'string', 'max:2048', 'regex:#^(/|https?://)#i', 'not_regex:/[\x00-\x1F\x7F]/',
                function (string $attribute, mixed $value, Closure $fail) use ($source, $ignoreId) {
                    $wildcards = substr_count($source, '*');
                    preg_match_all('/\$(\d+)/', (string) $value, $used);

                    if ($used[1] !== [] && max(array_map('intval', $used[1])) > $wildcards) {
                        $fail('The target uses a $ number the source has no * for.');
                    } elseif (preg_match('#^https?://[^/]*\$\d#i', (string) $value)) {
                        // What a visitor typed would choose the site they are sent to (`https://example.com$1` → example.com.evil.test).
                        $fail('A $ number can only come after the domain and a /.');
                    } elseif ($loop = self::loop($source, (string) $value, $ignoreId)) {
                        $fail($loop);
                    }
                },
            ],
            'status' => ['required', Rule::in(self::STATUSES)],
            'active' => ['boolean'],
        ];
    }

    /**
     * Why a rule from $source to $target would send visitors round in a
     * circle, or null. A target under a wildcard's own source (`/blog/*` to
     * `/blog/new/$1`) is allowed: the pages there usually exist.
     */
    private static function loop(string $source, string $target, ?int $ignoreId): ?string
    {
        if (! str_starts_with($target, '/') || $source === '') {
            return null;
        }

        if (self::pointsBack($source, $target)) {
            return 'This sends the address back to itself.';
        }

        $source = self::normalize($source);

        if (str_contains($source, '*')) {
            return null;
        }

        // Follow the rules from the target, as a visitor would be sent on, and see whether
        // they come back here. Each step reads only the rules that could match (an exact
        // source, the wildcards), not the cached set, which an import would rebuild after
        // every row; the rule being edited stands aside for its new version. Ignoring case,
        // an exact source can't be looked up in SQL (see forSource()), so all are read once.
        $rules = fn () => self::query()->where('active', true)->whereKeyNot($ignoreId ?? 0)->select(['id', 'source', 'target', 'status']);
        $everything = self::ignoresCase() ? $rules()->get() : null;
        $wildcards = $everything ?? $rules()->where('source', 'like', '%*%')->get();
        $path = self::normalize($target);
        $seen = [];

        for ($hops = 1; $hops <= self::MAX_HOPS && ! isset($seen[self::key($path)]); $hops++) {
            $seen[self::key($path)] = true;
            $candidates = $everything ?? $rules()->where('source', $path)->get()->merge($wildcards);
            $next = app(Matcher::class)->matchAmong($candidates, $path);

            if ($next === null || $next['target'] === null || ! str_starts_with($next['target'], '/')) {
                return null;
            }

            $path = self::normalize($next['target']);

            if (self::key($path) === self::key($source)) {
                return $hops === 1
                    ? 'The redirect from that address leads back here, so the two would loop.'
                    : "The redirects from that address lead back here after {$hops} steps, so visitors would go round in a loop.";
            }
        }

        return null;
    }

    /**
     * Whether a rule sends every address it matches to that same address:
     * `/a` to `/a`, or `/x/*` to `/x/$1`. Letter case counts even when matching
     * ignores it: `/About` to `/about` is how an old address in capitals reaches
     * the page, and if that page is missing the 404 isn't redirected again.
     */
    public static function pointsBack(string $source, ?string $target): bool
    {
        $back = 0;
        $same = preg_replace_callback('/\*/', fn () => '$'.++$back, self::normalize($source));

        return $target !== null && str_starts_with($target, '/') && self::normalize($target) === $same;
    }

    public function isWildcard(): bool
    {
        return str_contains($this->source, '*');
    }
}
