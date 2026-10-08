<?php

namespace JothamLec\MarketingToolkit\Reports\Rules;

use JothamLec\MarketingToolkit\Reports\PageFacts;
use JothamLec\MarketingToolkit\Reports\Result;
use JothamLec\MarketingToolkit\Reports\SiteFacts;

/**
 * One check that a report runs on every page. Its weight sets how much it
 * counts towards the page's score, which is 3 for what keeps a page out of
 * search results, 2 for what shapes how it shows, and 1 for polish.
 */
abstract class Rule
{
    /**
     * Gets the check's key in the addon settings (`rule_{handle}`) and in a page's results.
     */
    abstract public static function handle(): string;

    /**
     * Gets the check's name, which is a translation key or plain text. Reports
     * store it as it is and translate it when they show it.
     */
    public function label(): string
    {
        return 'marketing-toolkit::reports.rules.'.static::handle();
    }

    abstract public function weight(): int;

    abstract public function check(string $url, PageFacts $page, SiteFacts $site): Result;

    /**
     * Determines whether the check applies to a page that search engines are told to skip.
     */
    public function appliesToNoindex(): bool
    {
        return false;
    }

    /**
     * Turns some items into a message parameter that lists the first few and
     * then "and N more", which is translated when the message is shown. Full
     * addresses are shortened to their paths unless $paths is false.
     *
     * @param  list<string>  $items
     * @return string|array{message: string, params: array{list: string, count: int}}
     */
    protected function listed(array $items, int $show = 3, bool $paths = true): string|array
    {
        $items = $paths ? array_map(fn ($url) => parse_url($url, PHP_URL_PATH) ?: '/', $items) : $items;
        $list = implode(', ', array_slice($items, 0, $show));
        $more = count($items) - $show;

        return $more > 0 ? ['message' => 'marketing-toolkit::reports.messages.and_more', 'params' => ['list' => $list, 'count' => $more]] : $list;
    }
}
