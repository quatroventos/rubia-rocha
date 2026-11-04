<?php

namespace App\Support;

/**
 * Dimensões e srcset de imagens locais. Variantes ficam ao lado do arquivo com a largura no fim do nome
 * (foto.webp, foto-960.webp; retrato-720.webp, retrato-1080.webp) e entram no srcset pela largura real.
 */
class ResponsiveImage
{
    public const THUMB_WIDTH = 960;

    /** @var array<string, array{width: int, height: int, srcset: string|null}|null> */
    private static array $cache = [];

    /**
     * @return array{width: int, height: int, srcset: string|null}|null
     */
    public static function meta(string $src): ?array
    {
        if (array_key_exists($src, self::$cache)) {
            return self::$cache[$src];
        }

        return self::$cache[$src] = self::resolve($src);
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    /**
     * Gera a variante de miniatura (nome-960.webp) de um upload maior que THUMB_WIDTH.
     */
    public static function makeThumbnail(string $absolute): void
    {
        if (! function_exists('imagewebp') || preg_match('/\.(jpe?g|png|webp)$/i', $absolute) !== 1) {
            return;
        }

        $size = @getimagesize($absolute);
        if (! $size || $size[0] <= self::THUMB_WIDTH) {
            return;
        }

        $source = match ($size[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($absolute),
            IMAGETYPE_PNG => @imagecreatefrompng($absolute),
            IMAGETYPE_WEBP => @imagecreatefromwebp($absolute),
            default => false,
        };

        if ($source === false) {
            return;
        }

        $height = (int) round($size[1] * self::THUMB_WIDTH / $size[0]);
        $thumb = imagecreatetruecolor(self::THUMB_WIDTH, $height);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, self::THUMB_WIDTH, $height, $size[0], $size[1]);
        imagewebp($thumb, preg_replace('/\.[a-z]+$/i', '-'.self::THUMB_WIDTH.'.webp', $absolute), 82);
    }

    /**
     * @return array{width: int, height: int, srcset: string|null}|null
     */
    private static function resolve(string $src): ?array
    {
        $absolute = self::absolute($src);
        if ($absolute === null) {
            return null;
        }

        $size = @getimagesize($absolute);
        if (! $size || $size[1] <= 0) {
            return null;
        }

        return [
            'width' => (int) $size[0],
            'height' => (int) $size[1],
            'srcset' => self::srcset($src, $absolute, (int) $size[0]),
        ];
    }

    private static function srcset(string $src, string $absolute, int $width): ?string
    {
        $directory = dirname($absolute);
        $file = pathinfo($absolute, PATHINFO_FILENAME);
        $base = (string) preg_replace('/-\d{3,4}$/', '', $file);
        $urlDirectory = substr($src, 0, (int) strrpos($src, '/'));

        $candidates = [basename($absolute) => $width];

        foreach (glob($directory.'/'.$base.'-*.webp') ?: [] as $variant) {
            $name = basename($variant);
            if (preg_match('/^'.preg_quote($base, '/').'-(\d{3,4})\.webp$/', $name) !== 1 || isset($candidates[$name])) {
                continue;
            }

            $variantSize = @getimagesize($variant);
            if ($variantSize) {
                $candidates[$name] = (int) $variantSize[0];
            }
        }

        if (is_file($directory.'/'.$base.'.webp') && ! isset($candidates[$base.'.webp'])) {
            $originalSize = @getimagesize($directory.'/'.$base.'.webp');
            if ($originalSize) {
                $candidates[$base.'.webp'] = (int) $originalSize[0];
            }
        }

        if (count($candidates) < 2) {
            return null;
        }

        asort($candidates);
        $byWidth = [];
        foreach ($candidates as $name => $candidateWidth) {
            $byWidth[$candidateWidth] ??= $urlDirectory.'/'.rawurlencode($name).' '.$candidateWidth.'w';
        }

        return implode(', ', $byWidth);
    }

    private static function absolute(string $src): ?string
    {
        $path = rawurldecode((string) parse_url($src, PHP_URL_PATH));

        if ($path === '' || ! str_starts_with($path, '/') || str_contains($path, '..')) {
            return null;
        }

        $absolute = str_starts_with($path, '/storage/')
            ? storage_path('app/public/'.substr($path, strlen('/storage/')))
            : public_path(ltrim($path, '/'));

        return is_file($absolute) ? $absolute : null;
    }
}
