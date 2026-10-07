<?php

namespace JothamLec\MarketingToolkit\Widgets;

use Illuminate\Support\Facades\Schema;
use JothamLec\MarketingToolkit\NotFound\MissingPath;
use JothamLec\MarketingToolkit\Reports\Report;
use JothamLec\MarketingToolkit\Support\Permissions;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Widgets\VueComponent;
use Statamic\Widgets\Widget;

/**
 * The dashboard's SEO card: the latest report's score and the most recently
 * hit missing pages, of the site selected in the control panel. Add it in
 * config/statamic/cp.php: `['type' => 'mt']`.
 */
final class SeoWidget extends Widget
{
    protected static $handle = 'mt';

    public function component(): ?VueComponent
    {
        if (! User::current()?->can(Permissions::VIEW)) {
            return null;
        }

        return VueComponent::render('mt-widget', [
            'title' => $this->config('title', __('marketing-toolkit::cp.seo')),
            'report' => $this->latestReport(),
            'notFound' => $this->recentNotFound(),
            'url' => cp_route('mt.index'),
            'notFoundUrl' => cp_route('mt.404s.index'),
        ]);
    }

    /**
     * @return array{score: int, pages: int, created_at: string, url: string}|null
     */
    protected function latestReport(): ?array
    {
        if (! Schema::hasTable('mt_reports')) {
            return null;
        }

        $report = Report::latestDone(Site::selected()->handle());

        return $report === null ? null : [
            'score' => (int) $report->score,
            'pages' => $report->scoredPages(),
            'created_at' => $report->finished_at?->toIso8601String() ?? $report->created_at->toIso8601String(),
            'url' => cp_route('mt.reports.show', $report),
        ];
    }

    /**
     * @return list<array{path: string, hits: int}>
     */
    protected function recentNotFound(): array
    {
        if (! Schema::hasTable('mt_404s')) {
            return [];
        }

        return MissingPath::recent(Site::selected()->handle(), (int) $this->config('limit', 5));
    }
}
