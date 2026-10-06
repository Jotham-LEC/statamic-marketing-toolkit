<?php

namespace JothamLec\MarketingToolkit\Tests;

/**
 * Runs a test file in the free edition: `uses(FreeEdition::class)`.
 */
trait FreeEdition
{
    protected function edition(): string
    {
        return 'free';
    }
}
