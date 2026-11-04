<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\MediaPath;
use App\Support\ResponsiveImage;
use App\Support\SvgSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MediaController extends Controller
{
    public function store(Request $request, SvgSanitizer $sanitizer): JsonResponse
    {
        $kind = $request->string('kind')->toString();

        $rules = match ($kind) {
            'video' => ['file' => ['required', 'file', 'mimetypes:video/mp4,video/webm', 'max:51200']],
            'pdf' => ['file' => ['required', 'file', 'mimetypes:application/pdf', 'max:10240']],
            default => ['file' => ['required', 'file', 'image:allow_svg', 'max:8192']],
        };

        $request->validate($rules);

        $file = $request->file('file');

        $path = $this->isSvg($file)
            ? $this->storeSvg($file, $sanitizer)
            : $file->store('media', 'public');

        if ($kind === 'image' || $kind === '') {
            ResponsiveImage::makeThumbnail(Storage::disk('public')->path($path));
        }

        return response()->json([
            'path' => MediaPath::toPublic($path),
        ]);
    }

    private function isSvg(UploadedFile $file): bool
    {
        return $file->getMimeType() === 'image/svg+xml' || strtolower($file->getClientOriginalExtension()) === 'svg';
    }

    private function storeSvg(UploadedFile $file, SvgSanitizer $sanitizer): string
    {
        $clean = $sanitizer->clean((string) file_get_contents($file->getRealPath()));

        if ($clean === null) {
            throw ValidationException::withMessages(['file' => 'O SVG enviado não é válido.']);
        }

        $path = 'media/'.Str::random(40).'.svg';
        Storage::disk('public')->put($path, $clean);

        return $path;
    }
}
