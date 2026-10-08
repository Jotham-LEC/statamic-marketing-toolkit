<?php

namespace JothamLec\MarketingToolkit\Reports;

/**
 * A page as Renderer rendered it. It holds the status and HTML or, when the
 * render threw an exception, a generic message and the exception's class.
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
     * Determines whether the page rendered as a page to read, which means a 200 without an error.
     */
    public function ok(): bool
    {
        return $this->status === 200 && $this->error === null;
    }
}
