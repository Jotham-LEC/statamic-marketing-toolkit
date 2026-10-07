<?php

namespace JothamLec\MarketingToolkit\Commands;

use Illuminate\Console\Command;
use JothamLec\MarketingToolkit\Reports\Report as SeoReport;
use JothamLec\MarketingToolkit\Reports\Runner;
use JothamLec\MarketingToolkit\Support\Sites;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\Site;

/**
 * `php please mt:report`: check every published page now, in this process,
 * and print the scores. The scheduler runs it when reports are scheduled. On
 * a multi-site install it reports on each site in turn, or on `--site`.
 */
class Report extends Command
{
    use RunsInPlease;

    protected $signature = 'statamic:mt:report {--site= : The handle of one site to report on (default: every site)}';

    protected $description = 'Check every published page against the SEO rules and score the site';

    public function handle(Runner $runner): int
    {
        $site = $this->option('site');

        if ($site !== null && ! Site::get($site)) {
            $this->components->error("There is no site [{$site}].");

            return self::FAILURE;
        }

        $sites = $site !== null ? [$site] : (Sites::multiple() ? Sites::handles() : [null]);
        $result = self::SUCCESS;

        foreach ($sites as $handle) {
            if (Sites::multiple()) {
                $this->components->info('Site: '.Site::get($handle)->name());
            }

            $result = max($result, $this->report($runner, $handle));
        }

        return $result;
    }

    private function report(Runner $runner, ?string $site): int
    {
        $report = $runner->start(site: $site);

        if ($report->pages_done > 0 && $report->isRunning()) {
            $this->components->info("Continuing report #{$report->id}, already running.");
        }

        $bar = $this->output->createProgressBar(max(1, $report->pages_total));
        $bar->setProgress($report->pages_done);

        $report = $runner->runToEnd($report, fn (SeoReport $report) => $bar->setProgress($report->pages_done));
        $bar->finish();
        $this->newLine(2);

        if ($report->status !== SeoReport::DONE) {
            $this->components->error("Report #{$report->id} failed: ".__((string) $report->error));

            return self::FAILURE;
        }

        $summary = $report->summary ?? [];
        $this->components->info("Report #{$report->id}: score ".($report->score ?? '–')." / 100 over {$summary['scored']} pages ({$summary['noindex']} hidden from search engines, {$summary['errors']} that didn’t render).");

        $this->table(['Check', 'Failing', 'Warnings'], collect($summary['rules'] ?? [])
            ->map(fn (array $rule) => [__($rule['label']), $rule['fail'], $rule['warn']])
            ->values()
            ->all());

        return self::SUCCESS;
    }
}
