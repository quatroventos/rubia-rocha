<?php

namespace App\Services;

use App\Cms\FormDefinition;
use App\Models\Certification;
use App\Models\MenuItem;
use App\Models\SiteSetting;
use App\Support\Locales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Throwable;

class SystemOptions
{
    public const PREFIX = 'cms:options:';

    public const KEYS = ['site', 'menus:locale', 'certs', 'form:contact'];

    public const TTL_PRESETS = [
        300 => '5 minutos',
        900 => '15 minutos',
        3600 => '1 hora',
        21600 => '6 horas',
        86400 => '24 horas',
    ];

    public function site(): ?SiteSetting
    {
        $row = $this->remember('site', function () {
            $site = SiteSetting::query()->first();

            return $site?->attributesToArray();
        });

        return $this->hydrate(SiteSetting::class, is_array($row) ? $row : null);
    }

    /**
     * @return Collection<string, Collection<int, MenuItem>>
     */
    public function menus(?string $locale = null): Collection
    {
        $locales = $this->remember('menus:locale', function () {
            $query = MenuItem::query()
                ->where('is_active', true)
                ->orderBy('sort');

            $items = $query->get();

            return $items
                ->groupBy(fn (MenuItem $item) => Locales::normalize($item->locale))
                ->map(fn (Collection $localeItems) => $localeItems
                    ->groupBy('group')
                    ->map(function (Collection $groupItems) {
                        $rows = $groupItems->map(fn (MenuItem $item) => $item->attributesToArray())->values()->all();

                        return $this->treeRows($rows);
                    })
                    ->all())
                ->all();
        });

        $groups = is_array($locales) ? ($locales[Locales::normalize($locale)] ?? []) : [];

        return collect(is_array($groups) ? $groups : [])
            ->map(fn (array $items) => collect($items)
                ->map(fn (array $row) => $this->hydrateMenuNode($row))
                ->filter()
                ->values());
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function treeRows(array $rows): array
    {
        $byParent = [];
        foreach ($rows as $row) {
            $parentId = $row['parent_id'] ?? null;
            $key = $parentId === null ? 'root' : (string) $parentId;
            $byParent[$key][] = $row;
        }

        $build = function (string $key) use (&$build, $byParent): array {
            $nodes = [];
            foreach ($byParent[$key] ?? [] as $row) {
                $row['children'] = $build((string) $row['id']);
                $nodes[] = $row;
            }

            return $nodes;
        };

        return $build('root');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function hydrateMenuNode(array $row): ?MenuItem
    {
        $childRows = $row['children'] ?? [];
        unset($row['children']);

        $item = $this->hydrate(MenuItem::class, $row);
        if ($item === null) {
            return null;
        }

        $children = collect(is_array($childRows) ? $childRows : [])
            ->map(fn (array $child) => $this->hydrateMenuNode($child))
            ->filter()
            ->values();

        $item->setRelation('children', $children);

        return $item;
    }

    /**
     * @return Collection<int, MenuItem>
     */
    public function menu(string $group, ?string $locale = null): Collection
    {
        return $this->menus($locale)->get($group, collect());
    }

    /**
     * @return Collection<int, Certification>
     */
    public function certifications(): Collection
    {
        $rows = $this->remember('certs', function () {
            return Certification::query()
                ->orderBy('sort')
                ->get()
                ->map(fn (Certification $item) => $item->attributesToArray())
                ->all();
        });

        return collect(is_array($rows) ? $rows : [])
            ->map(fn (array $row) => $this->hydrate(Certification::class, $row))
            ->filter()
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function form(string $key): ?array
    {
        if (! in_array($key, FormDefinition::keys(), true)) {
            return null;
        }

        $form = $this->remember('form:'.$key, fn () => FormDefinition::withOptions($key));

        return is_array($form) ? $form : null;
    }

    public function flush(): void
    {
        foreach (self::KEYS as $name) {
            Cache::forget(self::PREFIX.$name);
        }

        Cache::forever(self::PREFIX.'flushed_at', now()->toIso8601String());
    }

    public function warm(): void
    {
        $this->flush();
        $this->site();
        $this->menus();
        $this->certifications();

        foreach (FormDefinition::keys() as $key) {
            $this->form($key);
        }
    }

    public function setTtl(int $seconds): void
    {
        if (! isset(self::TTL_PRESETS[$seconds])) {
            $seconds = (int) config('cms.options_ttl', 3600);
        }

        Cache::forever(self::PREFIX.'ttl', $seconds);
        $this->flush();
    }

    public function ttl(): int
    {
        $stored = Cache::get(self::PREFIX.'ttl');

        if (is_int($stored) && $stored > 0) {
            return $stored;
        }

        if (is_numeric($stored) && (int) $stored > 0) {
            return (int) $stored;
        }

        return max(60, (int) config('cms.options_ttl', 3600));
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $store = (string) config('cache.default');

        return [
            'store' => $store,
            'driver' => (string) config('cache.stores.'.$store.'.driver', $store),
            'redis' => $this->redisReady(),
            'ttl' => $this->ttl(),
            'ttl_presets' => self::TTL_PRESETS,
            'flushed_at' => Cache::get(self::PREFIX.'flushed_at'),
            'keys' => collect(self::KEYS)
                ->mapWithKeys(fn (string $name) => [$name => Cache::has(self::PREFIX.$name)])
                ->all(),
        ];
    }

    private function remember(string $name, callable $callback): mixed
    {
        $key = self::PREFIX.$name;
        $cached = Cache::get($key);

        if (is_array($cached) && array_key_exists('value', $cached)) {
            return $cached['value'];
        }

        $value = $callback();
        Cache::put($key, ['value' => $value], $this->ttl());

        return $value;
    }

    /**
     * @template T of Model
     *
     * @param  class-string<T>  $class
     * @param  array<string, mixed>|null  $attributes
     * @return T|null
     */
    private function hydrate(string $class, ?array $attributes): ?Model
    {
        if ($attributes === null) {
            return null;
        }

        /** @var T $model */
        $model = new $class;
        $model->forceFill($attributes);
        $model->exists = true;
        $model->syncOriginal();

        return $model;
    }

    private function redisReady(): bool
    {
        if ((string) config('cache.stores.'.config('cache.default').'.driver') !== 'redis') {
            return false;
        }

        try {
            $pong = Redis::connection((string) config('cache.stores.redis.connection', 'cache'))->ping();

            return $pong === true || $pong === 'PONG' || $pong === '+PONG';
        } catch (Throwable) {
            return false;
        }
    }
}
