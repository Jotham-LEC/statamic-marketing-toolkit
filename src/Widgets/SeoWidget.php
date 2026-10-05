<?php

namespace JothamLec\Seo\Widgets;

use Statamic\Facades\User;
use Statamic\Widgets\VueComponent;
use Statamic\Widgets\Widget;

/**
 * The dashboard's SEO card: the latest report's score and the most-hit
 * missing pages. Add it in config/statamic/cp.php: `['type' => 'seo']`.
 */
class SeoWidget extends Widget
{
    public function component(): ?VueComponent
    {
        if (! User::current()?->can('view seo')) {
            return null;
        }

        return VueComponent::render('seo-widget', [
            'title' => $this->config('title', 'SEO'),
            'report' => $this->latestReport(),
            'notFound' => $this->recentNotFound(),
            'url' => cp_route('seo.index'),
        ]);
    }

    /**
     * @return array{score: int, pages: int, created_at: string, url: string}|null
     */
    protected function latestReport(): ?array
    {
        // Reports arrive in v1.4.
        return null;
    }

    /**
     * @return list<array{path: string, hits: int, last_seen: string}>
     */
    protected function recentNotFound(): array
    {
        // 404 tracking arrives in v1.3.
        return [];
    }
}
