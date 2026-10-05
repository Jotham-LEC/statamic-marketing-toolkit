<?php

namespace JothamLec\Seo\Commands;

use Illuminate\Console\Command;
use JothamLec\Seo\Reports\Report as SeoReport;
use JothamLec\Seo\Reports\Runner;
use Statamic\Console\RunsInPlease;

/**
 * `php please seo:report`: check every published page now, in this process,
 * and print the scores. The scheduler runs it when reports are scheduled.
 */
class Report extends Command
{
    use RunsInPlease;

    protected $signature = 'statamic:seo:report';

    protected $description = 'Check every published page against the SEO rules and score the site';

    public function handle(Runner $runner): int
    {
        $report = $runner->start();

        if ($report->pages_done > 0 && $report->isRunning()) {
            $this->components->info("Continuing report #{$report->id}, already running.");
        }

        $bar = $this->output->createProgressBar(max(1, $report->pages_total));
        $bar->setProgress($report->pages_done);

        $report = $runner->runToEnd($report, fn (SeoReport $report) => $bar->setProgress($report->pages_done));
        $bar->finish();
        $this->newLine(2);

        if ($report->status !== SeoReport::DONE) {
            $this->components->error("Report #{$report->id} failed: {$report->error}");

            return self::FAILURE;
        }

        $summary = $report->summary ?? [];
        $this->components->info("Report #{$report->id}: score ".($report->score ?? '–')." / 100 over {$summary['scored']} pages ({$summary['noindex']} hidden from search engines, {$summary['errors']} that didn’t render).");

        $this->table(['Check', 'Failing', 'Warnings'], collect($summary['rules'] ?? [])
            ->map(fn (array $rule) => [$rule['label'], $rule['fail'], $rule['warn']])
            ->values()
            ->all());

        return self::SUCCESS;
    }
}
