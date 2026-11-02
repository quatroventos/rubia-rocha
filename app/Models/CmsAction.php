<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsAction extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function locationLabel(): string
    {
        $parts = array_values(array_filter([
            $this->city,
            $this->region,
            $this->country,
        ]));

        return $parts === [] ? '—' : implode(', ', $parts);
    }
}
