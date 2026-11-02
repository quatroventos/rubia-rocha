<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\PageSession;
use App\Models\PageView;
use App\Services\GeoLocationService;
use App\Support\Locales;
use App\Support\UserAgent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SiteAnalyticsController extends Controller
{
    public function __invoke(Request $request, GeoLocationService $geo): Response
    {
        if ($this->shouldIgnore($request)) {
            return response()->noContent();
        }

        $payload = $this->payload($request);

        $validator = Validator::make($payload, [
            't' => 'required|in:view,beat,leave',
            'sid' => 'required|uuid',
            'vid' => 'required|uuid',
            'path' => 'nullable|string|max:255',
            'ref' => 'nullable|string|max:2048',
            'lt' => 'nullable|integer|min:0|max:120000',
        ]);

        if ($validator->fails()) {
            return response()->noContent();
        }

        $data = $validator->validated();
        $path = $this->normalizePath($data['path'] ?? '/');

        if ($this->isAdminPath($path)) {
            return response()->noContent();
        }

        $now = now();
        $session = PageSession::query()->where('session_key', $data['sid'])->first();

        if ($session === null) {
            $ua = (string) $request->userAgent();
            $parsed = UserAgent::parse($ua);
            $location = $geo->locate($request);
            $referrer = $this->cleanReferrer($data['ref'] ?? null);

            $session = PageSession::query()->create([
                'session_key' => $data['sid'],
                'visitor_key' => $data['vid'],
                'ip' => (string) $request->ip(),
                'country' => $location['country'],
                'region' => $location['region'],
                'city' => $location['city'],
                'latitude' => $location['latitude'],
                'longitude' => $location['longitude'],
                'device' => $parsed['device'],
                'browser' => $parsed['browser'],
                'os' => $parsed['os'],
                'user_agent' => Str::limit($ua, 512, ''),
                'origin' => $this->originHost($referrer),
                'referrer' => $referrer,
                'started_at' => $now,
                'last_seen_at' => $now,
                'duration_seconds' => 0,
                'page_count' => 0,
            ]);
        }

        $session->last_seen_at = $now;
        $session->duration_seconds = max(0, (int) $session->started_at->diffInSeconds($now));

        if ($data['t'] === 'view') {
            $session->page_count = $session->page_count + 1;

            PageView::query()->create([
                'page_session_id' => $session->id,
                'page_id' => $this->pageIdForPath($path),
                'path' => $path,
                'load_time_ms' => isset($data['lt']) ? (int) $data['lt'] : null,
                'created_at' => $now,
            ]);
        }

        $session->save();

        return response()->noContent();
    }

    private function shouldIgnore(Request $request): bool
    {
        if ($request->header('DNT') === '1') {
            return true;
        }

        if (UserAgent::isBot($request->userAgent())) {
            return true;
        }

        return ! $this->sameOrigin($request);
    }

    private function sameOrigin(Request $request): bool
    {
        $source = $request->headers->get('Origin') ?: $request->headers->get('Referer');

        if (! is_string($source) || $source === '') {
            return true;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $sourceHost = parse_url($source, PHP_URL_HOST);

        if (! is_string($appHost) || $appHost === '' || ! is_string($sourceHost) || $sourceHost === '') {
            return true;
        }

        return strcasecmp($appHost, $sourceHost) === 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        if ($request->isJson()) {
            return $request->all();
        }

        $raw = $request->getContent();

        if ($raw !== '') {
            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $request->all();
    }

    private function normalizePath(?string $path): string
    {
        $path = trim((string) $path);

        if ($path === '' || $path === '/') {
            return '/';
        }

        $parsed = parse_url($path, PHP_URL_PATH);
        $path = is_string($parsed) ? $parsed : $path;
        $path = '/'.ltrim($path, '/');

        return rtrim($path, '/') ?: '/';
    }

    private function isAdminPath(string $path): bool
    {
        $admin = trim((string) config('cms.admin_path'), '/');

        if ($admin === '') {
            return false;
        }

        return $path === '/'.$admin || str_starts_with($path, '/'.$admin.'/');
    }

    private function pageIdForPath(string $path): ?int
    {
        [$locale, $rest] = Locales::splitPath($path);
        $slug = trim($rest, '/');
        $query = Page::query()->where('locale', $locale);

        return $slug === ''
            ? $query->where('template', 'home')->value('id')
            : $query->where('slug', $slug)->value('id');
    }

    private function cleanReferrer(?string $referrer): ?string
    {
        $referrer = trim((string) $referrer);

        if ($referrer === '') {
            return null;
        }

        return Str::limit($referrer, 2048, '');
    }

    private function originHost(?string $referrer): string
    {
        if ($referrer === null) {
            return 'direct';
        }

        $host = parse_url($referrer, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return 'direct';
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (is_string($appHost) && $appHost !== '' && strcasecmp($host, $appHost) === 0) {
            return 'direct';
        }

        return Str::lower($host);
    }
}
