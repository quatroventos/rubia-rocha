import { Monitor, RefreshCw, Smartphone } from 'lucide-react';
import { useState } from 'react';
import { api } from '../api';
import type { LighthouseReport, LighthouseSnapshot } from '../screens/analytics';

const categories: { key: keyof Pick<LighthouseReport, 'performance' | 'accessibility' | 'best_practices' | 'seo'>; label: string }[] = [
  { key: 'performance', label: 'Performance' },
  { key: 'accessibility', label: 'Acessibilidade' },
  { key: 'best_practices', label: 'Boas práticas' },
  { key: 'seo', label: 'SEO' },
];

export function scoreTone(score: number | null): 'green' | 'gold' | 'coral' | 'muted' {
  if (score == null) {
    return 'muted';
  }

  if (score >= 90) {
    return 'green';
  }

  if (score >= 50) {
    return 'gold';
  }

  return 'coral';
}

export function formatMs(ms: number | null): string {
  if (ms == null) {
    return 'n/d';
  }

  if (ms >= 1000) {
    return `${(ms / 1000).toFixed(1).replace('.', ',')}s`;
  }

  return `${ms}ms`;
}

export function ScoreRing({ score, label }: { score: number | null; label: string }) {
  const radius = 34;
  const circumference = 2 * Math.PI * radius;
  const progress = score == null ? 0 : Math.max(0, Math.min(score, 100)) / 100;
  const tone = scoreTone(score);

  return (
    <div className={`health-ring ${tone}`}>
      <svg viewBox="0 0 88 88" aria-hidden="true">
        <circle className="health-ring-track" cx="44" cy="44" r={radius} />
        <circle
          className="health-ring-value"
          cx="44"
          cy="44"
          r={radius}
          strokeDasharray={circumference}
          strokeDashoffset={circumference * (1 - progress)}
        />
      </svg>
      <div>
        <strong>{score ?? 'n/d'}</strong>
        <span>{label}</span>
      </div>
    </div>
  );
}

function StrategyCard({
  title,
  icon: Icon,
  report,
}: {
  title: string;
  icon: typeof Smartphone;
  report: LighthouseReport | null;
}) {
  const max = 100;

  return (
    <article className="health-strategy">
      <header>
        <span className="health-device"><Icon size={14} /> {title}</span>
      </header>
      <div className="health-visual">
        <ScoreRing score={report?.performance ?? null} label="Saúde" />
        <svg className="health-bars" viewBox="0 0 268 132" role="img" aria-label={`Categorias ${title}`}>
          {categories.map((category, index) => {
            const value = report?.[category.key] ?? 0;
            const width = (value / max) * 148;
            const y = 8 + index * 32;
            const tone = scoreTone(report?.[category.key] ?? null);

            return (
              <g key={category.key}>
                <text x="0" y={y + 11} className="health-bar-label">{category.label}</text>
                <rect x="86" y={y} width="148" height="14" rx="4" className="health-bar-track" />
                <rect x="86" y={y} width={Math.max(width, value > 0 ? 4 : 0)} height="14" rx="4" className={`health-bar-fill ${tone}`} />
                <text x="262" y={y + 11} textAnchor="end" className="health-bar-value">{report?.[category.key] ?? 'n/d'}</text>
              </g>
            );
          })}
        </svg>
      </div>
      <dl className="health-metrics">
        <div><dt>LCP</dt><dd>{formatMs(report?.metrics.lcp_ms ?? null)}</dd></div>
        <div><dt>FCP</dt><dd>{formatMs(report?.metrics.fcp_ms ?? null)}</dd></div>
        <div><dt>TBT</dt><dd>{formatMs(report?.metrics.tbt_ms ?? null)}</dd></div>
        <div><dt>CLS</dt><dd>{report?.metrics.cls ?? 'n/d'}</dd></div>
      </dl>
    </article>
  );
}

export function SiteHealth({
  snapshot,
  onRefresh,
}: {
  snapshot: LighthouseSnapshot | null;
  onRefresh: (next: LighthouseSnapshot) => void;
}) {
  const [loading, setLoading] = useState(false);
  const status = snapshot?.status ?? 'pending';

  const refresh = () => {
    setLoading(true);
    void api<LighthouseSnapshot>('/dashboard/lighthouse', { method: 'POST' })
      .then(onRefresh)
      .catch(() => undefined)
      .finally(() => setLoading(false));
  };

  const message = {
    pending: 'A primeira medição Lighthouse ainda não rodou.',
    error: 'Falha ao consultar a API do PageSpeed.',
    ready: snapshot?.fetched_at
      ? `Atualizado em ${new Date(snapshot.fetched_at).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}`
      : 'Medição Lighthouse',
  }[status === 'ready' || status === 'error' ? status : 'pending'];

  return (
    <section className="panel health-panel">
      <div className="panel-head health-head">
        <div>
          <h3>Saúde do site</h3>
          <p>{message}</p>
        </div>
        <button type="button" className="btn btn-secondary" onClick={refresh} disabled={loading}>
          <RefreshCw size={14} />
          {loading ? 'Medindo' : 'Atualizar'}
        </button>
      </div>
      <div className="panel-body">
        <div className="health-grid">
          <StrategyCard title="Mobile" icon={Smartphone} report={snapshot?.mobile ?? null} />
          <StrategyCard title="Desktop" icon={Monitor} report={snapshot?.desktop ?? null} />
        </div>
      </div>
    </section>
  );
}
