<?php

namespace JothamLec\MarketingToolkit\Reports;

use JothamLec\MarketingToolkit\Support\Package;
use Statamic\Facades\Addon;
use Statamic\Facades\Blueprint;
use Statamic\Facades\YAML;

/**
 * The report settings editors set on the Settings tab of Marketing → Reports,
 * with the defaults in resources/blueprints/settings.yaml for anything not
 * saved yet.
 */
class ReportSettings
{
    /** `schedule_day`'s options, each at its index in Carbon's days of the week. */
    public const array DAYS = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

    /** @var array<string, mixed> */
    private array $values;

    /** @var array<string, mixed>|null the blueprint's defaults, read once */
    private static ?array $defaults = null;

    /**
     * @param  array<string, mixed>|null  $values  null: read the saved addon settings
     */
    public function __construct(?array $values = null)
    {
        $this->values = [...self::defaults(), ...array_filter($values ?? $this->saved(), fn ($value) => $value !== null)];
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
     * Each setting's default, from the settings blueprint (Statamic registers
     * the same file as the addon's settings blueprint). Read from the file, so
     * it works before Statamic has booted the addon too.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return self::$defaults ??= Blueprint::make()
            ->setContents(YAML::file(__DIR__.'/../../resources/blueprints/settings.yaml')->parse())
            ->fields()
            ->all()
            ->map
            ->defaultValue()
            ->all();
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
