<?php

namespace JothamLec\MarketingToolkit\Redirects;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use JothamLec\MarketingToolkit\Support\Sites;
use Statamic\Facades\Site;
use Statamic\Facades\URL;

/**
 * This class finds the rule for a path on a site. The active rules that apply there are cached per site
 * as one array (an exact-match map and the wildcards, longest source first), and the cache is rebuilt
 * when a rule is saved or deleted, so a 404 costs a cache read rather than a query. A site's own rule
 * wins over a rule for every site from the same address. When matching ignores case, a second map holds
 * the sources case-folded, and the wildcards ignore case too.
 *
 * Sources are paths within the site, as Statamic's uri() and the automatic redirects write them. On a
 * site at example.com/fr/, for example, `/a-propos` means example.com/fr/a-propos. A path is passed in as
 * it was requested (`/fr/a-propos`), and it is matched without the site's folder first and then as it
 * is, so a rule typed with the folder (as it had to be before) keeps working.
 */
class Matcher
{
    /** We rename this key when the compiled form changes, so a set cached in the old form is never read. */
    private const string KEY = 'mt:redirect-rules';

    /** This is set while many rules are saved at once, such as in an import, so the cache flushes once at the end. */
    private static bool $deferred = false;

    /**
     * @param  string  $path  the path as requested, from the domain's root (with the site's folder)
     * @param  ?string  $site  a site handle, or null for the current site
     * @return array{id: int, status: int, target: ?string}|null
     */
    public function match(string $path, string $query = '', ?string $site = null): ?array
    {
        $site ??= Site::current()->handle();

        return $this->matchIn($this->rules($site), self::paths($path, $site), $query);
    }

    /**
     * Finds the rule for a path among the given rules, which are the rules that could match it. This
     * avoids reading (or rebuilding) every rule.
     *
     * @param  iterable<Redirect>  $redirects
     * @return array{id: int, status: int, target: ?string}|null
     */
    public function matchAmong(iterable $redirects, string $path, ?string $site = null): ?array
    {
        return $this->matchIn(self::compile(collect($redirects), $site), self::paths($path, $site), '');
    }

    /**
     * Returns the forms of a requested path that rules are matched against: first within the site (without
     * its folder), and then as requested. The path is already decoded and has no query string, so a `?`
     * here was a `%3F` that is part of the path.
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
            // A source in the exact case requested wins over one that differs only in case, unless
            // only the source in another case is the site's own.
            $exact = $rules['exact'][$path] ?? null;
            $folded = $rules['folded'][Redirect::key($path)] ?? null;

            if ($rule = ($folded && $folded['own'] && ! ($exact['own'] ?? 0) ? $folded : null) ?? $exact ?? $folded) {
                return $this->resolved($rule, [], $query);
            }
        }

        // This caches each path read backwards (see pattern()), by character or by byte, as each rule needs it.
        $backwards = [];

        foreach ($rules['wildcards'] as $rule) {
            foreach ($paths as $i => $path) {
                // When matching by character, a path that isn't valid UTF-8 matches no rule, as with PCRE's `u`.
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
     * Runs $callback, which saves many rules, and flushes the cache once when it is done. The flush happens
     * after the callback's transaction, so a request in between can't cache the old rules.
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
     * The rules are cached separately for each `redirects.case_sensitive` value, so changing it takes effect at once.
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
     * Compiles the rules into the three forms that match() reads. The `exact` and `folded` maps look a path
     * up directly, and the `wildcards` are tried in turn.
     *
     * @param  Collection<int, Redirect>  $redirects
     * @param  ?string  $site  the site they are matched on, whose own rules win over those for every site
     * @return array{exact: array<string, array<string, mixed>>, folded: array<string, array<string, mixed>>, wildcards: list<array<string, mixed>>}
     */
    private static function compile(Collection $redirects, ?string $site = null): array
    {
        [$wildcards, $exact] = $redirects->partition(fn (Redirect $redirect) => $redirect->isWildcard());

        return [
            'exact' => self::exactMap($exact, $site),
            'folded' => Redirect::ignoresCase() ? self::foldedMap($exact, $site) : [],
            'wildcards' => self::wildcardList($wildcards, $site),
        ];
    }

    /**
     * Maps the rules without a `*` by their source as stored, such as
     * `['/old-page' => rule, '/about' => rule]`. Where a rule for every site and the site's own rule
     * share a source, the site's own rule is added last, so it is the one that is kept.
     *
     * @param  Collection<int, Redirect>  $redirects
     * @return array<string, array<string, mixed>>
     */
    private static function exactMap(Collection $redirects, ?string $site): array
    {
        return $redirects
            ->sortBy(fn (Redirect $redirect) => self::isOwn($redirect, $site))
            // We normalise the source again for sources that were saved before they were decoded on save.
            ->mapWithKeys(fn (Redirect $redirect) => [Redirect::normalize($redirect->source) => self::rule($redirect, $site)])
            ->all();
    }

