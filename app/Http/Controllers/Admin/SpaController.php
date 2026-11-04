<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemOptions;
use Illuminate\Contracts\View\View;
use Throwable;

class SpaController extends Controller
{
    public function __invoke(SystemOptions $options): View
    {
        $name = 'Rubia Rocha Fotografia';
        $logo = null;
        $favicon = null;

        try {
            $site = $options->site();
            if ($site) {
                $name = filled($site->name) ? (string) $site->name : $name;
                $logoPath = media($site->logo_path);
                $logo = $logoPath !== '' ? $logoPath : null;
                $faviconPath = media($site->favicon_path);
                $favicon = $faviconPath !== '' ? $faviconPath : null;
            }
        } catch (Throwable) {
            //
        }

        return view('admin', [
            'adminPath' => '/'.trim((string) config('cms.admin_path'), '/'),
            'branding' => [
                'name' => $name,
                'logo' => $logo,
                'favicon' => $favicon,
            ],
        ]);
    }
}
