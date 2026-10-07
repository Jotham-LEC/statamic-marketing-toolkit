<?php

namespace JothamLec\MarketingToolkit\Reports;

use JothamLec\MarketingToolkit\Support\Package;
use Statamic\Facades\Addon;

/**
 * The report settings editors set under Tools → Addons → SEO, with the
 * blueprint's defaults for anything not saved yet.
 */
class ReportSettings
{
    public const array DEFAULTS = [
        // Off until asked for: it sends requests to the sites a page links to.
        'rule_external_links' => false,
        'title_min' => 30,
        'title_max' => 60,
        'description_min' => 50,
        'description_max' => 160,
        'excluded_collections' => [],
        'max_pages' => 0,
        'chunk_size' => 25,
        'keep_reports' => 10,
        'schedule' => 'off',
        'schedule_day' => 'monday',
        'schedule_time' => '03:00',
    ];

    /** @var array<string, mixed> */
    private array $values;

    /**
     * @param  array<string, mixed>|null  $values  null: read the saved addon settings
     */
    public function __construct(?array $values = null)
    {
        $this->values = [...self::DEFAULTS, ...array_filter($values ?? $this->saved(), fn ($value) => $value !== null)];
    }

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    public function ruleEnabled(string $handle): bool
    {
        return (bool) ($this->values['rule_'.$handle] ?? true);
    }

    /**
     * @return list<string>
     */
    public function excludedCollections(): array
    {
        return array_values(array_filter((array) $this->get('excluded_collections'), 'is_string'));
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values;
    }

    /**
     * @return array<string, mixed>
     */
    private function saved(): array
    {
        try {
            return Addon::get(Package::NAME)?->settings()->all() ?? [];
        } catch (\Throwable) {
            // Before Statamic has booted the addon (an early config read).
            return [];
        }
    }
}
