<?php

namespace App\Services;

use App\Models\Page;
use App\Models\PageSession;
use App\Models\PageView;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Str;

class AnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(string $range, ?LighthouseService $lighthouse = null): array
    {
        [$from, $to, $previousFrom, $previousTo, $bucket] = $this->bounds($range);
        $lighthouse ??= app(LighthouseService::class);

        return [
            'range' => $range,
            'live' => [
                'visitors' => PageSession::query()
                    ->where('last_seen_at', '>=', now()->subMinutes(3))
                    ->count(),
            ],
            'load_time_avg_ms' => $this->averageLoadTime($from, $to),
            'month_visits' => PageSession::query()
                ->whereBetween('started_at', [now()->copy()->startOfMonth(), now()->copy()->endOfMonth()])
                ->count(),
            'chart' => $this->chart($from, $to, $previousFrom, $previousTo, $bucket),
            'pages' => $this->topPages($from, $to),
            'duration_avg_seconds' => $this->averageDuration($from, $to),
            'depth_avg' => $this->averageDepth($from, $to),
            'origins' => $this->origins($from, $to),
            'locations' => $this->locations($from, $to),
            'lighthouse' => $lighthouse->snapshot(),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: Carbon, 4: 'hour'|'day'}
     */
    public function bounds(string $range): array
    {
        $now = now();

        return match ($range) {
            'today' => [
                $now->copy()->startOfDay(),
                $now->copy()->endOfDay(),
                $now->copy()->subDay()->startOfDay(),
                $now->copy()->subDay()->endOfDay(),
                'hour',
            ],
            '7d' => [
                $now->copy()->subDays(6)->startOfDay(),
                $now->copy()->endOfDay(),
                $now->copy()->subDays(13)->startOfDay(),
                $now->copy()->subDays(7)->endOfDay(),
                'day',
            ],
            '30d' => [
                $now->copy()->subDays(29)->startOfDay(),
                $now->copy()->endOfDay(),
                $now->copy()->subDays(59)->startOfDay(),
                $now->copy()->subDays(30)->endOfDay(),
                'day',
            ],
            'last_month' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
                $now->copy()->subMonthsNoOverflow(2)->startOfMonth(),
                $now->copy()->subMonthsNoOverflow(2)->endOfMonth(),
                'day',
            ],
            default => [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
                'day',
            ],
        };
    }

    /**
     * @return array{labels: list<string>, current: list<int>, previous: list<int>, current_label: string, previous_label: string}
     */
    private function chart(Carbon $from, Carbon $to, Carbon $previousFrom, Carbon $previousTo, string $bucket): array
    {
        $current = $this->series($from, $to, $bucket);
        $previous = $this->series($previousFrom, $previousTo, $bucket);

        if (count($previous['values']) < count($current['values'])) {
            $previous['values'] = array_pad($previous['values'], count($current['values']), 0);
        }

        if (count($previous['values']) > count($current['values'])) {
            $previous['values'] = array_slice($previous['values'], 0, count($current['values']));
        }

        return [
            'labels' => $current['labels'],
            'current' => $current['values'],
            'previous' => $previous['values'],
            'current_label' => $this->periodLabel($from, $to, $bucket),
            'previous_label' => $this->periodLabel($previousFrom, $previousTo, $bucket),
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    private function series(Carbon $from, Carbon $to, string $bucket): array
    {
        $sessions = PageSession::query()
            ->whereBetween('started_at', [$from, $to])
            ->get(['started_at']);

        if ($bucket === 'hour') {
            $counts = array_fill(0, 24, 0);

            foreach ($sessions as $session) {
                $counts[(int) $session->started_at->format('G')]++;
            }

            $labels = [];
            for ($hour = 0; $hour < 24; $hour++) {
                $labels[] = str_pad((string) $hour, 2, '0', STR_PAD_LEFT).'h';
            }

            return [
                'labels' => $labels,
                'values' => array_values($counts),
            ];
        }

        $counts = [];
        $labels = [];
        $period = CarbonPeriod::create($from->copy()->startOfDay(), '1 day', $to->copy()->startOfDay());

        foreach ($period as $day) {
            $key = $day->toDateString();
            $counts[$key] = 0;
            $labels[] = $day->format('d/m');
        }

        foreach ($sessions as $session) {
            $key = $session->started_at->toDateString();

            if (array_key_exists($key, $counts)) {
                $counts[$key]++;
            }
        }

        return [
            'labels' => $labels,
            'values' => array_values($counts),
        ];
    }

    /**
     * @return list<array{path: string, title: string, views: int}>
     */
    private function topPages(Carbon $from, Carbon $to): array
    {
        $rows = PageView::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('path, page_id, count(*) as views')
            ->groupBy('path', 'page_id')
            ->orderByDesc('views')
            ->limit(8)
            ->get();

        $titles = Page::query()
            ->whereIn('id', $rows->pluck('page_id')->filter()->all())
            ->get(['id', 'slug', 'seo_title'])
            ->keyBy('id');

        return $rows->map(function ($row) use ($titles): array {
            $page = $row->page_id ? $titles->get($row->page_id) : null;
            $title = $page?->seo_title ?: $this->titleFromPath($row->path);

            if (str_contains($title, '|')) {
                $title = trim(explode('|', $title, 2)[0]);
            }

            return [
                'path' => $row->path,
                'title' => $title,
                'views' => (int) $row->views,
            ];
        })->all();
    }

    /**
     * @return list<array{host: string, views: int, pct: float}>
     */
    private function origins(Carbon $from, Carbon $to): array
    {
        $rows = PageSession::query()
            ->whereBetween('started_at', [$from, $to])
            ->selectRaw('origin, count(*) as views')
            ->groupBy('origin')
            ->orderByDesc('views')
            ->limit(8)
            ->get();

        $total = max(1, (int) $rows->sum('views'));

        return $rows->map(fn ($row): array => [
            'host' => $row->origin === 'direct' || $row->origin === '' ? 'Direto' : $row->origin,
            'views' => (int) $row->views,
            'pct' => round(((int) $row->views / $total) * 100, 1),
        ])->all();
    }

    /**
     * @return list<array{city: string, region: ?string, country: ?string, lat: ?float, lng: ?float, views: int}>
     */
    private function locations(Carbon $from, Carbon $to): array
    {
        return PageSession::query()
            ->whereBetween('started_at', [$from, $to])
            ->whereNotNull('city')
            ->selectRaw('city, region, country, latitude, longitude, count(*) as views')
            ->groupBy('city', 'region', 'country', 'latitude', 'longitude')
            ->orderByDesc('views')
            ->limit(20)
            ->get()
            ->map(fn ($row): array => [
                'city' => $row->city,
                'region' => $row->region,
                'country' => $row->country,
                'lat' => $row->latitude !== null ? (float) $row->latitude : null,
                'lng' => $row->longitude !== null ? (float) $row->longitude : null,
                'views' => (int) $row->views,
            ])
            ->all();
    }

    private function averageLoadTime(Carbon $from, Carbon $to): ?int
    {
        $avg = PageView::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('load_time_ms')
            ->where('load_time_ms', '>', 0)
            ->avg('load_time_ms');

        return $avg === null ? null : (int) round((float) $avg);
    }

    private function averageDuration(Carbon $from, Carbon $to): ?int
    {
        $avg = PageSession::query()
            ->whereBetween('started_at', [$from, $to])
            ->avg('duration_seconds');

        return $avg === null ? null : (int) round((float) $avg);
    }

    private function averageDepth(Carbon $from, Carbon $to): ?float
    {
        $avg = PageSession::query()
            ->whereBetween('started_at', [$from, $to])
            ->avg('page_count');

        return $avg === null ? null : round((float) $avg, 1);
    }

    private function periodLabel(Carbon $from, Carbon $to, string $bucket): string
    {
        if ($bucket === 'hour') {
            return $from->isToday() ? 'Hoje' : $from->format('d/m');
        }

        if ($from->isSameMonth($to) && $from->day === 1 && $to->isSameDay($to->copy()->endOfMonth())) {
            return $this->monthName($from);
        }

        if ($from->isSameDay($to)) {
            return $from->format('d/m');
        }

        return $from->format('d/m').' – '.$to->format('d/m');
    }

    private function monthName(CarbonInterface $date): string
    {
        $months = [
            1 => 'Janeiro',
            2 => 'Fevereiro',
            3 => 'Março',
            4 => 'Abril',
            5 => 'Maio',
            6 => 'Junho',
            7 => 'Julho',
            8 => 'Agosto',
            9 => 'Setembro',
            10 => 'Outubro',
            11 => 'Novembro',
            12 => 'Dezembro',
        ];

        return $months[$date->month].' '.$date->year;
    }

    private function titleFromPath(string $path): string
    {
        if ($path === '/') {
            return 'Home';
        }

        return Str::of($path)->trim('/')->replace('-', ' ')->title()->toString();
    }
}
