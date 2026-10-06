<?php

namespace JothamLec\MarketingToolkit\Redirects;

/**
 * A campaign link (Pro): a short address on the site, like /go/linkedin,
 * that redirects to a page with UTM tags, so the campaign shows in
 * Analytics and in each lead's source. The tags live in the redirect's
 * target; the redirect counts the clicks.
 */
final class Campaign
{
    public const array TAGS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];

    /**
     * $target with these UTM tags: each set one replaces the target's own,
     * each emptied one is taken out, and the rest of its query stays as typed,
     * byte for byte: a `$1` a wildcard fills in, `a.b`, `+`, a bare `?flag` or
     * a repeated key would not survive a trip through parse_str().
     *
     * @param  array<string, mixed>  $tags
     */
    public static function withTags(string $target, array $tags): string
    {
        $wanted = array_filter(array_map(fn ($value) => trim((string) $value), array_intersect_key($tags, array_flip(self::TAGS))), fn (string $value) => $value !== '');

        $current = self::tags($target);
        ksort($wanted);
        ksort($current);

        if ($wanted === $current) {
            return $target;
        }

        [$target, $fragment] = array_pad(explode('#', $target, 2), 2, null);
        [$base, $query] = array_pad(explode('?', $target, 2), 2, '');

        $kept = array_filter(
            explode('&', $query),
            fn (string $pair) => $pair !== '' && ! in_array(urldecode(explode('=', $pair, 2)[0]), self::TAGS, true),
        );

        foreach (self::TAGS as $tag) {
            if (isset($wanted[$tag])) {
                $kept[] = rawurlencode($tag).'='.rawurlencode($wanted[$tag]);
            }
        }

        return $base.($kept === [] ? '' : '?'.implode('&', $kept)).($fragment === null ? '' : '#'.$fragment);
    }

    /**
     * The UTM tags in $target's query string.
     *
     * @return array<string, string>
     */
    public static function tags(string $target): array
    {
        parse_str((string) parse_url($target, PHP_URL_QUERY), $params);

        return array_map('strval', array_intersect_key(array_filter($params, 'is_string'), array_flip(self::TAGS)));
    }
}
