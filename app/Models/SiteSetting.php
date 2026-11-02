<?php

namespace App\Models;

use App\Services\SystemOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class SiteSetting extends Model
{
    use LogsActivity;

    protected $guarded = [];

    protected $hidden = [
        'smtp_password',
        'evolution_api_key',
    ];

    protected $appends = [
        'smtp_password_set',
        'evolution_api_key_set',
    ];

    protected function casts(): array
    {
        return [
            'sitemap_extra' => 'array',
            'translations' => 'array',
            'form_schemas' => 'array',
            'backup_hour' => 'integer',
            'backup_retention_days' => 'integer',
            'smtp_enabled' => 'boolean',
            'smtp_port' => 'integer',
            'smtp_password' => 'encrypted',
            'whatsapp_enabled' => 'boolean',
            'evolution_api_key' => 'encrypted',
            'plan_links' => 'array',
            'client_logos' => 'array',
        ];
    }

    public function getSmtpPasswordSetAttribute(): bool
    {
        return filled($this->attributes['smtp_password'] ?? null);
    }

    public function getEvolutionApiKeySetAttribute(): bool
    {
        return filled($this->attributes['evolution_api_key'] ?? null);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['smtp_password', 'evolution_api_key'])
            ->logOnlyDirty();
    }

    public function translated(string $field, string $locale): mixed
    {
        $translations = is_array($this->translations) ? $this->translations : [];
        $value = $translations[$locale][$field] ?? null;

        return filled($value) ? $value : $this->getAttribute($field);
    }

    public static function current(): self
    {
        return app(SystemOptions::class)->site()
            ?? static::query()->firstOrFail();
    }
}
