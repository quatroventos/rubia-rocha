<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicStorageController extends Controller
{
    public function __invoke(string $path): BinaryFileResponse
    {
        $relative = str_replace('\\', '/', $path);

        if ($relative === '' || str_contains($relative, "\0") || preg_match('#(^|/)\.\.(/|$)#', $relative)) {
            abort(404);
        }

        $disk = Storage::disk('public');

        try {
            $root = realpath($disk->path('.'));
            $full = realpath($disk->path($relative));
        } catch (\Throwable) {
            abort(404);
        }

        if ($root === false || $full === false || ! is_file($full)) {
            abort(404);
        }

        $prefix = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (! str_starts_with($full, $prefix)) {
            abort(404);
        }

        return response()->file($full);
    }
}
