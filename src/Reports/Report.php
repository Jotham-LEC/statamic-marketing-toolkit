<?php

namespace JothamLec\MarketingToolkit\Reports;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use JothamLec\MarketingToolkit\Models\Concerns\BelongsToSite;

/**
 * One run over the site. It holds the settings it started with, how far it
 * has got, and, once done, the site's score and a count of the pages failing
 * each check. On a multi-site install, each report covers one site.
 *
 * @property int $id
 * @property ?string $site null on a single-site install
 * @property string $status running, done or failed
 * @property int $pages_total
 * @property int $pages_done
 * @property ?int $score
 * @property array<string, mixed> $settings
 * @property ?array{rules: array<string, array<string, mixed>>, scored?: int, noindex?: int, errors?: int} $summary as SiteTotals::summary() returns it; older reports lack scored and the counts after it
 * @property ?string $error
 * @property ?Carbon $finished_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Report extends Model
{
    use BelongsToSite;

    public const string RUNNING = 'running';

    public const string DONE = 'done';

    public const string FAILED = 'failed';

    protected $table = 'mt_reports';

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
     * Gets the latest finished report that the control panel shows while $site
     * is selected, for the overview and the dashboard widget.
     */
    public static function latestDone(string $site): ?self
    {
        return self::query()->shownOn($site)->where('status', self::DONE)->latest('id')->first();
    }

    /**
     * Gets the number of pages that the score covers. This is the scored pages
     * (not the noindex ones) or, in a report from before they were counted
     * separately, every page.
     */
    public function scoredPages(): int
    {
        return (int) ($this->summary['scored'] ?? $this->pages_total);
    }

    public function settings(): ReportSettings
    {
        return new ReportSettings($this->settings);
    }
}
