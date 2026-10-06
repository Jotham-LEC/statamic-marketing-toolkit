<?php

namespace JothamLec\Seo\Redirects;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Statamic\Facades\URL;

/**
 * Finds the rule for a path. The active rules are cached as one array (an
 * exact-match map and the wildcards, longest source first) and rebuilt when a
 * rule is saved or deleted, so a 404 costs a cache read, not a query. When
 * matching ignores case, a second map holds the sources case-folded, and the
 * wildcards ignore case too.
 */
class Matcher
{
    private const string KEY = 'seo:redirects';

    /**
     * @return array{id: int, status: int, target: ?string}|null
     */
    public function match(string $path, string $query = ''): ?array
    {
        return $this->matchIn($this->rules(), $path, $query);
    }

    /**
     * The rule for a path among the given ones: the rules that could match
     * it, without reading (or rebuilding) every rule.
     *
     * @param  iterable<Redirect>  $redirects
     * @return array{id: int, status: int, target: ?string}|null
     */
    public function matchAmong(iterable $redirects, string $path): ?array
    {
        return $this->matchIn(self::compile(collect($redirects)), $path, '');
    }

    /**
     * @param  array{exact: array<string, array<string, mixed>>, folded: array<string, array<string, mixed>>, wildcards: list<array<string, mixed>>}  $rules
     * @return array{id: int, status: int, target: ?string}|null
     */
    private function matchIn(array $rules, string $path, string $query): ?array
    {
        // Already decoded and without a query string: a `?` here was `%3F`, part of the path.
        $path = '/'.trim($path, '/');

        // A source in the very case asked for wins over one that differs only in case.
        if ($rule = $rules['exact'][$path] ?? $rules['folded'][Redirect::key($path)] ?? null) {
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
        Cache::forget(self::KEY);
        Cache::forget(self::KEY.':any-case');
    }

    /**
     * Cached apart for each `redirects.case_sensitive`, so changing it takes effect at once.
     *
     * @return array{exact: array<string, array<string, mixed>>, folded: array<string, array<string, mixed>>, wildcards: list<array<string, mixed>>}
     */
    private function rules(): array
    {
        return Cache::rememberForever(Redirect::ignoresCase() ? self::KEY.':any-case' : self::KEY, fn () => self::compile(Redirect::query()->where('active', true)->get(['id', 'source', 'target', 'status'])));
    }

    /**
     * @param  Collection<int, Redirect>  $redirects
     * @return array{exact: array<string, array<string, mixed>>, folded: array<string, array<string, mixed>>, wildcards: list<array<string, mixed>>}
     */
    private static function compile(Collection $redirects): array
    {
        $rule = fn (Redirect $redirect) => ['id' => $redirect->id, 'status' => $redirect->status, 'target' => $redirect->target];
        $ignoresCase = Redirect::ignoresCase();

        [$wildcards, $exact] = $redirects->partition(fn (Redirect $redirect) => $redirect->isWildcard());

        return [
            // Normalized again for sources saved before they were decoded on save.
            'exact' => $exact->mapWithKeys(fn (Redirect $redirect) => [Redirect::normalize($redirect->source) => $rule($redirect)])->all(),
            // Of sources that differ only in case (saved before case was ignored), the oldest.
            'folded' => $ignoresCase
                ? $exact->sortByDesc('id')->mapWithKeys(fn (Redirect $redirect) => [Redirect::key(Redirect::normalize($redirect->source)) => $rule($redirect)])->all()
                : [],
            // The most specific (longest) source wins when several match.
            'wildcards' => $wildcards
                ->sortByDesc(fn (Redirect $redirect) => strlen($redirect->source))
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
