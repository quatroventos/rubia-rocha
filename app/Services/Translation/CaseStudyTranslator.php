<?php

namespace App\Services\Translation;

use App\Models\CaseStudy;
use App\Support\Locales;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CaseStudyTranslator
{
    /**
     * Campos com texto para leitura. O resto (cliente, ano, mídia, serviços, stack, url) é copiado.
     *
     * @var list<string>
     */
    private const FIELDS = ['title', 'summary', 'tags', 'cover_alt', 'content'];

    /**
     * @var list<string>
     */
    private const COPIED = ['client', 'sector', 'services', 'year', 'cover_path', 'cover_ratio', 'stack', 'url', 'featured', 'sort'];

    public function __construct(private TextTranslator $translator) {}

    public function translate(CaseStudy $case, string $locale): CaseStudy
    {
        $source = $case->sourceRow();

        if (Locales::isDefault($locale) || ! Locales::isSupported($locale)) {
            throw new InvalidArgumentException('Idioma inválido para tradução: '.$locale);
        }

        $data = [];
        foreach (self::FIELDS as $field) {
            $data[$field] = $source->getAttribute($field);
        }

        $translated = $this->translator->translate(
            ContentStrings::extract($data),
            $locale,
            'Portfolio case study for client '.$source->client.'.'
        );
        $result = ContentStrings::apply($data, $translated);

        $seo = $this->translator->seo([
            'slug' => $source->slug,
            'title' => (string) ($source->seo_title ?: $source->title),
            'description' => (string) ($source->seo_description ?: $source->summary),
            'summary' => mb_substr(strip_tags((string) ($result['summary'] ?? '').' '.implode(' ', ContentStrings::extract(is_array($result['content'] ?? null) ? $result['content'] : []))), 0, 1500),
        ], $locale);

        return DB::transaction(function () use ($source, $locale, $result, $seo) {
            /** @var CaseStudy|null $row */
            $row = CaseStudy::query()->withTrashed()->where('translation_of', $source->id)->where('locale', $locale)->lockForUpdate()->first();

            $row ??= new CaseStudy([
                'translation_of' => $source->id,
                'locale' => $locale,
                'status' => 'draft',
            ]);

            $copied = [];
            foreach (self::COPIED as $field) {
                $copied[$field] = $source->getAttribute($field);
            }

            $row->forceFill([
                ...$copied,
                ...$result,
                'slug' => $this->uniqueSlug($source->slug, $locale, $row->id),
                'seo_title' => $seo['seo_title'] !== '' ? $seo['seo_title'] : null,
                'seo_description' => $seo['seo_description'] !== '' ? $seo['seo_description'] : null,
                'source_hash' => $source->sourceHash(),
                'translated_at' => now(),
                'deleted_at' => null,
            ])->save();

            return $row->fresh() ?? $row;
        });
    }

    private function uniqueSlug(string $slug, string $locale, ?int $ignoreId): string
    {
        $base = Str::slug($slug) ?: 'case';
        $candidate = $base;
        $suffix = 2;

        while (CaseStudy::query()->withTrashed()
            ->where('locale', $locale)
            ->where('slug', $candidate)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }
}
