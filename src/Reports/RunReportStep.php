<?php

namespace JothamLec\MarketingToolkit\Reports;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * One step of a report on a queue worker; it queues the next until the
 * report is done. On the sync queue it is never dispatched: the control
 * panel advances the report one step per progress request instead.
 *
 * A step that fails for good (it threw, or ran past the timeout) marks the
 * report failed rather than leaving it running with nothing to move it on.
 * A worker killed outright calls nothing: Runner::resumeIfStalled() queues
 * the step again once the report has stood still for a while.
 */
class RunReportStep implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 600;

    /** How long a step waits when another process is stepping the report. */
    private const int RETRY_SECONDS = 30;

    public function __construct(public int $reportId) {}

    public function handle(Runner $runner): void
    {
        $report = Report::query()->find($this->reportId);

        if ($report === null || ! $report->isRunning()) {
            return;
        }

        $done = $report->pages_done;
        $report = $runner->step($report);

        // Another process held the step: come back in a while rather than at once.
        if ($report->isRunning()) {
            self::dispatch($this->reportId)->delay($report->pages_done === $done ? self::RETRY_SECONDS : 0);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $report = Report::query()->find($this->reportId);

        if ($report !== null) {
            app(Runner::class)->fail($report);
        }
    }

    public static function usesWorker(): bool
    {
        return config('queue.connections.'.config('queue.default').'.driver') !== 'sync';
    }
}
