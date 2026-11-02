<?php

namespace App\Http\Controllers;

use App\Services\Seo\OgImageCapture;
use App\Services\SystemOptions;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;

/**
 * Página aberta só pelo Chrome do servidor para gerar a OG image. O token expira em minutos e some após a captura.
 */
class OgCaptureController extends Controller
{
    public function __invoke(string $token): Response
    {
        $entry = Cache::get(OgImageCapture::tokenKey($token));

        abort_unless(is_array($entry), 404);

        if (is_file(public_path('hot'))) {
            $hot = storage_path('app/og-capture.hot');
            File::put($hot, (string) config('cms.capture_vite_url'));
            Vite::useHotFile($hot);
        }

        return response()
            ->view('site.app', [
                'site' => app(SystemOptions::class)->site(),
                'payload' => $entry['payload'],
                'sitePayload' => $entry['site'],
                'ogCard' => $entry['og'] ?? null,
                'cmsPreview' => true,
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store');
    }
}
