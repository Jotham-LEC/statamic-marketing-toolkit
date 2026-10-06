<?php

namespace JothamLec\MarketingToolkit\Reports;

/**
 * How the link check runs: `seo.reports` in config/seo.php, over these
 * defaults. A report keeps a copy of them from when it started.
 */
class ReportSettings
{
    public const array DEFAULTS = [
        // Off, daily or weekly; weekly runs on Monday.
        'schedule' => 'weekly',
        'schedule_day' => 'monday',
        'schedule_time' => '03:00',
        // Sends a request to each site the pages link to (cached for a day).
        'external_links' => true,
        'exclude_collections' => [],
        'max_pages' => 0,
        'chunk_size' => 25,
        'keep_reports' => 10,
    ];

    /** @var array<string, mixed> */
    private array $values;

    /**
     * @param  array<string, mixed>|null  $values  null: read config/seo.php
     */
    public function __construct(?array $values = null)
    {
        $values ??= (array) config('seo.reports', []);
        $this->values = [...self::DEFAULTS, ...array_filter($values, fn ($value) => $value !== null)];
    }

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    /**
     * Every check runs, but the external link check can be turned off.
     */
    public function ruleEnabled(string $handle): bool
    {
        return $handle !== 'external_links' || (bool) $this->get('external_links');
    }

    /**
     * @return list<string>
     */
    public function excludedCollections(): array
    {
        return array_values(array_filter((array) $this->get('exclude_collections'), 'is_string'));
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values;
    }
}
