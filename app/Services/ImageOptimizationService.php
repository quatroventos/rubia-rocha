<?php

namespace App\Services;

use App\Models\MediaOptimization;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ImageOptimizationService
{
    public const SETTINGS_KEY = 'media_opt:settings';

    public const MAP_KEY = 'media_opt:map';

    public const OUTPUT_DIR = 'optimized';

    /**
     * @var list<string>
     */
    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /**
     * @var array<string, string>|null
     */
    private ?array $map = null;

    /**
     * @return array{quality: int, max_width: int|null}
     */
    public function settings(): array
    {
        $stored = Cache::get(self::SETTINGS_KEY);

        if (! is_array($stored)) {
            return $this->defaultSettings();
        }

        return [
            'quality' => $this->clampQuality($stored['quality'] ?? 90),
            'max_width' => array_key_exists('max_width', $stored)
                ? $this->normalizeMaxWidth($stored['max_width'])
                : 2560,
        ];
    }

    /**
     * @param  array{quality?: mixed, max_width?: mixed}  $data
     * @return array{quality: int, max_width: int|null}
     */
    public function updateSettings(array $data): array
    {
        $settings = [
            'quality' => $this->clampQuality($data['quality'] ?? 90),
            'max_width' => $this->normalizeMaxWidth($data['max_width'] ?? null),
        ];

        Cache::forever(self::SETTINGS_KEY, $settings);

        return $settings;
    }

    /**
     * @return array{
     *     settings: array{quality: int, max_width: int|null},
     *     capabilities: array{webp: bool},
     *     summary: array<string, int>,
     *     items: list<array<string, mixed>>,
     *     error_log: list<array{path: string, name: string, error: string, at: string|null}>
     * }
     */
    public function panel(): array
    {
        $files = $this->discover();
        $rows = MediaOptimization::query()
            ->whereIn('source_path', array_keys($files))
            ->get()
            ->keyBy('source_path');

        $items = [];
        $errorLog = [];
        $originalBytes = 0;
        $optimizedBytes = 0;
        $savedBytes = 0;
        $optimizedCount = 0;
        $errorCount = 0;

        foreach ($files as $sourcePath => $absolute) {
            /** @var MediaOptimization|null $row */
            $row = $rows->get($sourcePath);
            $bytes = is_file($absolute) ? (int) filesize($absolute) : 0;
            $originalBytes += $bytes;

            $status = $row?->status ?? MediaOptimization::STATUS_PENDING;
            $optBytes = $row?->optimized_bytes;
            $optPath = $row?->optimized_path;
            $optAbsolute = $optPath ? $this->absoluteFromRelative($optPath) : null;

            if ($status === MediaOptimization::STATUS_OPTIMIZED && (! $optAbsolute || ! is_file($optAbsolute))) {
                $status = MediaOptimization::STATUS_PENDING;
                $optBytes = null;
                $optPath = null;
            }

            if ($status === MediaOptimization::STATUS_OPTIMIZED) {
                $optimizedCount++;
                $optimizedBytes += (int) ($optBytes ?? 0);
                $savedBytes += max(0, $bytes - (int) ($optBytes ?? 0));
            }

            if ($status === MediaOptimization::STATUS_ERROR) {
                $errorCount++;
                $errorLog[] = [
                    'path' => $sourcePath,
                    'name' => basename($sourcePath),
                    'error' => (string) ($row?->error ?: 'Erro desconhecido.'),
                    'at' => $row?->updated_at?->toIso8601String(),
                ];
            }

            $items[] = [
                'path' => $sourcePath,
                'name' => basename($sourcePath),
                'url' => $this->publicUrl($sourcePath),
                'optimized_url' => $optPath ? $this->publicUrl($optPath) : null,
                'status' => $status,
                'original_bytes' => $row?->original_bytes ?: $bytes,
                'optimized_bytes' => $optBytes,
                'quality' => $row?->quality,
                'max_width' => $row?->max_width,
                'width' => $row?->width,
                'height' => $row?->height,
                'error' => $row?->error,
                'optimized_at' => $row?->optimized_at?->toIso8601String(),
            ];
        }

        usort($items, static fn (array $a, array $b): int => $b['original_bytes'] <=> $a['original_bytes']);

        $orphanErrors = MediaOptimization::query()
            ->where('status', MediaOptimization::STATUS_ERROR)
            ->whereNotIn('source_path', array_keys($files))
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        foreach ($orphanErrors as $row) {
            $errorLog[] = [
                'path' => $row->source_path,
                'name' => basename($row->source_path),
                'error' => (string) ($row->error ?: 'Erro desconhecido.'),
                'at' => $row->updated_at?->toIso8601String(),
            ];
        }

        usort($errorLog, static fn (array $a, array $b): int => strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? '')));

        $pendingCount = count($items) - $optimizedCount - $errorCount;

        return [
            'settings' => $this->settings(),
            'capabilities' => [
                'webp' => $this->supportsWebp(),
            ],
            'summary' => [
                'total' => count($items),
                'optimized' => $optimizedCount,
                'pending' => max(0, $pendingCount),
                'errors' => $errorCount + $orphanErrors->count(),
                'original_bytes' => $originalBytes,
                'optimized_bytes' => $optimizedBytes,
                'saved_bytes' => $savedBytes,
            ],
            'items' => $items,
            'error_log' => $errorLog,
        ];
    }

    /**
     * @param  list<string>  $paths
     * @return array{
     *     settings: array{quality: int, max_width: int|null},
     *     summary: array<string, int>,
     *     items: list<array<string, mixed>>,
     *     processed: int,
     *     failed: int
     * }
     */
    public function optimize(array $paths = [], ?int $quality = null, ?int $maxWidth = null): array
    {
        $settings = $this->settings();
        $quality = $this->clampQuality($quality ?? $settings['quality']);
        $maxWidth = $maxWidth !== null
            ? $this->normalizeMaxWidth($maxWidth)
            : $settings['max_width'];

        $targets = $this->resolveTargets($paths);
        $processed = 0;
        $failed = 0;
        $failures = [];

        if ($targets !== [] && ! $this->supportsWebp()) {
            $message = 'O PHP deste ambiente não tem suporte a WebP no GD. Reconstrua a imagem Docker com libwebp (docker compose build --no-cache app).';

            foreach ($targets as $sourcePath => $absolute) {
                $failed++;
                $failures[] = ['path' => $sourcePath, 'error' => $message];

                MediaOptimization::query()->updateOrCreate(
                    ['source_path' => $sourcePath],
                    [
                        'status' => MediaOptimization::STATUS_ERROR,
                        'quality' => $quality,
                        'max_width' => $maxWidth,
                        'original_bytes' => is_file($absolute) ? (int) filesize($absolute) : 0,
                        'optimized_path' => null,
                        'optimized_bytes' => null,
                        'width' => null,
                        'height' => null,
                        'error' => $message,
                        'optimized_at' => null,
                    ]
                );
            }

            Log::error('Otimização de imagens bloqueada: GD sem WebP.', [
                'targets' => count($targets),
            ]);

            $this->forgetMap();

            return [
                ...$this->panel(),
                'processed' => 0,
                'failed' => $failed,
                'failures' => $failures,
            ];
        }

        foreach ($targets as $sourcePath => $absolute) {
            try {
                $this->optimizeOne($sourcePath, $absolute, $quality, $maxWidth);
                $processed++;
            } catch (Throwable $e) {
                $failed++;
                $message = $this->friendlyError($e);
                $failures[] = ['path' => $sourcePath, 'error' => $message];
                report($e);
                Log::warning('Falha ao otimizar imagem.', [
                    'path' => $sourcePath,
                    'error' => $message,
                ]);

                MediaOptimization::query()->updateOrCreate(
                    ['source_path' => $sourcePath],
                    [
                        'status' => MediaOptimization::STATUS_ERROR,
                        'quality' => $quality,
                        'max_width' => $maxWidth,
                        'original_bytes' => is_file($absolute) ? (int) filesize($absolute) : 0,
                        'optimized_path' => null,
                        'optimized_bytes' => null,
                        'width' => null,
                        'height' => null,
                        'error' => $message,
                        'optimized_at' => null,
                    ]
                );
            }
        }

        $this->forgetMap();

        return [
            ...$this->panel(),
            'processed' => $processed,
            'failed' => $failed,
            'failures' => $failures,
        ];
    }

    /**
     * @param  list<string>  $paths
     * @return array{
     *     settings: array{quality: int, max_width: int|null},
     *     summary: array<string, int>,
     *     items: list<array<string, mixed>>,
     *     reverted: int
     * }
     */
    public function revert(array $paths = []): array
    {
        $query = MediaOptimization::query()
            ->where('status', MediaOptimization::STATUS_OPTIMIZED);

        if ($paths !== []) {
            $normalized = array_values(array_filter(array_map(
                fn (string $path): ?string => $this->normalizeSourcePath($path),
                $paths
            )));
            $query->whereIn('source_path', $normalized);
        }

        $reverted = 0;

        foreach ($query->get() as $row) {
            if ($row->optimized_path) {
                $absolute = $this->absoluteFromRelative($row->optimized_path);
                if ($absolute && is_file($absolute)) {
                    @unlink($absolute);
                }
            }

            $row->delete();
            $reverted++;
        }

        $this->forgetMap();

        return [
            ...$this->panel(),
            'reverted' => $reverted,
        ];
    }

    public function publicUrlFor(?string $path): ?string
    {
        $source = $this->normalizeSourcePath($path);

        if ($source === null) {
            return null;
        }

        $optimized = $this->map()[$source] ?? null;

        if (! is_string($optimized) || $optimized === '') {
            return null;
        }

        $absolute = $this->absoluteFromRelative($optimized);

        if (! $absolute || ! is_file($absolute)) {
            return null;
        }

        return $this->publicUrl($optimized);
    }

    /**
     * @return array<string, string>
     */
    private function map(): array
    {
        if ($this->map !== null) {
            return $this->map;
        }

        $cached = Cache::get(self::MAP_KEY);

        if (is_array($cached)) {
            /** @var array<string, string> $cached */
            return $this->map = $cached;
        }

        $map = MediaOptimization::query()
            ->where('status', MediaOptimization::STATUS_OPTIMIZED)
            ->whereNotNull('optimized_path')
            ->pluck('optimized_path', 'source_path')
            ->all();

        Cache::forever(self::MAP_KEY, $map);

        return $this->map = $map;
    }

    private function forgetMap(): void
    {
        $this->map = null;
        Cache::forget(self::MAP_KEY);
    }

    /**
     * @return array<string, string> relative => absolute
     */
    private function discover(): array
    {
        $roots = [
            'assets' => storage_path('app/public/assets'),
            'media' => storage_path('app/public/media'),
        ];

        $found = [];

        foreach ($roots as $prefix => $root) {
            if (! is_dir($root)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $ext = strtolower($file->getExtension());
                if (! in_array($ext, self::EXTENSIONS, true)) {
                    continue;
                }

                $absolute = $file->getPathname();
                $relative = $prefix.'/'.ltrim(str_replace('\\', '/', substr($absolute, strlen($root))), '/');

                if (str_starts_with($relative, self::OUTPUT_DIR.'/')) {
                    continue;
                }

                $found[$relative] = $absolute;
            }
        }

        return $found;
    }

    /**
     * @param  list<string>  $paths
     * @return array<string, string>
     */
    private function resolveTargets(array $paths): array
    {
        $all = $this->discover();

        if ($paths === []) {
            return $all;
        }

        $targets = [];

        foreach ($paths as $path) {
            $normalized = $this->normalizeSourcePath($path);
            if ($normalized !== null && isset($all[$normalized])) {
                $targets[$normalized] = $all[$normalized];
            }
        }

        return $targets;
    }

    private function optimizeOne(string $sourcePath, string $absolute, int $quality, ?int $maxWidth): void
    {
        if (! $this->supportsWebp()) {
            throw new RuntimeException('Suporte a WebP indisponível neste PHP (extensão GD).');
        }

        if (! is_file($absolute)) {
            throw new RuntimeException('Arquivo não encontrado.');
        }

        $image = $this->loadImage($absolute);
        $width = imagesx($image);
        $height = imagesy($image);

        if ($maxWidth !== null && $width > $maxWidth) {
            $ratio = $maxWidth / $width;
            $newWidth = $maxWidth;
            $newHeight = max(1, (int) round($height * $ratio));
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
            $width = $newWidth;
            $height = $newHeight;
        }

        $optimizedRelative = $this->optimizedRelativePath($sourcePath);
        $optimizedAbsolute = $this->absoluteFromRelative($optimizedRelative);

        if ($optimizedAbsolute === null) {
            imagedestroy($image);
            throw new RuntimeException('Caminho de saída inválido.');
        }

        File::ensureDirectoryExists(dirname($optimizedAbsolute));

        if (! imagewebp($image, $optimizedAbsolute, $quality)) {
            imagedestroy($image);
            throw new RuntimeException('Falha ao gravar WebP.');
        }

        imagedestroy($image);

        clearstatcache(true, $optimizedAbsolute);

        MediaOptimization::query()->updateOrCreate(
            ['source_path' => $sourcePath],
            [
                'optimized_path' => $optimizedRelative,
                'status' => MediaOptimization::STATUS_OPTIMIZED,
                'quality' => $quality,
                'max_width' => $maxWidth,
                'original_bytes' => (int) filesize($absolute),
                'optimized_bytes' => (int) filesize($optimizedAbsolute),
                'width' => $width,
                'height' => $height,
                'error' => null,
                'optimized_at' => now(),
            ]
        );
    }

    /**
     * @return \GdImage
     */
    private function loadImage(string $absolute): \GdImage
    {
        $info = @getimagesize($absolute);

        if ($info === false) {
            throw new RuntimeException('Formato de imagem inválido.');
        }

        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($absolute),
            IMAGETYPE_PNG => @imagecreatefrompng($absolute),
            IMAGETYPE_WEBP => @imagecreatefromwebp($absolute),
            IMAGETYPE_GIF => @imagecreatefromgif($absolute),
            default => false,
        };

        if ($image === false) {
            throw new RuntimeException('Não foi possível ler a imagem.');
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        return $image;
    }

    private function optimizedRelativePath(string $sourcePath): string
    {
        $dir = dirname($sourcePath);
        $base = pathinfo($sourcePath, PATHINFO_FILENAME);
        $prefix = $dir === '.' ? '' : $dir.'/';

        return self::OUTPUT_DIR.'/'.$prefix.$base.'.webp';
    }

    private function absoluteFromRelative(string $relative): ?string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');

        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        return storage_path('app/public/'.$relative);
    }

    private function publicUrl(string $relative): string
    {
        return rtrim((string) config('app.url'), '/').'/storage/'.ltrim($relative, '/');
    }

    private function normalizeSourcePath(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $path = parse_url($path, PHP_URL_PATH) ?: $path;
        }

        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#^/+#', '', $path) ?? $path;
        $path = preg_replace('#^storage/#', '', $path) ?? $path;

        if (str_starts_with($path, self::OUTPUT_DIR.'/')) {
            return null;
        }

        if (! preg_match('#^(assets|media)/#', $path)) {
            return null;
        }

        if (str_contains($path, '..')) {
            return null;
        }

        return $path;
    }

    /**
     * @return array{quality: int, max_width: int|null}
     */
    private function defaultSettings(): array
    {
        return [
            'quality' => 90,
            'max_width' => 2560,
        ];
    }

    private function clampQuality(mixed $value): int
    {
        $quality = (int) $value;

        return max(60, min(100, $quality));
    }

    private function normalizeMaxWidth(mixed $value): ?int
    {
        if ($value === null || $value === '' || (int) $value <= 0) {
            return null;
        }

        return max(640, min(5000, (int) $value));
    }

    public function supportsWebp(): bool
    {
        return function_exists('imagewebp') && function_exists('imagecreatefromstring');
    }

    private function friendlyError(Throwable $e): string
    {
        $message = trim($e->getMessage());

        if ($message === '' || str_contains(strtolower($message), 'imagewebp')) {
            return 'Falha ao gravar WebP. Verifique se o PHP foi compilado com suporte a WebP.';
        }

        return $message;
    }
}
