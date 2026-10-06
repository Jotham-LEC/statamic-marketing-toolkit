<?php

namespace JothamLec\MarketingToolkit\Reports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use JothamLec\MarketingToolkit\Support\Sites;

/**
 * One run over the site: its settings when it started, how far it has got,
 * and, once done, the site's score and a count of pages failing each check.
 * On a multi-site install each report is of one site.
 *
 * @property int $id
 * @property ?string $site null on a single site
 * @property string $status running, done or failed
 * @property int $pages_total
 * @property int $pages_done
 * @property ?int $score
 * @property array<string, mixed> $settings
 * @property ?array<string, mixed> $summary
 * @property ?string $error
 * @property ?Carbon $finished_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Report extends Model
{
    public const string RUNNING = 'running';

    public const string DONE = 'done';

    public const string FAILED = 'failed';

    protected $table = 'seo_reports';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'pages_total' => 'integer',
            'pages_done' => 'integer',
            'score' => 'integer',
            'settings' => 'array',
            'summary' => 'array',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ReportPage, $this>
     */
    public function pages(): HasMany
    {
        return $this->hasMany(ReportPage::class);
    }

    public function isRunning(): bool
    {
        return $this->status === self::RUNNING;
    }

    /**
     * Reports of exactly this site; null: those of a single-site install.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOfSite(Builder $query, ?string $site): void
    {
        $site === null ? $query->whereNull('site') : $query->where('site', $site);
    }

    /**
     * The reports the control panel shows while $site is selected: its own,
     * and those from before the install had more than one site.
     *
     * @param  Builder<self>  $query
     */
    public function scopeShownOn(Builder $query, string $site): void
    {
        if (Sites::multiple()) {
            $query->where(fn (Builder $query) => $query->where('site', $site)->orWhereNull('site'));
        }
    }

    /**
     * The latest finished report the control panel shows while $site is
     * selected, for the overview and the dashboard widget.
     */
    public static function latestDone(string $site): ?self
    {
        return self::query()->shownOn($site)->where('status', self::DONE)->latest('id')->first();
    }

    /**
     * The pages the score is of: those scored (not a noindex page), or every
     * page in a report from before they were counted apart.
     */
    public function scoredPages(): int
    {
        return (int) ($this->summary['scored'] ?? $this->pages_total);
    }

    /**
     * Whether the control panel shows this report while $site is selected (see scopeShownOn()).
     */
    public function isShownOn(string $site): bool
    {
        return ! Sites::multiple() || $this->site === null || $this->site === $site;
    }

    public function settings(): ReportSettings
    {
        return new ReportSettings($this->settings);
    }
}
