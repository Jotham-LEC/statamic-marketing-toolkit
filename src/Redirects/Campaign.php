<?php

namespace JothamLec\MarketingToolkit\Redirects;

/**
 * A campaign link is a short address on the site, such as /go/linkedin, that redirects to a page with
 * UTM tags, so the campaign shows in Analytics and in each lead's source. The tags are stored in the
 * redirect's target, and the redirect counts the clicks.
 */
final class Campaign
{
    public const array TAGS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];

    /**
     * Returns $target with these UTM tags. Each tag that is set replaces the target's own, each emptied
     * tag is taken out, and the rest of the query stays exactly as typed, byte for byte. We avoid
     * parse_str() here, because a `$1` that a wildcard fills in, `a.b`, `+`, a bare `?flag`, or a
     * repeated key would not survive the trip.
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
     * @return array<string, string>
     */
    public static function tags(string $target): array
    {
        parse_str((string) parse_url($target, PHP_URL_QUERY), $params);

        return array_map('strval', array_intersect_key(array_filter($params, 'is_string'), array_flip(self::TAGS)));
    }
}
