<?php

namespace JothamLec\MarketingToolkit\Reports;

/**
 * The links on one page, as HtmlInspector sorts them. Each list holds an
 * address only once.
 */
final readonly class PageLinks
{
    /**
     * @param  list<string>  $broken  paths on this site that lead nowhere
     * @param  list<string>  $redirected  paths on this site that a redirect sends elsewhere
     * @param  list<string>  $internal  every path on this site that the page links to, normalised
     * @param  list<string>  $external  addresses on other sites, without a fragment
     */
    public function __construct(
        public array $broken,
        public array $redirected,
        public array $internal,
        public array $external,
    ) {}
}
