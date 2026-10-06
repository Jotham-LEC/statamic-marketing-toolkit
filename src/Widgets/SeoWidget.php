<?php

namespace JothamLec\Seo\Widgets;

use Illuminate\Support\Facades\Schema;
use JothamLec\Seo\NotFound\MissingPath;
use JothamLec\Seo\Reports\Report;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Widgets\VueComponent;
use Statamic\Widgets\Widget;

/**
 * The dashboard's SEO card: the latest report's score and the most recently
 * hit missing pages, of the site selected in the control panel. Add it in
 * config/statamic/cp.php: `['type' => 'seo']`.
 */
class SeoWidget extends Widget
{
    public function component(): ?VueComponent
    {
        if (! User::current()?->can('view seo')) {
            return null;
        }

        return VueComponent::render('seo-widget', [
            'title' => $this->config('title', __('seo::cp.seo')),
            'report' => $this->latestReport(),
            'notFound' => $this->recentNotFound(),
            'url' => cp_route('seo.index'),
            'notFoundUrl' => cp_route('seo.404s.index'),
        ]);
    }

    /**
     * @return array{score: int, pages: int, created_at: string, url: string}|null
     */
    protected function latestReport(): ?array
    {
        if (! Schema::hasTable('seo_reports')) {
            return null;
        }

        $report = Report::query()->shownOn(Site::selected()->handle())->where('status', Report::DONE)->latest('id')->first();

        return $report === null ? null : [
            'score' => (int) $report->score,
            'pages' => (int) ($report->summary['scored'] ?? $report->pages_total),
            'created_at' => $report->finished_at?->toIso8601String() ?? $report->created_at->toIso8601String(),
            'url' => cp_route('seo.reports.show', $report),
        ];
    }

    /**
     * @return list<array{path: string, hits: int}>
     */
    protected function recentNotFound(): array
    {
        if (! Schema::hasTable('seo_404s')) {
            return [];
        }

        return MissingPath::query()->shownOn(Site::selected()->handle())->latest('last_seen_at')->limit((int) $this->config('limit', 5))->get()
            ->map(fn (MissingPath $row) => ['path' => $row->path, 'hits' => $row->hits])
            ->all();
    }
}
