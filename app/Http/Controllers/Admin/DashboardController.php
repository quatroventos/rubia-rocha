<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use App\Services\LighthouseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function show(Request $request, AnalyticsService $analytics): JsonResponse
    {
        $range = $request->string('range')->toString();

        if (! in_array($range, ['today', '7d', 'month', '30d', 'last_month'], true)) {
            $range = 'month';
        }

        return response()->json($analytics->dashboard($range));
    }

    public function refreshLighthouse(LighthouseService $lighthouse): JsonResponse
    {
        return response()->json($lighthouse->refresh());
    }
}
