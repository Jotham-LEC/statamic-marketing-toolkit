<?php

namespace JothamLec\MarketingToolkit\Reports\Rules;

use JothamLec\MarketingToolkit\Reports\PageFacts;
use JothamLec\MarketingToolkit\Reports\Result;
use JothamLec\MarketingToolkit\Reports\SiteFacts;

/**
 * One check a report runs on every page. Its weight is how much it counts
 * towards the page's score: 3 for what keeps a page out of search results,
 * 2 for what shapes how it shows, 1 for polish.
 */
abstract class Rule
{
    /**
     * The key in the addon settings (`rule_{handle}`) and in a page's results.
     */
    abstract public static function handle(): string;

    /**
     * The check's name: a translation key, or plain text. Reports keep it as
     * it is and translate it when shown.
     */
    abstract public function label(): string;

    abstract public function weight(): int;

    abstract public function check(string $url, PageFacts $page, SiteFacts $site): Result;

    /**
     * Whether the check applies to a page search engines are told to skip.
     */
    public function appliesToNoindex(): bool
    {
        return false;
    }

    /**
     * The paths of some pages, for a message: the first few, then "and N more".
     *
     * @param  list<string>  $urls
     */
    protected function list(array $urls, int $show = 3): string
    {
        $list = $this->listed($urls, $show);

        return is_array($list) ? Result::translate($list['message'], $list['params']) : $list;
    }

    /**
     * As list(), as a message parameter, translated when the message is shown.
     * Full addresses are shortened to their paths unless $paths is false.
     *
     * @param  list<string>  $items
     * @return string|array{message: string, params: array{list: string, count: int}}
     */
    protected function listed(array $items, int $show = 3, bool $paths = true): string|array
    {
        $items = $paths ? array_map(fn ($url) => parse_url($url, PHP_URL_PATH) ?: '/', $items) : $items;
        $list = implode(', ', array_slice($items, 0, $show));
        $more = count($items) - $show;

        return $more > 0 ? ['message' => 'seo::reports.messages.and_more', 'params' => ['list' => $list, 'count' => $more]] : $list;
    }
}
