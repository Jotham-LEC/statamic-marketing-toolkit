<?php

namespace JothamLec\Seo\NotFound;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A path visitors asked for and got a 404: one row per path, with how often
 * and when, and the last page that linked to it.
 *
 * @property int $id
 * @property string $path
 * @property int $hits
 * @property ?string $referrer
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 */
class MissingPath extends Model
{
    public $timestamps = false;

    protected $table = 'seo_404s';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'hits' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
