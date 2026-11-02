<?php

namespace App\Models\Concerns;

use App\Support\Locales;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasTranslations
{
    /**
     * @return list<string>
     */
    abstract public function translatableHashFields(): array;

    public function scopeForLocale(Builder $query, ?string $locale = null): Builder
    {
        return $query->where($this->qualifyColumn('locale'), Locales::normalize($locale));
    }

    public function scopeOriginals(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('locale'), Locales::default());
    }

    public function original(): BelongsTo
    {
        return $this->belongsTo(static::class, 'translation_of');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(static::class, 'translation_of');
    }

    public function isOriginal(): bool
    {
        return $this->translation_of === null;
    }

    public function localeCode(): string
    {
        return Locales::normalize($this->locale);
    }

    public function sourceRow(): static
    {
        if ($this->isOriginal()) {
            return $this;
        }

        /** @var static|null $original */
        $original = $this->relationLoaded('original') ? $this->getRelation('original') : $this->original()->first();

        return $original ?? $this;
    }

    public function translationFor(string $locale): ?static
    {
        $source = $this->sourceRow();

        if (Locales::isDefault($locale)) {
            return $source;
        }

        /** @var static|null $row */
        $row = $source->translations()->where('locale', $locale)->first();

        return $row;
    }

    public function sourceHash(): string
    {
        $source = $this->sourceRow();
        $data = [];
        foreach ($this->translatableHashFields() as $field) {
            $data[$field] = $source->getAttribute($field);
        }

        return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    public function isTranslationStale(): bool
    {
        if ($this->isOriginal()) {
            return false;
        }

        return $this->source_hash !== null && $this->source_hash !== $this->sourceHash();
    }

    /**
     * @return array<string, string>
     */
    public function alternates(bool $onlyPublished = true): array
    {
        $source = $this->sourceRow();
        $rows = collect([$source])->concat($source->translations()->get());

        return $rows
            ->filter(fn ($row) => ! $onlyPublished || $row->status === 'published')
            ->filter(fn ($row) => Locales::isSupported($row->locale))
            ->sortBy(fn ($row) => array_search($row->locale, Locales::all(), true))
            ->mapWithKeys(fn ($row) => [$row->locale => $row->publicPath()])
            ->all();
    }
}
