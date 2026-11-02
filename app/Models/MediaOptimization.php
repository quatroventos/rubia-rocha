<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaOptimization extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_OPTIMIZED = 'optimized';

    public const STATUS_ERROR = 'error';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quality' => 'integer',
            'max_width' => 'integer',
            'original_bytes' => 'integer',
            'optimized_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'optimized_at' => 'datetime',
        ];
    }

    public function isOptimized(): bool
    {
        return $this->status === self::STATUS_OPTIMIZED
            && filled($this->optimized_path);
    }
}
