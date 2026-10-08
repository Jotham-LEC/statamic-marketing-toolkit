<?php

namespace JothamLec\MarketingToolkit\Reports;

use JothamLec\MarketingToolkit\Reports\Rules\Rule;

/**
 * Adds up a report's totals as its last step scores the pages one by one. It
 * counts how many pages fail or warn on each check, keeps each page's score,
 * and counts how many pages were scored, left out as noindex, or failed to render.
 */
final class SiteTotals
{
    /** @var array<string, array{label: string, weight: int, fail: int, warn: int}> keyed by check */
    private array $rules = [];

    /** @var list<int> */
    private array $scores = [];

    private int $scored = 0;

    private int $noindex = 0;

    private int $errors = 0;

    /**
     * @param  list<Rule>  $rules  the checks the report runs
     */
    public function __construct(array $rules)
    {
        foreach ($rules as $rule) {
            $this->rules[$rule::handle()] = ['label' => $rule->label(), 'weight' => $rule->weight(), 'fail' => 0, 'warn' => 0];
        }
    }

    /**
     * Adds a page that didn't render, which scores zero.
     */
    public function addError(): void
    {
        $this->errors++;
        $this->scores[] = 0;
    }

    /**
     * Adds a page that rendered, with its results keyed by check. $score is null
     * for a page that search engines are told to skip, because it is listed but not scored.
     *
     * @param  array<string, array{status: string}>  $results
     */
    public function addPage(array $results, ?int $score, bool $noindex): void
    {
        foreach ($results as $handle => $result) {
            if ($result['status'] !== Result::PASS) {
                $this->rules[$handle][$result['status']]++;
            }
        }

        $noindex ? $this->noindex++ : $this->scored++;

        if ($score !== null) {
            $this->scores[] = $score;
        }
    }

    /**
     * Gets the site's score, which is the average of the pages' scores.
     */
    public function score(): ?int
    {
        return $this->scores === [] ? null : (int) round(array_sum($this->scores) / count($this->scores));
    }

    /**
     * Gets the report's summary in the form that Report stores.
     *
     * @return array{rules: array<string, array{label: string, weight: int, fail: int, warn: int}>, scored: int, noindex: int, errors: int}
     */
    public function summary(): array
    {
        return ['rules' => $this->rules, 'scored' => $this->scored, 'noindex' => $this->noindex, 'errors' => $this->errors];
    }
}
