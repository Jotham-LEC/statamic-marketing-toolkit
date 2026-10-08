<?php

namespace JothamLec\MarketingToolkit\Reports;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Runs one step of a report on a queue worker, then queues the next step until
 * the report is done. On the sync queue this job is never dispatched; instead,
 * the control panel advances the report by one step per progress request.
 *
 * A step that fails for good (it threw, or ran past the timeout) marks the
 * report as failed rather than leaving it running with nothing to move it on.
 * A worker that is killed outright calls nothing, so Runner::resumeIfStalled()
 * queues the step again once the report has stood still for a while.
 */
class RunReportStep implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * The timeout stays below the 90 seconds a queue waits by default before it
     * hands a job to another worker (`retry_after`), because a longer step would
     * run twice at once. A step takes no new page after Runner::STEP_SECONDS.
     */
    public int $timeout = 75;

    /**
     * A step that fails isn't tried again. Instead, failed() marks the report as
     * failed, and the user can start a new one.
     */
    public int $tries = 1;

    /** The number of seconds a step waits when another process is stepping the report. */
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

        // If another process held the step, the next one waits a while rather than starting at once.
        if ($report->isRunning()) {
            self::dispatch($this->reportId)->delay($report->pages_done === $done ? self::RETRY_SECONDS : 0);
        }
    }

    /**
     * Laravel calls this method when the step threw or ran past its timeout. It
     * marks the report as failed, unless the report finished in the meantime.
     */
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
