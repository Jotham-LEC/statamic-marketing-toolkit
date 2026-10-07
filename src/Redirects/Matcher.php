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
 *
 * Sources are paths within the site, as Statamic's uri() and the automatic
 * redirects write them: on a site at example.com/fr/, `/a-propos` is
 * example.com/fr/a-propos. A path is asked for as requested (`/fr/a-propos`),
 * and is matched without the site's folder first, then as it is, so a rule
 * typed with the folder (as one had to before) keeps working.
 */
class Matcher
{
    /** Renamed when the compiled form changes, so a cached set in the old one is never read. */
    private const string KEY = 'mt:redirect-rules';

    /** Set while many rules are saved at once (an import), which flush once at the end. */
    private static bool $deferred = false;

    /**
     * @param  string  $path  as requested, from the domain's root (with the site's folder)
     * @param  ?string  $site  a site handle; null: the current site
     * @return array{id: int, status: int, target: ?string}|null
     */
    public function match(string $path, string $query = '', ?string $site = null): ?array
    {
        $site ??= Site::current()->handle();

        return $this->matchIn($this->rules($site), self::paths($path, $site), $query);
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
        return $this->matchIn(self::compile(collect($redirects), $site), self::paths($path, $site), '');
    }

    /**
     * The forms of a requested path rules are matched against: within the site
     * (without its folder), then as requested. Already decoded and without a
     * query string: a `?` here was `%3F`, part of the path.
     *
     * @return list<string>
     */
    private static function paths(string $path, ?string $site): array
    {
        $path = '/'.trim($path, '/');
        $within = Sites::within($path, $site);

        return array_values(array_unique(array_filter([$within === null ? null : '/'.trim($within, '/'), $path])));
    }

    /**
     * An exact source wins over a wildcard, whichever form of the path it matches.
     *
     * @param  array{exact: array<string, array<string, mixed>>, folded: array<string, array<string, mixed>>, wildcards: list<array<string, mixed>>}  $rules
     * @param  list<string>  $paths
     * @return array{id: int, status: int, target: ?string}|null
     */
    private function matchIn(array $rules, array $paths, string $query): ?array
    {
        foreach ($paths as $path) {
            // A source in the very case asked for wins over one that differs only in case,
            // unless only the one in another case is the site's own.
            $exact = $rules['exact'][$path] ?? null;
            $folded = $rules['folded'][Redirect::key($path)] ?? null;

            if ($rule = ($folded && $folded['own'] && ! ($exact['own'] ?? 0) ? $folded : null) ?? $exact ?? $folded) {
                return $this->resolved($rule, [], $query);
            }
        }

        // Each path backwards (see pattern()), by character or by byte as each rule needs it.
        $backwards = [];

        foreach ($rules['wildcards'] as $rule) {
            foreach ($paths as $i => $path) {
                // Matched by character, a path that isn't valid UTF-8 matches no rule, as PCRE's `u` would have it.
                if ($rule['chars'] && ! mb_check_encoding($path, 'UTF-8')) {
                    continue;
                }

                if (preg_match($rule['pattern'], $backwards[$rule['chars']][$i] ??= self::reverse($path, $rule['chars']), $captures)) {
                    $captures = array_map(fn (string $capture) => self::reverse($capture, $rule['chars']), array_reverse(array_slice($captures, 1)));

                    return $this->resolved($rule, $captures, $query);
                }
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
                    ...self::pattern(Redirect::normalize($redirect->source), $ignoresCase),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * A wildcard source as a regular expression, written backwards, to match a
     * path read backwards. Forwards, each `*` was a greedy `(.*)`, and with
     * several, PCRE tried every way of sharing a long path between them: a
     * rule that matched a path of a few hundred characters could run out of
     * PCRE's backtrack limit and silently not match, and a made-up address
     * cost every such rule that limit. Backwards, each piece of text between
     * two `*` is taken at its first place (its last, forwards: where the
     * greedy `*` before it puts it) and never tried again (an atomic group),
     * so each `*` matches what it did, in time that grows with the path's
     * length alone. Ignoring case, it folds letters beyond A–Z too (`/CAFÉ/*`
     * matches `/café/x`), reading by character, unless the source isn't
     * valid UTF-8. What the `*` matched keeps the visitor's case either way.
     *
     * @return array{pattern: string, chars: bool}
     */
    private static function pattern(string $source, bool $ignoresCase): array
    {
        $chars = $ignoresCase && mb_check_encoding($source, 'UTF-8');
        $pieces = array_map(fn (string $piece) => preg_quote($piece, '#'), explode('*', self::reverse($source, $chars)));
        // The source's end, then each piece between two `*`, then its start (a wildcard has at least one `*`).
        $end = array_shift($pieces);
        $start = array_pop($pieces);
        $between = implode('', array_map(fn (string $piece) => '(?>(.*?)'.$piece.')', $pieces));

        return [
            'pattern' => '#^'.$end.$between.'(.*)'.$start.'$#'.($ignoresCase ? ($chars ? 'iu' : 'i') : ''),
            'chars' => $chars,
        ];
    }

    /**
     * Backwards by character (UTF-8), or by byte.
     */
    private static function reverse(string $text, bool $chars): string
    {
        return $chars ? implode('', array_reverse(mb_str_split($text, 1, 'UTF-8'))) : strrev($text);
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
