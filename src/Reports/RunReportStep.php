<?php

namespace JothamLec\Seo\Reports;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * One step of a report on a queue worker; it queues the next until the
 * report is done. On the sync queue it is never dispatched: the control
 * panel advances the report one step per progress request instead.
 */
class RunReportStep implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(public int $reportId) {}

    public function handle(Runner $runner): void
    {
        $report = Report::query()->find($this->reportId);

        if ($report === null || ! $report->isRunning()) {
            return;
        }

        if ($runner->step($report)->isRunning()) {
            self::dispatch($this->reportId);
        }
    }

    public static function usesWorker(): bool
    {
        return config('queue.connections.'.config('queue.default').'.driver') !== 'sync';
    }
}
