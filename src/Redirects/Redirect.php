<?php

namespace JothamLec\MarketingToolkit\Redirects;

use Closure;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use JothamLec\MarketingToolkit\Support\Sites;

/**
 * One rule: requests for `source` go to `target` with `status` (301, 302),
 * or are answered 410 Gone. A `*` in the source matches anything, and the
 * target takes what it matched as $1, $2… Rules apply only to addresses the
 * site would otherwise answer with a 404. On a multi-site install a rule
 * names the site it applies on, or none for every site; a site's own rule
 * wins over one for every site from the same address.
 *
 * @property int $id
 * @property ?string $site
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

    /** The longest source: MySQL's unique index on site and source must stay under 3072 bytes. */
    public const int MAX_SOURCE = 736;

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
            $redirect->site = $redirect->site === '' ? null : $redirect->site;
            $redirect->source = self::normalize($redirect->source);
            $redirect->target = $redirect->status === 410 ? null : self::normalizeTarget($redirect->target);
        });

        static::saved(fn () => Matcher::flush());
        static::deleted(fn () => Matcher::flush());
    }

    /**
     * Rules stored for exactly this site; null: those for every site.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOnSite(Builder $query, ?string $site): void
    {
        $site === null ? $query->whereNull('site') : $query->where('site', $site);
    }

    /**
     * Rules that apply on this site: its own and those for every site.
     *
     * @param  Builder<self>  $query
     */
    public function scopeAppliesOn(Builder $query, string $site): void
    {
        $query->where(fn (Builder $query) => $query->where('site', $site)->orWhereNull('site'));
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
        return ! config('seo.redirects.case_sensitive');
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
     * The rule that starts from this address on $site (null: the rules for
     * every site), in any letter case when matching ignores it. Sources are stored as typed, and databases fold letters
     * differently (SQLite only A–Z, MySQL by its collation), so that comparison is made here.
     */
    public static function forSource(string $source, ?int $ignoreId = null, ?string $site = null): ?self
    {
        $source = self::normalize($source);
        $query = self::query()->onSite($site)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId));

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
     * Checks a redirect's fields, from the form or a CSV row. $taken: whether
     * another rule already starts from the source, and $active: the active
     * rules, when the caller has them at hand (an import, which reads them
     * once rather than for every row); else they are looked up.
     *
     * @param  array<string, mixed>  $data
     * @param  ?Collection<int, self>  $active
     */
    public static function validator(array $data, ?int $ignoreId = null, ?bool $taken = null, ?Collection $active = null): ValidatorContract
    {
        $site = is_string($data['site'] ?? null) && $data['site'] !== '' ? $data['site'] : null;

        return Validator::make($data, self::rules((string) ($data['source'] ?? ''), $site, $ignoreId, $taken, $active), [
            'source.required' => __('seo::validation.redirect.source_required'),
            'source.starts_with' => __('seo::validation.redirect.source_starts_with'),
            'source.not_regex' => __('seo::validation.redirect.source_query'),
            'source.regex' => __('seo::validation.redirect.control_characters'),
            'target.required_unless' => __('seo::validation.redirect.target_required'),
            'target.regex' => __('seo::validation.redirect.target_format'),
            'target.not_regex' => __('seo::validation.redirect.control_characters'),
            'status.in' => __('seo::validation.redirect.status'),
            'site.in' => __('seo::validation.redirect.site'),
        ]);
    }

    /**
     * @param  ?Collection<int, self>  $active
     * @return array<string, mixed>
     */
    private static function rules(string $source, ?string $site, ?int $ignoreId, ?bool $taken, ?Collection $active): array
    {
        return [
            'site' => ['nullable', 'string', Rule::in(Sites::handles())],
            'source' => [
                // Control characters would go into the Location header (a line break starts a new header).
                'required', 'string', 'max:'.self::MAX_SOURCE, 'starts_with:/', 'not_regex:/[?#]/', 'regex:/^[^\x00-\x1F\x7F]*$/',
                function (string $attribute, mixed $value, Closure $fail) use ($site, $ignoreId, $taken) {
                    if ($taken ?? self::forSource((string) $value, $ignoreId, $site)) {
                        $fail(__('seo::validation.redirect.source_taken'));
                    }
                },
            ],
            'target' => [
                'nullable', 'required_unless:status,410', 'string', 'max:2048', 'regex:#^(/|https?://)#i', 'not_regex:/[\x00-\x1F\x7F]/',
                function (string $attribute, mixed $value, Closure $fail) use ($source, $site, $ignoreId, $active) {
                    $wildcards = substr_count($source, '*');
                    preg_match_all('/\$(\d+)/', (string) $value, $used);

                    if ($used[1] !== [] && max(array_map('intval', $used[1])) > $wildcards) {
                        $fail(__('seo::validation.redirect.target_number'));
                    } elseif (preg_match('#^https?://[^/]*\$\d#i', (string) $value)) {
                        // What a visitor typed would choose the site they are sent to (`https://example.com$1` → example.com.evil.test).
                        $fail(__('seo::validation.redirect.target_number_domain'));
                    } elseif ($loop = self::loop($source, (string) $value, $site, $ignoreId, $active)) {
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
     * `/blog/new/$1`) is allowed: the pages there usually exist. A rule for
     * one site is followed through that site's rules; one for every site,
     * through each site's.
     *
     * @param  ?Collection<int, self>  $active
     */
    private static function loop(string $source, string $target, ?string $site, ?int $ignoreId, ?Collection $active): ?string
    {
        if (! str_starts_with($target, '/') || $source === '') {
            return null;
        }

        if (self::pointsBack($source, $target)) {
            return __('seo::validation.redirect.points_back');
        }

        $source = self::normalize($source);

        if (str_contains($source, '*')) {
            return null;
        }

        foreach ($site !== null ? [$site] : (Sites::multiple() ? Sites::handles() : [null]) as $on) {
            if ($loop = self::loopOn($source, $target, $on, $ignoreId, $active)) {
                return $loop;
            }
        }

        return null;
    }

    /**
     * Follows the rules from the target, as a visitor on $site would be sent
     * on (null: a single site, every rule), and sees whether they come back.
     * Each step looks only at the rules that could match (an exact source, the
     * wildcards): among $active when given, else read for that step rather than
     * from the cached set, which an import would rebuild after every row. The
     * rule being edited stands aside for its new version. Ignoring case, an
     * exact source can't be looked up in SQL (see forSource()), so all are
     * read once.
     *
     * @param  ?Collection<int, self>  $active
     */
    private static function loopOn(string $source, string $target, ?string $site, ?int $ignoreId, ?Collection $active): ?string
    {
        if ($active !== null) {
            $rules = $active->reject(fn (self $rule) => $rule->id === $ignoreId)
                ->when($site, fn (Collection $rules, string $site) => $rules->filter(fn (self $rule) => $rule->site === $site || $rule->site === null));
            $candidates = fn (string $path) => $rules->filter(fn (self $rule) => $rule->isWildcard() || self::key(self::normalize($rule->source)) === self::key($path));
        } else {
            $query = fn () => self::query()->where('active', true)->whereKeyNot($ignoreId ?? 0)
                ->when($site, fn (Builder $query, string $site) => $query->appliesOn($site))
                ->select(['id', 'site', 'source', 'target', 'status']);
            $everything = self::ignoresCase() ? $query()->get() : null;
            $wildcards = $everything ?? $query()->where('source', 'like', '%*%')->get();
            $candidates = fn (string $path) => $everything ?? $query()->where('source', $path)->get()->merge($wildcards);
        }

        $path = self::normalize($target);
        $seen = [];

        for ($hops = 1; $hops <= self::MAX_HOPS && ! isset($seen[self::key($path)]); $hops++) {
            $seen[self::key($path)] = true;
            $next = app(Matcher::class)->matchAmong($candidates($path), $path, $site);

            if ($next === null || $next['target'] === null || ! str_starts_with($next['target'], '/')) {
                return null;
            }

            $path = self::normalize($next['target']);

            if (self::key($path) === self::key($source)) {
                return $hops === 1
                    ? __('seo::validation.redirect.loop')
                    : __('seo::validation.redirect.loop_steps', ['steps' => $hops]);
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
