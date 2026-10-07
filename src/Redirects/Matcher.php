<?php

namespace JothamLec\MarketingToolkit\Redirects;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use JothamLec\MarketingToolkit\Support\Sites;
use Statamic\Facades\Site;
use Statamic\Facades\URL;

/**
 * Finds the rule for a path on a site. The active rules that apply there are
 * cached per site as one array (an exact-match map and the wildcards, longest
 * source first) and rebuilt when a rule is saved or deleted, so a 404 costs a
 * cache read, not a query. A site's own rule wins over one for every site
 * from the same address. When matching ignores case, a second map holds the
 * sources case-folded, and the wildcards ignore case too.
 */
class Matcher
{
    private const string KEY = 'mt:redirects';

    /** Set while many rules are saved at once (an import), which flush once at the end. */
    private static bool $deferred = false;

    /**
     * @param  ?string  $site  a site handle; null: the current site
     * @return array{id: int, status: int, target: ?string}|null
     */
    public function match(string $path, string $query = '', ?string $site = null): ?array
    {
        return $this->matchIn($this->rules($site ?? Site::current()->handle()), $path, $query);
    }

    /**
     * The rule for a path among the given ones: the rules that could match
     * it, without reading (or rebuilding) every rule.
     *
     * @param  iterable<Redirect>  $redirects
     * @return array{id: int, status: int, target: ?string}|null
     */
    public function matchAmong(iterable $redirects, string $path, ?string $site = null): ?array
    {
        return $this->matchIn(self::compile(collect($redirects), $site), $path, '');
    }

    /**
     * @param  array{exact: array<string, array<string, mixed>>, folded: array<string, array<string, mixed>>, wildcards: list<array<string, mixed>>}  $rules
     * @return array{id: int, status: int, target: ?string}|null
     */
    private function matchIn(array $rules, string $path, string $query): ?array
    {
        // Already decoded and without a query string: a `?` here was `%3F`, part of the path.
        $path = '/'.trim($path, '/');

        // A source in the very case asked for wins over one that differs only in case,
        // unless only the one in another case is the site's own.
        $exact = $rules['exact'][$path] ?? null;
        $folded = $rules['folded'][Redirect::key($path)] ?? null;

        if ($rule = ($folded && $folded['own'] && ! ($exact['own'] ?? 0) ? $folded : null) ?? $exact ?? $folded) {
            return $this->resolved($rule, [], $query);
        }

        foreach ($rules['wildcards'] as $rule) {
            if (preg_match($rule['pattern'], $path, $captures)) {
                return $this->resolved($rule, array_slice($captures, 1), $query);
            }
        }

        return null;
    }

    public static function flush(): void
    {
        if (self::$deferred) {
            return;
        }

        foreach (Sites::handles() as $site) {
            Cache::forget(self::KEY.':'.$site);
            Cache::forget(self::KEY.':'.$site.':any-case');
        }
    }

    /**
     * Runs $callback, which saves many rules, and flushes once when it is done
     * (after its transaction, so a request between can't cache the old rules).
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function flushAfter(Closure $callback): mixed
    {
        self::$deferred = true;

        try {
            return $callback();
        } finally {
            self::$deferred = false;
            self::flush();
        }
    }

    /**
     * Cached apart for each `redirects.case_sensitive`, so changing it takes effect at once.
     *
     * @return array{exact: array<string, array<string, mixed>>, folded: array<string, array<string, mixed>>, wildcards: list<array<string, mixed>>}
     */
    private function rules(string $site): array
    {
        return Cache::rememberForever(self::KEY.':'.$site.(Redirect::ignoresCase() ? ':any-case' : ''), fn () => self::compile(
            Redirect::query()->where('active', true)->appliesOn($site)->get(['id', 'site', 'source', 'target', 'status']),
            $site,
        ));
    }

