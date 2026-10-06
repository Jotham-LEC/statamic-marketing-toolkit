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
     * each emptied one is taken out, and the rest of its query stays.
     *
     * @param  array<string, mixed>  $tags
     */
    public static function withTags(string $target, array $tags): string
    {
        $fragment = str_contains($target, '#') ? '#'.substr($target, strpos($target, '#') + 1) : '';
        $target = $fragment === '' ? $target : substr($target, 0, strpos($target, '#'));
        [$base, $query] = array_pad(explode('?', $target, 2), 2, '');
        parse_str($query, $params);

        foreach (self::TAGS as $tag) {
            $value = trim((string) ($tags[$tag] ?? ''));
            unset($params[$tag]);

            if ($value !== '') {
                $params[$tag] = $value;
            }
        }

        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        return $base.($query === '' ? '' : '?'.$query).$fragment;
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
