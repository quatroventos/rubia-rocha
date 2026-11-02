<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\SystemOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CacheController extends Controller
{
    public function show(SystemOptions $options): JsonResponse
    {
        $this->authorize('viewAny', SiteSetting::class);

        return response()->json(['data' => $options->status()]);
    }

    public function update(Request $request, SystemOptions $options): JsonResponse
    {
        $this->authorize('viewAny', SiteSetting::class);

        $data = $request->validate([
            'ttl' => ['required', 'integer', 'in:300,900,3600,21600,86400'],
        ]);

        $options->setTtl((int) $data['ttl']);

        return response()->json(['data' => $options->status()]);
    }

    public function flush(SystemOptions $options): JsonResponse
    {
        $this->authorize('viewAny', SiteSetting::class);

        $options->flush();

        return response()->json(['data' => $options->status()]);
    }

    public function warm(SystemOptions $options): JsonResponse
    {
        $this->authorize('viewAny', SiteSetting::class);

        $options->warm();

        return response()->json(['data' => $options->status()]);
    }
}