    /**
     * @param  Collection<int, Redirect>  $redirects
     * @param  ?string  $site  the site they are matched on: its own rules win over those for every site
     * @return array{exact: array<string, array<string, mixed>>, folded: array<string, array<string, mixed>>, wildcards: list<array<string, mixed>>}
     */
    private static function compile(Collection $redirects, ?string $site = null): array
    {
        $ignoresCase = Redirect::ignoresCase();
        $own = fn (Redirect $redirect) => $site !== null && $redirect->site === $site ? 1 : 0;
        $rule = fn (Redirect $redirect) => ['id' => $redirect->id, 'status' => $redirect->status, 'target' => $redirect->target, 'own' => $own($redirect)];

        [$wildcards, $exact] = $redirects->partition(fn (Redirect $redirect) => $redirect->isWildcard());

        return [
            // Normalized again for sources saved before they were decoded on save.
            // The site's own rules last, so they overwrite those for every site.
            'exact' => $exact->sortBy($own)->mapWithKeys(fn (Redirect $redirect) => [Redirect::normalize($redirect->source) => $rule($redirect)])->all(),
            // Of sources that differ only in case (saved before case was ignored), the oldest; the site's own winning.
            'folded' => $ignoresCase
                ? $exact->sortBy(fn (Redirect $redirect) => [$own($redirect), -$redirect->id])->mapWithKeys(fn (Redirect $redirect) => [Redirect::key(Redirect::normalize($redirect->source)) => $rule($redirect)])->all()
                : [],
            // The most specific (longest) source wins when several match; at the same length, the site's own.
            'wildcards' => $wildcards
                ->sortByDesc(fn (Redirect $redirect) => [strlen($redirect->source), $own($redirect)])
                ->map(fn (Redirect $redirect) => [
                    ...$rule($redirect),
                    'pattern' => self::pattern(Redirect::normalize($redirect->source), $ignoresCase),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * A wildcard source as a regular expression. Ignoring case, it folds letters
     * beyond A–Z too (`/CAFÉ/*` matches `/café/x`), unless the source isn't valid
     * UTF-8. What the `*` matched keeps the visitor's case either way.
     */
    private static function pattern(string $source, bool $ignoresCase): string
    {
        $pattern = '#^'.str_replace('\*', '(.*)', preg_quote($source, '#')).'$#';

        if (! $ignoresCase) {
            return $pattern;
        }

        return $pattern.(mb_check_encoding($source, 'UTF-8') ? 'iu' : 'i');
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  list<string>  $captures
     * @return array{id: int, status: int, target: ?string}
     */
    private function resolved(array $rule, array $captures, string $query): array
    {
        $target = $rule['target'];

        if ($target !== null) {
            // What a `*` matched is decoded text; it goes back into an address encoded.
            $target = preg_replace_callback('/\$(\d+)/', fn ($m) => $this->encode($captures[(int) $m[1] - 1] ?? ''), $target);
            $target = $this->withTrailingSlash($target);

            // The visitor's query string travels on (utm tags, a search), ahead of any fragment.
            if ($query !== '') {
                [$address, $fragment] = array_pad(explode('#', $target, 2), 2, null);
                $target = $address.(str_contains($address, '?') ? '&' : '?').$query.($fragment !== null ? '#'.$fragment : '');
            }
        }

        return ['id' => $rule['id'], 'status' => $rule['status'], 'target' => $target];
    }

    /**
     * On a site that has Statamic add trailing slashes (URL::enforceTrailingSlashes()),
     * send visitors straight to the slashed address rather than through a second redirect.
     */
    private function withTrailingSlash(string $target): string
    {
        if (! URL::isEnforcingTrailingSlashes() || ! str_starts_with($target, '/')) {
            return $target;
        }

        $end = strcspn($target, '?#');
        $path = substr($target, 0, $end);

        if ($path !== '/' && ! str_ends_with($path, '/') && pathinfo($path, PATHINFO_EXTENSION) === '') {
            $path .= '/';
        }

        return $path.substr($target, $end);
    }

    private function encode(string $capture): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $capture)));
    }
}
