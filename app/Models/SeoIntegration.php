<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Credenciais e prompts das integrações de SEO (OpenSEO, OpenAI), editáveis no CMS.
 * Linha única; campo vazio cai no valor do .env.
 */
class SeoIntegration extends Model
{
    use LogsActivity;

    public const SECRETS = ['openseo_api_key', 'openai_api_key'];

    protected $guarded = [];

    protected $hidden = self::SECRETS;

    protected function casts(): array
    {
        return [
            'openseo_api_key' => 'encrypted',
            'openai_api_key' => 'encrypted',
            'openseo_location_code' => 'integer',
            'openseo_paid_enabled' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept([...self::SECRETS, 'prompt_writer', 'prompt_seo_title', 'prompt_seo_description', 'prompt_keyword'])
            ->logOnlyDirty();
    }

    public static function current(): self
    {
        return static::query()->first() ?? new static;
    }
}
