<?php

namespace JothamLec\MarketingToolkit\Reports;

use Illuminate\Database\Eloquent\Model;

/**
 * One page of a report. It holds what the rendered HTML said (its facts) and,
 * once the report is done, each check's verdict and the page's score. A page
 * that the site hides from search engines has no score.
 *
 * @property int $id
 * @property int $report_id
 * @property string $url
 * @property string $content_type either entry or term
 * @property string $content_id
 * @property ?string $title
 * @property bool $in_sitemap
 * @property bool $checked
 * @property ?array<string, mixed> $facts
 * @property ?array<string, array{status: string, message: string, params?: array<string, mixed>}> $results each message is a translation key or plain text
 * @property ?int $score
 */
class ReportPage extends Model
{
    public $timestamps = false;

    protected $table = 'mt_report_pages';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'in_sitemap' => 'boolean',
            'checked' => 'boolean',
            'facts' => 'array',
            'results' => 'array',
            'score' => 'integer',
        ];
    }

    public function facts(): PageFacts
    {
        return PageFacts::fromArray($this->facts ?? []);
    }
}
