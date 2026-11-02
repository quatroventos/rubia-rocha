<?php

namespace App\Services\Seo;

use App\Models\SeoIntegration;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

/**
 * Aplica em runtime as credenciais e prompts salvos no CMS por cima do .env.
 * Campo vazio no banco volta ao valor original do .env, guardado na primeira aplicação.
 */
class SeoIntegrations
{
    /**
     * Coluna da tabela => chave de configuração.
     */
    public const MAP = [
        'openseo_url' => 'services.openseo.url',
        'openseo_api_key' => 'services.openseo.key',
        'openseo_host_header' => 'services.openseo.host_header',
        'openseo_project_id' => 'services.openseo.project',
        'openseo_site_url' => 'services.openseo.site_url',
        'openseo_location_code' => 'services.openseo.location_code',
        'openseo_language_code' => 'services.openseo.language_code',
        'openseo_paid_enabled' => 'services.openseo.paid',
        'openai_api_key' => 'services.openai.key',
        'openai_model' => 'services.openai.model',
        'prompt_writer' => 'services.openai.prompts.writer',
        'prompt_seo_title' => 'services.openai.prompts.seo_title',
        'prompt_seo_description' => 'services.openai.prompts.seo_description',
        'prompt_keyword' => 'services.openai.prompts.keyword',
    ];

    /**
     * @var array<string, mixed>|null
     */
    private ?array $defaults = null;

    public function apply(?SeoIntegration $settings = null): void
    {
        $defaults = $this->defaults();

        if ($settings === null) {
            try {
                if (! Schema::hasTable('seo_integrations')) {
                    return;
                }
            } catch (\Throwable) {
                return;
            }

            $settings = SeoIntegration::query()->first();
        }

        foreach (self::MAP as $column => $key) {
            $value = $settings?->getAttribute($column);

            if (is_string($value)) {
                $value = trim($value);
            }

            Config::set($key, $value === null || $value === '' ? $defaults[$column] : $this->normalize($column, $value));
        }
    }

    /**
     * Valor que vale quando o campo do CMS fica vazio (o do .env).
     */
    public function fallback(string $column): mixed
    {
        return $this->defaults()[$column] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return $this->defaults ??= collect(self::MAP)->map(fn (string $key) => config($key))->all();
    }

    public function paidEnabled(): bool
    {
        return (bool) config('services.openseo.paid');
    }

    private function normalize(string $column, mixed $value): mixed
    {
        return match ($column) {
            'openseo_site_url', 'openseo_url' => rtrim((string) $value, '/'),
            default => $value,
        };
    }
}
