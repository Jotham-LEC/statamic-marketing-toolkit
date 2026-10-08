<?php

namespace JothamLec\MarketingToolkit\Reports;

/**
 * A page as Renderer rendered it: its status and HTML, or, when it threw,
 * a generic message and the exception's class.
 */
final readonly class RenderedPage
{
    public function __construct(
        public int $status,
        public string $html = '',
        public ?string $error = null,
        public ?string $exception = null,
    ) {}

    /**
     * Whether the page rendered as a page to read: a 200 without an error.
     */
    public function ok(): bool
    {
        return $this->status === 200 && $this->error === null;
    }
}
