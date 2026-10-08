<?php

namespace JothamLec\MarketingToolkit\Reports;

/**
 * The links on one page, as HtmlInspector sorts them. Each list holds an
 * address once.
 */
final readonly class PageLinks
{
    /**
     * @param  list<string>  $broken  paths on this site that lead nowhere
     * @param  list<string>  $redirected  paths on this site that a redirect sends on
     * @param  list<string>  $internal  every path on this site, normalized
     * @param  list<string>  $external  addresses on other sites, without a fragment
     */
    public function __construct(
        public array $broken,
        public array $redirected,
        public array $internal,
        public array $external,
    ) {}
}
