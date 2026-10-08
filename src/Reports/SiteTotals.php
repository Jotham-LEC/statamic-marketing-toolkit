<?php

namespace JothamLec\MarketingToolkit\Reports;

use JothamLec\MarketingToolkit\Reports\Rules\Rule;

/**
 * What a report adds up as its last step scores the pages one by one: how
 * many pages fail or warn on each check, each page's score, and how many
 * pages were scored, left out as noindex, or failed to render.
 */
final class SiteTotals
{
    /** @var array<string, array{label: string, weight: int, fail: int, warn: int}> by check */
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
     * A page that didn't render: it scores zero.
     */
    public function addError(): void
    {
        $this->errors++;
        $this->scores[] = 0;
    }

    /**
     * A page that rendered, with its results by check. $score is null for a
     * page search engines are told to skip: it is listed, not scored.
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
     * The site's score: the average of the pages' scores.
     */
    public function score(): ?int
    {
        return $this->scores === [] ? null : (int) round(array_sum($this->scores) / count($this->scores));
    }

    /**
     * The report's summary, as Report stores it.
     *
     * @return array{rules: array<string, array{label: string, weight: int, fail: int, warn: int}>, scored: int, noindex: int, errors: int}
     */
    public function summary(): array
    {
        return ['rules' => $this->rules, 'scored' => $this->scored, 'noindex' => $this->noindex, 'errors' => $this->errors];
    }
}
