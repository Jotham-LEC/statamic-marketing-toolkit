<?php

namespace JothamLec\MarketingToolkit\Reports\Rules;

use JothamLec\MarketingToolkit\Reports\PageFacts;
use JothamLec\MarketingToolkit\Reports\Result;

/**
 * One check the link check runs on every page.
 */
abstract class Rule
{
    /**
     * The key in a page's results.
     */
    abstract public static function handle(): string;

    /**
     * The check's name: a translation key, or plain text. Reports keep it as
     * it is and translate it when shown.
     */
    abstract public function label(): string;

    abstract public function check(string $url, PageFacts $page): Result;

    /**
     * Whether the check applies to a page search engines are told to skip.
     */
    public function appliesToNoindex(): bool
    {
        return false;
    }

    /**
     * As a message parameter, the first few items, then "and N more",
     * translated when the message is shown.
     *
     * @param  list<string>  $items
     * @return string|array{message: string, params: array{list: string, count: int}}
     */
    protected function listed(array $items, int $show = 5): string|array
    {
        $list = implode(', ', array_slice($items, 0, $show));
        $more = count($items) - $show;

        return $more > 0 ? ['message' => 'seo::reports.messages.and_more', 'params' => ['list' => $list, 'count' => $more]] : $list;
    }
}
