<?php

namespace App\Services\Translation;

use App\Models\CaseStudy;
use App\Models\Page;
use App\Support\Locales;

class LinkLocalizer
{
    /** @var array<string, array<string, string>> */
    private array $maps = [];

    /**
     * Caminho pt-BR => caminho publicado no idioma.
     *
     * @return array<string, string>
     */
    public function map(string $locale): array
    {
        if (Locales::isDefault($locale)) {
            return [];
        }

        if (isset($this->maps[$locale])) {
            return $this->maps[$locale];
        }

        $map = [];
        foreach ([Page::class, CaseStudy::class] as $model) {
            $model::query()
                ->where('locale', $locale)
                ->where('status', 'published')
                ->whereNotNull('translation_of')
                ->with('original')
                ->get()
                ->each(function ($row) use (&$map) {
                    if ($row->original !== null) {
                        $map[$row->original->publicPath()] = $row->publicPath();
                    }
                });
        }

        return $this->maps[$locale] = $map;
    }

    public function href(string $href, string $locale): string
    {
        $map = $this->map($locale);
        if ($map === [] || ! str_starts_with($href, '/') || str_starts_with($href, '//')) {
            return $href;
        }

        $parts = preg_split('/(?=[?#])/', $href, 2) ?: [$href];
        $path = rtrim($parts[0], '/') ?: '/';
        $rest = $parts[1] ?? '';

        return isset($map[$path]) ? $map[$path].$rest : $href;
    }

    public function html(string $html, string $locale): string
    {
        if ($this->map($locale) === [] || ! str_contains($html, 'href=')) {
            return $html;
        }

        return (string) preg_replace_callback(
            '/href=(["\'])(\/[^"\']*)\1/',
            fn (array $match) => 'href='.$match[1].$this->href($match[2], $locale).$match[1],
            $html
        );
    }

    /**
     * @param  array<int|string, mixed>  $data
     * @return array<int|string, mixed>
     */
    public function data(array $data, string $locale): array
    {
        if ($this->map($locale) === []) {
            return $data;
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->data($value, $locale);
            } elseif (is_string($value)) {
                $isHrefKey = is_string($key) && ($key === 'href' || str_ends_with($key, '_href'));
                $data[$key] = $isHrefKey ? $this->href($value, $locale) : $this->html($value, $locale);
            }
        }

        return $data;
    }
}
