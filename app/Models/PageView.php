<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageView extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'load_time_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PageSession::class, 'page_session_id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
