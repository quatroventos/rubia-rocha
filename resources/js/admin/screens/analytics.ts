export type RangeKey = 'today' | '7d' | 'month' | '30d' | 'last_month';

export type LighthouseReport = {
  performance: number | null;
  accessibility: number | null;
  best_practices: number | null;
  seo: number | null;
  metrics: {
    fcp_ms: number | null;
    lcp_ms: number | null;
    tbt_ms: number | null;
    si_ms: number | null;
    cls: number | null;
  };
};

export type LighthouseSnapshot = {
  url: string;
  status: 'ready' | 'pending' | 'error';
  fetched_at: string | null;
  mobile: LighthouseReport | null;
  desktop: LighthouseReport | null;
};

export type DashboardStats = {
  range: RangeKey;
  live: { visitors: number };
  load_time_avg_ms: number | null;
  month_visits: number;
  lighthouse: LighthouseSnapshot;
  chart: {
    labels: string[];
    current: number[];
    previous: number[];
    current_label: string;
    previous_label: string;
  };
  pages: { path: string; title: string; views: number }[];
  duration_avg_seconds: number | null;
  depth_avg: number | null;
  origins: { host: string; views: number; pct: number }[];
  locations: {
    city: string;
    region: string | null;
    country: string | null;
    lat: number | null;
    lng: number | null;
    views: number;
  }[];
};

export const rangeOptions: { key: RangeKey; label: string }[] = [
  { key: 'today', label: 'Hoje' },
  { key: '7d', label: 'Últimos 7 dias' },
  { key: 'month', label: 'Este mês' },
  { key: '30d', label: 'Últimos 30 dias' },
  { key: 'last_month', label: 'Mês passado' },
];

export function formatLoadTime(ms: number | null): string {
  if (ms == null) {
    return '—';
  }

  if (ms >= 1000) {
    return `${(ms / 1000).toFixed(1).replace('.', ',')}s`;
  }

  return `${ms}ms`;
}

export function formatDuration(seconds: number | null): string {
  if (seconds == null) {
    return '—';
  }

  if (seconds >= 3600) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    return minutes > 0 ? `${hours}h ${minutes}min` : `${hours}h`;
  }

  if (seconds >= 60) {
    const minutes = Math.floor(seconds / 60);
    const rest = seconds % 60;
    return rest > 0 ? `${minutes}min ${rest}s` : `${minutes}min`;
  }

  return `${seconds}s`;
}

export function formatDepth(value: number | null): string {
  if (value == null) {
    return '—';
  }

  return value.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
}

export function locationLabel(row: DashboardStats['locations'][number]): string {
  return [row.city, row.region, row.country].filter(Boolean).join(', ');
}
