import { useEffect, useState } from 'react';
import { Activity, Clock3, Layers, Timer, Users } from 'lucide-react';
import { AppShell } from '../components/AppShell';
import { DateRangeFilter } from '../components/DateRangeFilter';
import { LocationsMap } from '../components/LocationsMap';
import { SiteHealth } from '../components/SiteHealth';
import { VisitsChart } from '../components/VisitsChart';
import { api } from '../api';
import {
  formatDepth,
  formatDuration,
  formatLoadTime,
  rangeOptions,
  type DashboardStats,
  type RangeKey,
} from './analytics';

const emptyChart = {
  labels: [],
  current: [],
  previous: [],
  current_label: '',
  previous_label: '',
};

export function DashboardScreen() {
  const [range, setRange] = useState<RangeKey>('month');
  const [stats, setStats] = useState<DashboardStats | null>(null);

  useEffect(() => {
    let active = true;

    const load = () => {
      void api<DashboardStats>(`/dashboard?range=${range}`)
        .then((payload) => {
          if (active) {
            setStats(payload);
          }
        })
        .catch(() => undefined);
    };

    load();
    const timer = window.setInterval(load, 15000);

    return () => {
      active = false;
      window.clearInterval(timer);
    };
  }, [range]);

  const rangeLabel = rangeOptions.find((option) => option.key === range)?.label ?? 'Este mês';
  const chart = stats?.chart ?? emptyChart;
  const pages = stats?.pages ?? [];
  const origins = stats?.origins ?? [];
  const locations = stats?.locations ?? [];

  return (
    <AppShell
      eyebrow="Operação"
      title="Dashboard"
      subtitle="Visitas, origem e desempenho das páginas públicas."
      actions={(
        <div data-tour="dash-range">
          <DateRangeFilter value={range} onChange={setRange} />
        </div>
      )}
    >
      <div className="stat-grid">
        <article className="stat-card" data-tour="dash-live">
          <div className="stat-icon coral"><Activity size={18} /></div>
          <label>Online agora</label>
          <strong>{stats?.live.visitors ?? '—'}</strong>
        </article>
        <article className="stat-card" data-tour="dash-load">
          <div className="stat-icon gold"><Timer size={18} /></div>
          <label>Tempo médio para carregamento da página</label>
          <strong>{formatLoadTime(stats?.load_time_avg_ms ?? null)}</strong>
        </article>
        <article className="stat-card" data-tour="dash-month">
          <div className="stat-icon purple"><Users size={18} /></div>
          <label>Visitas do mês</label>
          <strong>{stats?.month_visits ?? '—'}</strong>
        </article>
      </div>

      <div data-tour="dash-health">
        <SiteHealth
          snapshot={stats?.lighthouse ?? null}
          onRefresh={(lighthouse) => {
            setStats((current) => (current ? { ...current, lighthouse } : current));
          }}
        />
      </div>

      <div className="overview-grid">
        <section className="panel" data-tour="dash-chart">
          <div className="panel-head">
            <h3>Acessos por dia</h3>
            <p>Comparativo com o período anterior</p>
          </div>
          <div className="panel-body">
            <VisitsChart chart={chart} />
          </div>
        </section>
        <section className="panel" data-tour="dash-pages">
          <div className="panel-head">
            <h3>Páginas mais acessadas</h3>
            <p>{rangeLabel}</p>
          </div>
          <div className="panel-body">
            {pages.length === 0 ? (
              <p className="empty-inline">Sem acessos no período.</p>
            ) : (
              <ol className="rank-list">
                {pages.map((page) => (
                  <li key={page.path}>
                    <div>
                      <strong>{page.title}</strong>
                      <span>{page.path}</span>
                    </div>
                    <em>{page.views}</em>
                  </li>
                ))}
              </ol>
            )}
          </div>
        </section>
      </div>

      <div className="metric-strip">
        <article className="metric-card" data-tour="dash-duration">
          <div className="stat-icon green"><Clock3 size={18} /></div>
          <div>
            <label>Tempo médio de visita</label>
            <strong>{formatDuration(stats?.duration_avg_seconds ?? null)}</strong>
          </div>
        </article>
        <article className="metric-card" data-tour="dash-depth">
          <div className="stat-icon blue"><Layers size={18} /></div>
          <div>
            <label>Profundidade da visita</label>
            <strong>{formatDepth(stats?.depth_avg ?? null)}</strong>
            <span>páginas por sessão</span>
          </div>
        </article>
      </div>

      <div className="analytics-split">
        <section className="panel" data-tour="dash-origins">
          <div className="panel-head">
            <h3>Principais origens</h3>
            <p>De onde a visita chegou</p>
          </div>
          <div className="panel-body">
            {origins.length === 0 ? (
              <p className="empty-inline">Sem origens no período.</p>
            ) : (
              <ol className="rank-list">
                {origins.map((origin) => (
                  <li key={origin.host}>
                    <div>
                      <strong>{origin.host}</strong>
                      <span>{origin.pct.toLocaleString('pt-BR')}%</span>
                    </div>
                    <em>{origin.views}</em>
                  </li>
                ))}
              </ol>
            )}
          </div>
        </section>
        <section className="panel" data-tour="dash-map">
          <div className="panel-head">
            <h3>Principais locais</h3>
            <p>Cidades com mais visitas</p>
          </div>
          <div className="panel-body geo-panel">
            <LocationsMap locations={locations} />
            {locations.length === 0 ? (
              <p className="empty-inline">Sem localização no período.</p>
            ) : (
              <ol className="rank-list">
                {locations.slice(0, 6).map((row) => (
                  <li key={`${row.city}-${row.region}-${row.country}`}>
                    <div>
                      <strong>{row.city}</strong>
                      <span>{[row.region, row.country].filter(Boolean).join(', ')}</span>
                    </div>
                    <em>{row.views}</em>
                  </li>
                ))}
              </ol>
            )}
          </div>
        </section>
      </div>
    </AppShell>
  );
}