    /**
     * Maps the rules without a `*` by their case-folded source, for matching in any letter case, so
     * `/About-Us` and `/ABOUT-US` both become `['/about-us' => rule]`. Of sources that differ only in case
     * (saved before case was ignored), the oldest is kept, and the site's own rule wins over a rule for
     * every site.
     *
     * @param  Collection<int, Redirect>  $redirects
     * @return array<string, array<string, mixed>>
     */
    private static function foldedMap(Collection $redirects, ?string $site): array
    {
        return $redirects
            ->sortBy(fn (Redirect $redirect) => [self::isOwn($redirect, $site), -$redirect->id])
            ->mapWithKeys(fn (Redirect $redirect) => [Redirect::key(Redirect::normalize($redirect->source)) => self::rule($redirect, $site)])
            ->all();
    }

    /**
     * Lists the rules with a `*`, each with its pattern, with the longest source first. When several rules
     * match, the most specific one wins, and at the same length, the site's own rule wins. For example,
     * `/blog/*` becomes a rule with the pattern `#^(.*)/golb/$#`, which is matched against the path read
     * backwards (see pattern()). Read backwards, `/blog/hello` is `olleh/golb/`, which matches with
     * `olleh`, so `$1` is `hello`.
     *
     * @param  Collection<int, Redirect>  $redirects
     * @return list<array<string, mixed>>
     */
    private static function wildcardList(Collection $redirects, ?string $site): array
    {
        $ignoresCase = Redirect::ignoresCase();

        return $redirects
            ->sortByDesc(fn (Redirect $redirect) => [strlen($redirect->source), self::isOwn($redirect, $site)])
            ->map(fn (Redirect $redirect) => [
                ...self::rule($redirect, $site),
                ...self::pattern(Redirect::normalize($redirect->source), $ignoresCase),
            ])
            ->values()
            ->all();
    }

    /**
     * Returns the parts of a rule that the matcher keeps. The `own` value is 1 for a rule of the site it is
     * matched on, and 0 for a rule for every site.
     *
     * @return array{id: int, status: int, target: ?string, own: int}
     */
    private static function rule(Redirect $redirect, ?string $site): array
    {
        return ['id' => $redirect->id, 'status' => $redirect->status, 'target' => $redirect->target, 'own' => self::isOwn($redirect, $site)];
    }

    private static function isOwn(Redirect $redirect, ?string $site): int
    {
        return $site !== null && $redirect->site === $site ? 1 : 0;
    }

    /**
     * Turns a wildcard source into a regular expression, written backwards, to match a path read
     * backwards. Written forwards, each `*` was a greedy `(.*)`, and with several of them, PCRE tried every
     * way of sharing a long path between them. A rule that matched a path of a few hundred characters
     * could run out of PCRE's backtrack limit and silently fail to match, and a made-up address cost every
     * such rule that limit. Written backwards, each piece of text between two `*` is taken at its first
     * place (its last place forwards, where the greedy `*` before it puts it) and is never tried again
     * (an atomic group). Each `*` therefore matches what it did before, in time that grows only with the
     * path's length. When ignoring case, the pattern folds letters beyond A–Z too (`/CAFÉ/*` matches
     * `/café/x`) by reading by character, unless the source isn't valid UTF-8. Either way, the text that
     * the `*` matched keeps the visitor's case.
     *
     * @return array{pattern: string, chars: bool}
     */
    private static function pattern(string $source, bool $ignoresCase): array
    {
        $chars = $ignoresCase && mb_check_encoding($source, 'UTF-8');
        $pieces = array_map(fn (string $piece) => preg_quote($piece, '#'), explode('*', self::reverse($source, $chars)));
        // The pattern holds the source's end, each piece between two `*`, and then its start.
        // A wildcard always has at least one `*`, so the end and the start both exist.
        $end = array_shift($pieces);
        $start = array_pop($pieces);
        $between = implode('', array_map(fn (string $piece) => '(?>(.*?)'.$piece.')', $pieces));

        return [
            'pattern' => '#^'.$end.$between.'(.*)'.$start.'$#'.($ignoresCase ? ($chars ? 'iu' : 'i') : ''),
            'chars' => $chars,
        ];
    }

    /**
     * Reverses the text by character (UTF-8), or by byte.
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
            // The text that a `*` matched is decoded, so we encode it again before it goes back into an address.
            $target = preg_replace_callback('/\$(\d+)/', fn ($m) => $this->encode($captures[(int) $m[1] - 1] ?? ''), $target);
            $target = $this->withTrailingSlash($target);

            // The visitor's query string (such as UTM tags or a search) is passed on, ahead of any fragment.
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
