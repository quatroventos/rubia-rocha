<?php

namespace App\Models;

use Database\Factories\RedirectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Redirect extends Model
{
    /** @use HasFactory<RedirectFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_CODES = [301, 302];

    private const CACHE_KEY = 'redirects:map';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'is_active' => 'boolean',
            'hits' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        $flush = fn () => Cache::forget(self::CACHE_KEY);

        static::saved($flush);
        static::deleted($flush);
        static::restored($flush);
    }

    /**
     * Caminho de origem no formato gravado: barra inicial, sem barra final, sem query, minúsculo e decodificado.
     */
    public static function normalizePath(string $path): string
    {
        $path = (string) (parse_url(trim($path), PHP_URL_PATH) ?? '');

        return '/'.trim(mb_strtolower(rawurldecode($path)), '/');
    }

    /**
     * @return array{id: int, to: string, status: int}|null
     */
    public static function match(string $path): ?array
    {
        $map = Cache::rememberForever(self::CACHE_KEY, fn () => self::query()
            ->where('is_active', true)
            ->get(['id', 'from_path', 'to_url', 'status_code'])
            ->mapWithKeys(fn (self $redirect) => [$redirect->from_path => [
                'id' => $redirect->id,
                'to' => $redirect->to_url,
                'status' => $redirect->status_code,
            ]])
            ->all());

        return $map[self::normalizePath($path)] ?? null;
    }
}
