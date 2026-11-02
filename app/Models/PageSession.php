<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PageSession extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'started_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'duration_seconds' => 'integer',
            'page_count' => 'integer',
        ];
    }

    public function views(): HasMany
    {
        return $this->hasMany(PageView::class);
    }
}
