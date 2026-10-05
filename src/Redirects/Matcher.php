<?php

namespace JothamLec\Seo\Redirects;

use Illuminate\Support\Facades\Cache;

/**
 * Finds the rule for a path. The active rules are cached as one array (an
 * exact-match map and the wildcards, longest source first) and rebuilt when a
 * rule is saved or deleted, so a 404 costs a cache read, not a query.
 */
class Matcher
{
    private const string KEY = 'seo:redirects';

    /**
     * @return array{id: int, status: int, target: ?string}|null
     */
    public function match(string $path, string $query = ''): ?array
    {
        // Already decoded and without a query string: a `?` here was `%3F`, part of the path.
        $path = '/'.trim($path, '/');
        $rules = $this->rules();

        if ($rule = $rules['exact'][$path] ?? null) {
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
    }

    /**
     * @return array{exact: array<string, array<string, mixed>>, wildcards: list<array<string, mixed>>}
     */
    private function rules(): array
    {
        return Cache::rememberForever(self::KEY, function () {
            $rules = Redirect::query()->where('active', true)->get(['id', 'source', 'target', 'status']);
            $rule = fn (Redirect $redirect) => ['id' => $redirect->id, 'status' => $redirect->status, 'target' => $redirect->target];

            [$wildcards, $exact] = $rules->partition(fn (Redirect $redirect) => $redirect->isWildcard());

            return [
                // Normalized again for sources saved before they were decoded on save.
                'exact' => $exact->mapWithKeys(fn (Redirect $redirect) => [Redirect::normalize($redirect->source) => $rule($redirect)])->all(),
                // The most specific (longest) source wins when several match.
                'wildcards' => $wildcards
                    ->sortByDesc(fn (Redirect $redirect) => strlen($redirect->source))
                    ->map(fn (Redirect $redirect) => [
                        ...$rule($redirect),
                        'pattern' => '#^'.str_replace('\*', '(.*)', preg_quote(Redirect::normalize($redirect->source), '#')).'$#',
                    ])
                    ->values()
                    ->all(),
            ];
        });
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
     * On a site that adds trailing slashes, send visitors straight to the
     * slashed address rather than through a second redirect.
     */
    private function withTrailingSlash(string $target): string
    {
        if (config('seo.trailing_slash') !== 'add' || ! str_starts_with($target, '/')) {
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
