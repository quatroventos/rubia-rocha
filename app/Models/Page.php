<?php

namespace App\Models;

use App\Cms\PageContentMigrator;
use App\Models\Concerns\HasTranslations;
use App\Support\Locales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Page extends Model
{
    use HasTranslations;
    use LogsActivity;

    /**
     * Slug com até dois níveis (ex.: ensaios/gestante), sem barra inicial ou final.
     */
    public const SLUG_PATTERN = '[a-z0-9]+(?:-[a-z0-9]+)*(?:/[a-z0-9]+(?:-[a-z0-9]+)*)?';

    protected $guarded = [];

    protected $attributes = [
        'locale' => 'pt-BR',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'published_at' => 'datetime',
            'translated_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public function translatableHashFields(): array
    {
        return ['slug', 'seo_title', 'seo_description', 'og_image_alt', 'content'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    public function pageViews(): HasMany
    {
        return $this->hasMany(PageView::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class)->latest('id');
    }

    /**
     * @return array<string, mixed>
     */
    public function revisionSnapshot(): array
    {
        return [
            'status' => $this->status,
            'slug' => $this->slug,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'seo_keyword' => $this->seo_keyword,
            'canonical' => $this->canonical,
            'og_image' => $this->og_image,
            'og_image_alt' => $this->og_image_alt,
            'robots' => $this->robots,
            'twitter_card' => $this->twitter_card,
            'published_at' => $this->published_at?->toIso8601String(),
            'content' => $this->content ?? [],
        ];
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeOrderedByMenu($query)
    {
        $order = MenuItem::query()
            ->where('group', 'header')
            ->where('locale', Locales::default())
            ->whereNull('parent_id')
            ->orderBy('sort')
            ->pluck('href')
            ->map(function (string $href) {
                $path = trim((string) (parse_url($href, PHP_URL_PATH) ?: $href), '/');

                return $path === '' ? 'home' : $path;
            })
            ->filter()
            ->values();

        if ($order->isEmpty()) {
            return $query->orderBy('slug');
        }

        $cases = $order
            ->map(fn (string $slug, int $index) => 'when ? then '.$index)
            ->implode(' ');

        return $query
            ->orderByRaw('case slug '.$cases.' else 999 end', $order->all())
            ->orderBy('slug');
    }

    public function publicPath(): string
    {
        return Locales::path($this->localeCode(), $this->isHome() ? '/' : '/'.$this->slug);
    }

    public function isHome(): bool
    {
        return $this->template === 'home' || $this->slug === 'home';
    }

    /**
     * @return list<array{id: string, type: string, data: array<string, mixed>}>
     */
    public function sections(): array
    {
        return PageContentMigrator::normalize($this->content, $this->template)['sections'];
    }

    /**
     * @return list<string>
     */
    public static function reservedSlugs(): array
    {
        $admin = trim((string) config('cms.admin_path'), '/');

        return array_values(array_filter([
            ...array_map(fn (string $locale) => Locales::prefix($locale), Locales::translated()),
            't',
            'home',
            'admin',
            'api',
            'login',
            'up',
            'filament',
            'livewire',
            'sitemap.xml',
            'robots.txt',
            'storage',
            '__og',
            $admin,
        ]));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        if ($field === null && is_numeric($value)) {
            return static::query()->whereKey((int) $value)->first();
        }

        return static::query()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->where('locale', Locales::default())
            ->first();
    }
}
