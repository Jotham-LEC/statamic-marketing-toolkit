<?php

namespace JothamLec\MarketingToolkit\Redirects;

use Closure;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use JothamLec\MarketingToolkit\Rules\RedirectTarget;
use JothamLec\MarketingToolkit\Rules\UniqueSource;
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

    protected $table = 'mt_redirects';

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

        // After the transaction, if there is one: a request in between could cache the old rules again.
        static::saved(fn () => DB::afterCommit(fn () => Matcher::flush()));
        static::deleted(fn () => DB::afterCommit(fn () => Matcher::flush()));
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
     * Rules the signed-in user may manage: those on the sites they may work
     * on, and those for every site only if they may work on every site, as a
     * rule for every site applies on sites they can't see (`/*` sending all
     * of them elsewhere).
     *
     * @param  Builder<self>  $query
     */
    public function scopeAccessible(Builder $query): void
    {
        if (Sites::multiple() && ! Sites::accessesAll()) {
            $query->whereIn('site', Sites::accessible());
        }
    }

    public function isAccessible(): bool
    {
        return $this->site === null ? Sites::accessesAll() : in_array($this->site, Sites::accessible(), true);
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
        return ! config('marketing-toolkit.redirects.case_sensitive');
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
     * Checks a redirect's fields, from a CSV row; the form checks them with
     * the same rules() and messages() (Http\Requests\SaveRedirect). $taken:
     * whether another rule already starts from the source, and $active: the
     * active rules, when the caller has them at hand (an import, which reads
     * them once rather than for every row); else they are looked up. $sites:
     * the handles a rule may name (the CP passes the user's own); every
     * site's when not given.
     *
     * @param  array<string, mixed>  $data
     * @param  ?Collection<int, self>  $active
     * @param  ?list<string>  $sites
     */
    public static function validator(array $data, ?int $ignoreId = null, ?bool $taken = null, ?Collection $active = null, ?array $sites = null): ValidatorContract
    {
        $site = is_string($data['site'] ?? null) && $data['site'] !== '' ? $data['site'] : null;

        return Validator::make($data, self::rules((string) ($data['source'] ?? ''), $site, $ignoreId, $taken, $active, $sites), self::messages());
    }

    /**
     * The checks a redirect's fields pass, from the form or a CSV row. Only
     * with every site among $sites may a rule name none (be for every site).
     *
     * @param  ?Collection<int, self>  $active
     * @param  ?list<string>  $sites  the handles a rule may name; null: every site's
     * @return array<string, mixed>
     */
    public static function rules(string $source, ?string $site, ?int $ignoreId = null, ?bool $taken = null, ?Collection $active = null, ?array $sites = null): array
    {
        $sites ??= Sites::handles();

        // Before the other checks: a regex on text that isn't UTF-8 fails, and Postgres refuses it.
        $utf8 = function (string $attribute, mixed $value, Closure $fail) {
            if (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
                $fail(__('marketing-toolkit::validation.redirect.encoding'));
            }
        };

        return [
            'site' => [Rule::requiredIf(Sites::multiple() && array_diff(Sites::handles(), $sites) !== []), 'nullable', 'string', Rule::in($sites)],
            'source' => [
                // Control characters would go into the Location header (a line break starts a new header).
                'required', 'string', 'bail', $utf8, 'max:'.self::MAX_SOURCE, 'starts_with:/', 'not_regex:/[?#]/', 'regex:/^[^\x00-\x1F\x7F]*$/',
                new UniqueSource($site, $ignoreId, $taken),
            ],
            'target' => [
                'nullable', 'required_unless:status,410', 'string', 'bail', $utf8, 'max:2048', 'regex:#^(/|https?://)#i', 'not_regex:/[\x00-\x1F\x7F]/',
                new RedirectTarget($source, $site, $ignoreId, $active),
            ],
            'status' => ['required', Rule::in(self::STATUSES)],
            'active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'source.required' => __('marketing-toolkit::validation.redirect.source_required'),
            'source.starts_with' => __('marketing-toolkit::validation.redirect.source_starts_with'),
            'source.not_regex' => __('marketing-toolkit::validation.redirect.source_query'),
            'source.regex' => __('marketing-toolkit::validation.redirect.control_characters'),
            'target.required_unless' => __('marketing-toolkit::validation.redirect.target_required'),
            'target.regex' => __('marketing-toolkit::validation.redirect.target_format'),
            'target.not_regex' => __('marketing-toolkit::validation.redirect.control_characters'),
            'status.in' => __('marketing-toolkit::validation.redirect.status'),
            'site.in' => __('marketing-toolkit::validation.redirect.site'),
            'site.required' => __('marketing-toolkit::validation.redirect.site_required'),
        ];
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
