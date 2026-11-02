<?php

namespace App\Support;

class MediaPath
{
    public static function toPublic(mixed $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        if (is_array($state)) {
            $state = collect($state)->first();
        }

        $state = (string) $state;

        if (str_starts_with($state, 'http://') || str_starts_with($state, 'https://')) {
            return $state;
        }

        $state = ltrim($state, '/');
        $state = preg_replace('#^storage/#', '', $state) ?? $state;

        return '/storage/'.$state;
    }
}
