<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Support\Locales;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class CaseStudy extends Model
{
    use HasTranslations;
    use LogsActivity;
    use SoftDeletes;

    public const SECTORS = [
        'financas' => 'Finanças',
        'educacao' => 'Educação',
        'varejo' => 'Varejo',
        'saude' => 'Saúde',
        'servicos' => 'Serviços',
        'industria' => 'Indústria',
    ];

    public const COVER_RATIOS = ['og', '16x9', '16x10'];

    protected $guarded = [];

    protected $attributes = [
        'locale' => 'pt-BR',
    ];

    /**
     * @return list<string>
     */
    public function translatableHashFields(): array
    {
        return ['slug', 'title', 'summary', 'tags', 'cover_alt', 'content', 'seo_title', 'seo_description'];
    }

    protected function casts(): array
    {
        return [
            'translated_at' => 'datetime',
            'services' => 'array',
            'tags' => 'array',
            'gallery' => 'array',
            'deliverables' => 'array',
            'stack' => 'array',
            'results' => 'array',
            'testimonial' => 'array',
            'content' => 'array',
            'featured' => 'boolean',
            'year' => 'integer',
            'sort' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('featured')->orderBy('sort')->orderByDesc('year')->orderByDesc('id');
    }

    public function hasService(string $slug): bool
    {
        return in_array($slug, $this->services ?? [], true);
    }

    public function publicPath(): string
    {
        return Locales::path($this->localeCode(), '/cases/'.$this->slug);
    }

    public function sectorLabel(): ?string
    {
        return self::SECTORS[$this->sector] ?? null;
    }

    /**
     * @return list<array{id: string, type: string, data: array<string, mixed>}>
     */
    public function sections(): array
    {
        $sections = is_array($this->content) ? ($this->content['sections'] ?? []) : [];

        return is_array($sections) ? array_values(array_filter($sections, 'is_array')) : [];
    }

    /**
     * Prints dos cards de listagem: os da primeira galeria do conteúdo.
     *
     * @return list<array{path: string, caption: string, alt: string}>
     */
    public function prints(): array
    {
        foreach ($this->sections() as $section) {
            if (($section['type'] ?? null) !== 'site.gallery') {
                continue;
            }

            $items = is_array($section['data']['items'] ?? null) ? $section['data']['items'] : [];

            return array_values(array_map(fn (array $item) => [
                'path' => (string) $item['image'],
                'caption' => (string) ($item['caption'] ?? ''),
                'alt' => (string) ($item['alt'] ?? ''),
            ], array_filter($items, fn ($item) => is_array($item) && filled($item['image'] ?? null))));
        }

        return [];
    }
}
