import { type FormEvent, type ReactNode, useCallback, useEffect, useMemo, useState } from 'react';
import { ChevronDown, ChevronRight, ExternalLink, RefreshCw, Trash2 } from 'lucide-react';
import { api, firstError } from '../api';
import { Modal } from './Modal';

type Failure = { state: 'unavailable' | 'unconfigured'; message: string; action_url?: string | null };

type Overview = {
  configured: boolean;
  project: { name: string | null; domain: string | null } | null;
  message: string | null;
  site_url: string;
  paid: boolean;
};

type Performance = { clicks: number; impressions: number; ctr: number | null; position: number | null };

type SearchConsole = Failure | {
  state: 'ready';
  range: string;
  link: string | null;
  totals: Performance;
  series: (Performance & { date: string })[];
  queries: (Performance & { query: string })[];
  pages: (Performance & { page: string })[];
  striking: (Performance & { query: string; page: string })[];
};

type Opportunities = Failure | {
  state: 'ready';
  link: string | null;
  rows: { url: string; score: number | null; position: number | null; impressions: number | null; clicks: number | null; sessions: number | null; conversions: number | null }[];
};

type AuditIssue = { type: string; title: string; fix: string | null; severity: 'critical' | 'warning' | 'info'; count: number; pages: { url: string; detail: string | null }[] };

type Audit = Failure | {
  state: 'none';
  link: string | null;
  site_url: string;
} | {
  state: 'ready' | 'running' | 'failed';
  link: string | null;
  site_url: string;
  audit: { id: string; url: string | null; phase: string | null; pages_crawled: number; pages_total: number; lighthouse_total: number; lighthouse_done: number; started_at: string | null; completed_at: string | null; error: string | null };
  counts?: { critical: number; warning: number; info: number };
  issues?: AuditIssue[];
};

type KeywordRow = { keyword: string; volume: number | null; difficulty: number | null; intent: string | null; intent_code: string | null; cpc: number | null };

type SavedKeywords = Failure | { state: 'ready'; link: string | null; rows: (KeywordRow & { id: string; tags: string[] })[] };

type Inspection = Failure | {
  state: 'ready';
  link: string | null;
  rows: { url: string; indexed: boolean | null; verdict: string | null; coverage: string | null; last_crawl: string | null; google_canonical: string | null; user_canonical: string | null; error: string | null }[];
};

type Domain = Failure | { state: 'idle'; domain: string } | {
  state: 'ready';
  domain: string;
  fetched_at: string;
  link: string | null;
  metrics: { traffic: number | null; keywords: number | null; backlinks: number | null; referring_domains: number | null };
  top_keywords: (KeywordRow & { position: number | null; url: string | null })[];
};

type Rate = { current: number | null; previous: number | null; change: number | null };

type AiVisibility = Failure | { state: 'none'; link: string | null } | {
  state: 'ready';
  days: number;
  link: string | null;
  tracker: { enabled: boolean; schedule: string | null; next_check_at: string | null; topics: string[]; prompts: number; paused: number };
  comparison: string | null;
  mentions: Rate;
  citations: Rate;
  coverage: { expected: number; answered: number; failed: number };
  engines: { engine: string; mentions: Rate; citations: Rate }[];
  runs: { created_at: string | null; mention_rate: number | null; citation_rate: number | null; expected: number; answered: number; failed: number }[];
};

type PanelKey = 'audit' | 'search' | 'keywords' | 'index' | 'domain' | 'ai';

const PANELS: { key: PanelKey; label: string; hint: string }[] = [
  { key: 'audit', label: 'Auditoria', hint: 'Problemas técnicos encontrados ao rastrear o site.' },
  { key: 'search', label: 'Search Console', hint: 'Cliques, impressões e buscas reais no Google.' },
  { key: 'keywords', label: 'Palavras-chave', hint: 'Termos salvos e pesquisa de novas ideias.' },
  { key: 'index', label: 'Indexação', hint: 'Se o Google indexou as páginas do sitemap.' },
  { key: 'domain', label: 'Domínio', hint: 'Tráfego orgânico estimado e backlinks.' },
  { key: 'ai', label: 'IA', hint: 'Menções da marca em respostas do ChatGPT e outras IAs.' },
];

const RANGES = [
  { key: 'last_7_days', label: '7 dias' },
  { key: 'last_28_days', label: '28 dias' },
  { key: 'last_3_months', label: '3 meses' },
  { key: 'last_6_months', label: '6 meses' },
  { key: 'last_12_months', label: '12 meses' },
];

const SEVERITY: Record<AuditIssue['severity'], string> = { critical: 'Crítico', warning: 'Aviso', info: 'Info' };

const SCHEDULE: Record<string, string> = { daily: 'diária', weekly: 'semanal', monthly: 'mensal' };

const COMPARISON: Record<string, string> = {
  comparable: '',
  incomplete: 'Coleta incompleta no período (menos de 90% das respostas); a variação não é confiável.',
  scope_changed: 'As perguntas mudaram entre os períodos; a variação não é comparável.',
  no_previous: 'Ainda não há período anterior para comparar.',
  no_data: 'Sem respostas coletadas no período.',
};

const EMPTY = 'n/d';

const number = (value: number | null | undefined) => (value === null || value === undefined ? EMPTY : value.toLocaleString('pt-BR'));
const percent = (value: number | null | undefined, digits = 1) => (value === null || value === undefined ? EMPTY : `${(value * 100).toLocaleString('pt-BR', { maximumFractionDigits: digits })}%`);
const points = (value: number | null | undefined) => (value === null || value === undefined ? EMPTY : `${value.toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%`);
const decimal = (value: number | null | undefined) => (value === null || value === undefined ? EMPTY : value.toLocaleString('pt-BR', { maximumFractionDigits: 1 }));
const shortDate = (value: string | null | undefined) => (value ? new Date(value.replace(' ', 'T')).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' }) : EMPTY);
const pathOf = (url: string) => {
  try {
    const parsed = new URL(url);
    return `${parsed.host.startsWith('www.') ? 'www ' : ''}${parsed.pathname}${parsed.search}`;
  } catch {
    return url;
  }
};

function isFailure(value: { state?: string } | null): value is Failure {
  return value?.state === 'unavailable' || value?.state === 'unconfigured';
}

/**
 * Carrega um bloco da aba. `reload(true)` ignora o cache do servidor.
 */
function useOpenSeo<T>(path: string) {
  const [data, setData] = useState<T | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const reload = useCallback(async (fresh = false) => {
    setLoading(true);
    setError('');
    try {
      const separator = path.includes('?') ? '&' : '?';
      setData(await api<T>(`/seo/openseo${path}${fresh ? `${separator}fresh=1` : ''}`));
    } catch (err) {
      setError(firstError(err));
    } finally {
      setLoading(false);
    }
  }, [path]);

  useEffect(() => {
    void reload();
  }, [reload]);

  return { data, setData, loading, error, reload };
}

function Unavailable({ failure, label = 'Resolver no OpenSEO' }: { failure: Failure; label?: string }) {
  return (
    <div className="oseo-unavailable">
      <p>{failure.message}</p>
      {failure.action_url ? (
        <a className="btn btn-secondary btn-sm" href={failure.action_url} target="_blank" rel="noreferrer">
          {label} <ExternalLink size={12} />
        </a>
      ) : null}
    </div>
  );
}

function Stat({ label, value, note, tone }: { label: string; value: ReactNode; note?: ReactNode; tone?: 'good' | 'ok' | 'bad' }) {
  return (
    <div className={`oseo-stat${tone ? ` is-${tone}` : ''}`}>
      <span>{label}</span>
      <strong>{value}</strong>
      {note ? <small>{note}</small> : null}
    </div>
  );
}

function PanelHead({ title, hint, link, linkLabel = 'OpenSEO', loading, onRefresh, children }: { title: string; hint: string; link?: string | null; linkLabel?: string; loading?: boolean; onRefresh?: () => void; children?: ReactNode }) {
  return (
    <div className="oseo-head">
      <div>
        <h3>{title}</h3>
        <p>{hint}</p>
      </div>
      <div className="oseo-head-actions">
        {children}
        {onRefresh ? (
          <button className="btn btn-secondary btn-sm" type="button" disabled={loading} onClick={onRefresh}>
            <RefreshCw size={12} className={loading ? 'is-spinning' : undefined} /> Atualizar
          </button>
        ) : null}
        {link ? (
          <a className="btn btn-secondary btn-sm" href={link} target="_blank" rel="noreferrer">
            {linkLabel} <ExternalLink size={12} />
          </a>
        ) : null}
      </div>
    </div>
  );
}

function Loading() {
  return <div className="oseo-loading"><div className="spinner" /></div>;
}

function CostDialog({ title, message, busy, confirmLabel, onConfirm, onClose }: { title: string; message: ReactNode; busy: boolean; confirmLabel: string; onConfirm: () => void; onClose: () => void }) {
  return (
    <Modal
      title={title}
      eyebrow="Consulta paga"
      onClose={onClose}
      footer={(
        <>
          <button className="btn btn-secondary" type="button" disabled={busy} onClick={onClose}>Cancelar</button>
          <button className="btn btn-primary" type="button" disabled={busy} onClick={onConfirm}>{busy ? 'Enviando...' : confirmLabel}</button>
        </>
      )}
    >
      <div className="oseo-cost">{message}</div>
    </Modal>
  );
}

function Sparkline({ values }: { values: number[] }) {
  if (values.length < 2) {
    return null;
  }

  const max = Math.max(...values, 1);
  const step = 100 / (values.length - 1);
  const line = values.map((value, index) => `${(index * step).toFixed(2)},${(30 - (value / max) * 28).toFixed(2)}`).join(' ');

  return (
    <svg className="oseo-spark" viewBox="0 0 100 30" preserveAspectRatio="none" aria-hidden="true">
      <polyline points={`0,30 ${line} 100,30`} className="oseo-spark-area" />
      <polyline points={line} className="oseo-spark-line" />
    </svg>
  );
}

function AuditPanel({ canEdit }: { canEdit: boolean }) {
  const { data, setData, loading, error, reload } = useOpenSeo<Audit>('/audit');
  const [open, setOpen] = useState<string | null>(null);
  const [maxPages, setMaxPages] = useState(100);
  const [lighthouse, setLighthouse] = useState(false);
  const [busy, setBusy] = useState(false);
  const [runError, setRunError] = useState('');

  const running = data?.state === 'running';

  useEffect(() => {
    if (!running) {
      return undefined;
    }

    const timer = window.setInterval(() => void reload(true), 5000);

    return () => window.clearInterval(timer);
  }, [running, reload]);

  async function start(event: FormEvent) {
    event.preventDefault();
    setBusy(true);
    setRunError('');
    try {
      setData(await api<Audit>('/seo/openseo/audit', { method: 'POST', body: JSON.stringify({ max_pages: maxPages, lighthouse }) }));
    } catch (err) {
      setRunError(firstError(err));
    } finally {
      setBusy(false);
    }
  }

  const runForm = canEdit && data && !isFailure(data) ? (
    <form className="oseo-inline-form" onSubmit={(event) => void start(event)}>
      <label className="oseo-select">
        <span>Até</span>
        <select value={maxPages} onChange={(event) => setMaxPages(Number(event.target.value))} disabled={busy || running}>
          {[50, 100, 250, 500].map((value) => <option key={value} value={value}>{value} páginas</option>)}
        </select>
      </label>
      <label className="check">
        <input type="checkbox" checked={lighthouse} onChange={(event) => setLighthouse(event.target.checked)} disabled={busy || running} />
        Lighthouse
      </label>
      <button className="btn btn-primary btn-sm" type="submit" disabled={busy || running}>
        {running ? 'Rastreando...' : busy ? 'Iniciando...' : 'Nova auditoria'}
      </button>
    </form>
  ) : null;

  return (
    <section className="panel oseo-panel">
      <PanelHead title="Auditoria do site" hint="O OpenSEO rastreia o site publicado e lista o que atrapalha o Google, do mais grave ao menos grave." link={data && 'link' in data ? data.link : null} loading={loading} onRefresh={() => void reload(true)}>
        {runForm}
      </PanelHead>
      <div className="panel-body">
        {runError ? <div className="form-error">{runError}</div> : null}
        {error ? <div className="form-error">{error}</div> : null}
        {!data && loading ? <Loading /> : null}
        {data && isFailure(data) ? <Unavailable failure={data} /> : null}
        {data?.state === 'none' ? <p className="empty-inline">Nenhuma auditoria ainda. Rode a primeira para ver os problemas de {data.site_url}.</p> : null}
        {data && 'audit' in data ? (
          <>
            {data.state === 'running' ? (
              <div className="oseo-progress">
                <div className="oseo-progress-bar"><span style={{ width: `${data.audit.pages_total ? Math.min(100, (data.audit.pages_crawled / data.audit.pages_total) * 100) : 8}%` }} /></div>
                <p>{data.audit.pages_crawled} de {data.audit.pages_total || '?'} páginas rastreadas{data.audit.phase ? ` (${data.audit.phase})` : ''}. A lista aparece ao terminar.</p>
              </div>
            ) : null}
            {data.state === 'failed' ? <div className="form-error">A última auditoria falhou{data.audit.error ? ` (${data.audit.error})` : ''}. Rode de novo.</div> : null}
            {data.counts ? (
              <div className="oseo-stats">
                <Stat label="Críticos" value={number(data.counts.critical)} tone={data.counts.critical > 0 ? 'bad' : 'good'} />
                <Stat label="Avisos" value={number(data.counts.warning)} tone={data.counts.warning > 0 ? 'ok' : 'good'} />
                <Stat label="Informativos" value={number(data.counts.info)} />
                <Stat label="Páginas rastreadas" value={number(data.audit.pages_crawled)} note={`em ${shortDate(data.audit.completed_at ?? data.audit.started_at)}`} />
              </div>
            ) : null}
            {data.issues && data.issues.length > 0 ? (
              <ul className="oseo-issues">
                {data.issues.map((issue) => {
                  const expanded = open === issue.type;
                  return (
                    <li key={issue.type} className={`is-${issue.severity}`}>
                      <button type="button" className="oseo-issue-head" aria-expanded={expanded} onClick={() => setOpen(expanded ? null : issue.type)}>
                        {expanded ? <ChevronDown size={13} /> : <ChevronRight size={13} />}
                        <span className={`oseo-sev is-${issue.severity}`}>{SEVERITY[issue.severity]}</span>
                        <strong>{issue.title}</strong>
                        <em>{issue.count} {issue.count === 1 ? 'página' : 'páginas'}</em>
                      </button>
                      {expanded ? (
                        <div className="oseo-issue-body">
                          {issue.fix ? <p className="oseo-fix">{issue.fix}</p> : null}
                          <ul>
                            {issue.pages.map((page, index) => (
                              <li key={`${page.url}-${index}`}>
                                <a href={page.url} target="_blank" rel="noreferrer">{pathOf(page.url)}</a>
                                {page.detail ? <span>{page.detail}</span> : null}
                              </li>
                            ))}
                          </ul>
                          {issue.count > issue.pages.length ? <p className="hint">Mostrando {issue.pages.length} de {issue.count}. A lista completa está no OpenSEO.</p> : null}
                        </div>
                      ) : null}
                    </li>
                  );
                })}
              </ul>
            ) : null}
            {data.state === 'ready' && data.issues?.length === 0 ? <p className="empty-inline">Nenhum problema encontrado na última auditoria.</p> : null}
          </>
        ) : null}
      </div>
    </section>
  );
}

function PerformanceTable<T extends Performance>({ title, rows, label, empty }: { title: string; rows: T[]; label: (row: T) => ReactNode; empty: string }) {
  return (
    <div className="oseo-block">
      <h4>{title}</h4>
      {rows.length === 0 ? <p className="empty-inline">{empty}</p> : (
        <div className="table-panel">
          <table className="oseo-table">
            <thead>
              <tr>
                <th>Termo</th>
                <th>Cliques</th>
                <th>Impressões</th>
                <th>CTR</th>
                <th>Posição</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row, index) => (
                <tr key={index}>
                  <td className="oseo-term">{label(row)}</td>
                  <td>{number(row.clicks)}</td>
                  <td>{number(row.impressions)}</td>
                  <td>{percent(row.ctr)}</td>
                  <td>{decimal(row.position)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

function SearchConsolePanel() {
  const [range, setRange] = useState('last_28_days');
  const { data, loading, error, reload } = useOpenSeo<SearchConsole>(`/search-console?range=${range}`);
  const opportunities = useOpenSeo<Opportunities>('/opportunities');

  return (
    <section className="panel oseo-panel">
      <PanelHead title="Search Console" hint="Dados reais do Google: quanto o site aparece, quanto é clicado e para quais buscas." link={data && 'link' in data ? data.link : null} loading={loading} onRefresh={() => { void reload(true); void opportunities.reload(true); }}>
        <div className="range-chips" role="tablist" aria-label="Período">
          {RANGES.map((option) => (
            <button key={option.key} type="button" role="tab" aria-selected={range === option.key} className={`range-chip${range === option.key ? ' is-active' : ''}`} onClick={() => setRange(option.key)}>
              {option.label}
            </button>
          ))}
        </div>
      </PanelHead>
      <div className="panel-body">
        {error ? <div className="form-error">{error}</div> : null}
        {!data && loading ? <Loading /> : null}
        {data && isFailure(data) ? <Unavailable failure={data} /> : null}
        {data?.state === 'ready' ? (
          <>
            <div className="oseo-stats">
              <Stat label="Cliques" value={number(data.totals.clicks)} note={<Sparkline values={data.series.map((row) => row.clicks)} />} />
              <Stat label="Impressões" value={number(data.totals.impressions)} note={<Sparkline values={data.series.map((row) => row.impressions)} />} />
              <Stat label="CTR médio" value={percent(data.totals.ctr)} />
              <Stat label="Posição média" value={decimal(data.totals.position)} tone={data.totals.position === null ? undefined : data.totals.position <= 10 ? 'good' : data.totals.position <= 20 ? 'ok' : 'bad'} />
            </div>
            <div className="oseo-block">
              <h4>Quase na primeira página</h4>
              <p className="hint">Buscas em que o site já aparece entre as posições 4 e 20. Melhorar o título e o texto da página indicada costuma render cliques rápido.</p>
              {data.striking.length === 0 ? <p className="empty-inline">Nenhuma busca nessa faixa no período.</p> : (
                <div className="table-panel">
                  <table className="oseo-table">
                    <thead>
                      <tr>
                        <th>Busca</th>
                        <th>Página</th>
                        <th>Impressões</th>
                        <th>Posição</th>
                      </tr>
                    </thead>
                    <tbody>
                      {data.striking.map((row, index) => (
                        <tr key={index}>
                          <td className="oseo-term">{row.query}</td>
                          <td className="oseo-term">{row.page ? <a href={row.page} target="_blank" rel="noreferrer">{pathOf(row.page)}</a> : EMPTY}</td>
                          <td>{number(row.impressions)}</td>
                          <td>{decimal(row.position)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
            <div className="oseo-split">
              <PerformanceTable title="Principais buscas" rows={data.queries} label={(row) => row.query} empty="Sem buscas no período." />
              <PerformanceTable title="Principais páginas" rows={data.pages} label={(row) => <a href={row.page} target="_blank" rel="noreferrer">{pathOf(row.page)}</a>} empty="Sem páginas no período." />
            </div>
          </>
        ) : null}
        <div className="oseo-block">
          <h4>Oportunidades com Google Analytics</h4>
          <p className="hint">Páginas em posição 4-20 cruzadas com visitas e conversões do GA4, ordenadas pelo potencial.</p>
          {opportunities.data && isFailure(opportunities.data) ? <Unavailable failure={opportunities.data} /> : null}
          {opportunities.data?.state === 'ready' ? (
            opportunities.data.rows.length === 0 ? <p className="empty-inline">Nenhuma oportunidade no período.</p> : (
              <div className="table-panel">
                <table className="oseo-table">
                  <thead>
                    <tr>
                      <th>Página</th>
                      <th>Potencial</th>
                      <th>Posição</th>
                      <th>Impressões</th>
                      <th>Sessões</th>
                      <th>Conversões</th>
                    </tr>
                  </thead>
                  <tbody>
                    {opportunities.data.rows.map((row) => (
                      <tr key={row.url}>
                        <td className="oseo-term"><a href={row.url} target="_blank" rel="noreferrer">{pathOf(row.url)}</a></td>
                        <td>{decimal(row.score)}</td>
                        <td>{decimal(row.position)}</td>
                        <td>{number(row.impressions)}</td>
                        <td>{number(row.sessions)}</td>
                        <td>{decimal(row.conversions)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )
          ) : null}
        </div>
      </div>
    </section>
  );
}

function KeywordMetrics({ row }: { row: KeywordRow }) {
  return (
    <>
      <td>{number(row.volume)}</td>
      <td><span className={`oseo-kd ${row.difficulty === null ? '' : row.difficulty <= 40 ? 'is-good' : row.difficulty <= 70 ? 'is-ok' : 'is-bad'}`}>{row.difficulty ?? EMPTY}</span></td>
      <td>{row.intent ?? EMPTY}</td>
      <td>{row.cpc === null ? EMPTY : `R$ ${row.cpc.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`}</td>
    </>
  );
}

function KeywordsPanel({ canEdit, paid }: { canEdit: boolean; paid: boolean }) {
  const { data, setData, loading, error, reload } = useOpenSeo<SavedKeywords>('/keywords');
  const [seed, setSeed] = useState('');
  const [results, setResults] = useState<KeywordRow[] | null>(null);
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [confirmSeed, setConfirmSeed] = useState(false);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState('');
  const [actionError, setActionError] = useState('');

  async function research() {
    setBusy(true);
    setActionError('');
    try {
      const response = await api<{ rows: KeywordRow[] }>('/seo/openseo/keywords/research', { method: 'POST', body: JSON.stringify({ seed: seed.trim() }) });
      setResults(response.rows);
      setSelected(new Set());
      setConfirmSeed(false);
    } catch (err) {
      setActionError(firstError(err));
      setConfirmSeed(false);
    } finally {
      setBusy(false);
    }
  }

  async function save() {
    if (!results) {
      return;
    }
    setBusy(true);
    setActionError('');
    try {
      const rows = results.filter((row) => selected.has(row.keyword));
      setData(await api<SavedKeywords>('/seo/openseo/keywords', { method: 'POST', body: JSON.stringify({ keywords: rows }) }));
      setMessage(`${rows.length} ${rows.length === 1 ? 'palavra salva' : 'palavras salvas'} no projeto.`);
      setSelected(new Set());
    } catch (err) {
      setActionError(firstError(err));
    } finally {
      setBusy(false);
    }
  }

  async function remove(id: string) {
    setActionError('');
    try {
      setData(await api<SavedKeywords>('/seo/openseo/keywords', { method: 'DELETE', body: JSON.stringify({ ids: [id] }) }));
    } catch (err) {
      setActionError(firstError(err));
    }
  }

  const toggle = (keyword: string) => setSelected((current) => {
    const next = new Set(current);
    if (next.has(keyword)) {
      next.delete(keyword);
    } else {
      next.add(keyword);
    }
    return next;
  });

  return (
    <section className="panel oseo-panel">
      <PanelHead title="Palavras-chave" hint={paid ? 'Pesquise ideias a partir de um termo e salve as que interessam no projeto do OpenSEO.' : 'Palavras salvas no projeto do OpenSEO.'} link={data && 'link' in data ? data.link : null} loading={loading} onRefresh={() => void reload(true)} />
      <div className="panel-body">
        {error ? <div className="form-error">{error}</div> : null}
        {actionError ? <div className="form-error">{actionError}</div> : null}
        {message ? <div className="form-success">{message}</div> : null}
        {canEdit && paid ? (
          <form className="oseo-search" onSubmit={(event) => { event.preventDefault(); if (seed.trim()) { setConfirmSeed(true); } }}>
            <input value={seed} onChange={(event) => setSeed(event.target.value)} placeholder="Ex.: criação de sites em Curitiba" maxLength={120} aria-label="Termo para pesquisar ideias" />
            <button className="btn btn-primary" type="submit" disabled={busy || !seed.trim()}>{busy && !results ? 'Pesquisando...' : 'Pesquisar ideias'}</button>
          </form>
        ) : null}
        {results ? (
          <div className="oseo-block">
            <div className="oseo-block-head">
              <h4>Ideias para "{seed}"</h4>
              <div className="oseo-head-actions">
                <button className="btn btn-secondary btn-sm" type="button" onClick={() => setResults(null)}>Fechar</button>
                <button className="btn btn-primary btn-sm" type="button" disabled={busy || selected.size === 0} onClick={() => void save()}>Salvar {selected.size || ''}</button>
              </div>
            </div>
            {results.length === 0 ? <p className="empty-inline">Nenhuma ideia encontrada para esse termo.</p> : (
              <div className="table-panel oseo-scroll">
                <table className="oseo-table">
                  <thead>
                    <tr>
                      <th aria-label="Selecionar" />
                      <th>Palavra-chave</th>
                      <th>Buscas/mês</th>
                      <th>Dificuldade</th>
                      <th>Intenção</th>
                      <th>CPC</th>
                    </tr>
                  </thead>
                  <tbody>
                    {results.map((row) => (
                      <tr key={row.keyword} className="is-clickable" onClick={() => toggle(row.keyword)}>
                        <td><input type="checkbox" checked={selected.has(row.keyword)} onChange={() => toggle(row.keyword)} onClick={(event) => event.stopPropagation()} aria-label={`Selecionar ${row.keyword}`} /></td>
                        <td className="oseo-term">{row.keyword}</td>
                        <KeywordMetrics row={row} />
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        ) : null}
        <div className="oseo-block">
          <h4>Salvas no projeto</h4>
          {!data && loading ? <Loading /> : null}
          {data && isFailure(data) ? <Unavailable failure={data} /> : null}
          {data?.state === 'ready' ? (
            data.rows.length === 0 ? <p className="empty-inline">{paid ? 'Nenhuma palavra salva. Pesquise um termo acima e salve as ideias que fizerem sentido.' : 'Nenhuma palavra salva no projeto do OpenSEO.'}</p> : (
              <div className="table-panel">
                <table className="oseo-table">
                  <thead>
                    <tr>
                      <th>Palavra-chave</th>
                      <th>Buscas/mês</th>
                      <th>Dificuldade</th>
                      <th>Intenção</th>
                      <th>CPC</th>
                      <th aria-label="Ações" />
                    </tr>
                  </thead>
                  <tbody>
                    {data.rows.map((row) => (
                      <tr key={row.id}>
                        <td className="oseo-term">{row.keyword}{row.tags.length ? <span className="oseo-tags">{row.tags.join(', ')}</span> : null}</td>
                        <KeywordMetrics row={row} />
                        <td>
                          {canEdit ? (
                            <div className="row-actions">
                              <button className="icon-btn danger" type="button" title="Remover" aria-label={`Remover ${row.keyword}`} onClick={() => void remove(row.id)}><Trash2 size={13} /></button>
                            </div>
                          ) : null}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )
          ) : null}
        </div>
      </div>
      {confirmSeed ? (
        <CostDialog
          title="Pesquisar ideias"
          busy={busy}
          confirmLabel="Pesquisar"
          onConfirm={() => void research()}
          onClose={() => setConfirmSeed(false)}
          message={<p>A pesquisa de "{seed.trim()}" consulta o DataForSEO e gasta cerca de 54 créditos. Salvar as ideias depois não custa nada.</p>}
        />
      ) : null}
    </section>
  );
}

function IndexPanel({ urls }: { urls: string[] }) {
  const [selected, setSelected] = useState<string[]>(() => urls.slice(0, 10));
  const [result, setResult] = useState<Inspection | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  async function inspect() {
    setBusy(true);
    setError('');
    try {
      setResult(await api<Inspection>('/seo/openseo/index', { method: 'POST', body: JSON.stringify({ urls: selected }) }));
    } catch (err) {
      setError(firstError(err));
    } finally {
      setBusy(false);
    }
  }

  const toggle = (url: string) => setSelected((current) => (current.includes(url) ? current.filter((item) => item !== url) : current.length >= 10 ? current : [...current, url]));

  return (
    <section className="panel oseo-panel">
      <PanelHead title="Indexação no Google" hint="Pergunta ao Search Console se cada URL está no índice do Google, quando foi rastreada e qual canonical o Google escolheu. Até 10 por vez, sem custo." link={result && 'link' in result ? result.link : null}>
        <button className="btn btn-primary btn-sm" type="button" disabled={busy || selected.length === 0} onClick={() => void inspect()}>{busy ? 'Verificando...' : `Verificar ${selected.length}`}</button>
      </PanelHead>
      <div className="panel-body">
        {error ? <div className="form-error">{error}</div> : null}
        {result && isFailure(result) ? <Unavailable failure={result} /> : null}
        {result?.state === 'ready' ? (
          <div className="table-panel oseo-block">
            <table className="oseo-table">
              <thead>
                <tr>
                  <th>URL</th>
                  <th>Situação</th>
                  <th>Cobertura</th>
                  <th>Último rastreio</th>
                  <th>Canonical do Google</th>
                </tr>
              </thead>
              <tbody>
                {result.rows.map((row) => (
                  <tr key={row.url}>
                    <td className="oseo-term"><a href={row.url} target="_blank" rel="noreferrer">{pathOf(row.url)}</a></td>
                    <td>{row.error ? <span className="oseo-sev is-critical">Erro</span> : row.indexed === null ? EMPTY : <span className={`oseo-sev ${row.indexed ? 'is-good' : 'is-warning'}`}>{row.indexed ? 'Indexada' : 'Fora do índice'}</span>}</td>
                    <td>{row.error ?? row.coverage ?? EMPTY}</td>
                    <td>{shortDate(row.last_crawl)}</td>
                    <td className="oseo-term">{row.google_canonical ? (row.user_canonical && row.google_canonical !== row.user_canonical ? <span className="oseo-warn" title={`Declarado: ${row.user_canonical}`}>{pathOf(row.google_canonical)} (diferente)</span> : pathOf(row.google_canonical)) : EMPTY}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : null}
        <div className="oseo-block">
          <div className="oseo-block-head">
            <h4>URLs do sitemap</h4>
            <span className="hint">{selected.length}/10 selecionadas</span>
          </div>
          {urls.length === 0 ? <p className="empty-inline">O sitemap está vazio.</p> : (
            <ul className="oseo-url-list">
              {urls.map((url) => (
                <li key={url}>
                  <label className="check">
                    <input type="checkbox" checked={selected.includes(url)} disabled={!selected.includes(url) && selected.length >= 10} onChange={() => toggle(url)} />
                    {pathOf(url)}
                  </label>
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>
    </section>
  );
}

function DomainPanel({ canEdit }: { canEdit: boolean }) {
  const { data, setData, loading, error } = useOpenSeo<Domain>('/domain');
  const [confirm, setConfirm] = useState(false);
  const [busy, setBusy] = useState(false);
  const [actionError, setActionError] = useState('');

  async function fetchDomain() {
    setBusy(true);
    setActionError('');
    try {
      setData(await api<Domain>('/seo/openseo/domain', { method: 'POST' }));
    } catch (err) {
      setActionError(firstError(err));
    } finally {
      setBusy(false);
      setConfirm(false);
    }
  }

  const domain = data && 'domain' in data ? data.domain : '';

  return (
    <section className="panel oseo-panel">
      <PanelHead title="Visão do domínio" hint="Estimativa do DataForSEO para o domínio inteiro: tráfego orgânico, palavras em que aparece e links recebidos." link={data?.state === 'ready' ? data.link : null}>
        {canEdit && data && !isFailure(data) ? <button className="btn btn-primary btn-sm" type="button" disabled={busy} onClick={() => setConfirm(true)}>{data.state === 'ready' ? 'Atualizar dados' : 'Buscar dados'}</button> : null}
      </PanelHead>
      <div className="panel-body">
        {error ? <div className="form-error">{error}</div> : null}
        {actionError ? <div className="form-error">{actionError}</div> : null}
        {!data && loading ? <Loading /> : null}
        {data && isFailure(data) ? <Unavailable failure={data} /> : null}
        {data?.state === 'idle' ? <p className="empty-inline">Ainda sem dados de {data.domain}. A consulta é paga e fica guardada por 12 horas.</p> : null}
        {data?.state === 'ready' ? (
          <>
            <div className="oseo-stats">
              <Stat label="Tráfego orgânico/mês" value={number(data.metrics.traffic)} note="estimado" />
              <Stat label="Palavras ranqueadas" value={number(data.metrics.keywords)} />
              <Stat label="Backlinks" value={number(data.metrics.backlinks)} />
              <Stat label="Domínios de referência" value={number(data.metrics.referring_domains)} />
            </div>
            {data.top_keywords.length > 0 ? (
              <div className="oseo-block">
                <h4>Principais palavras do domínio</h4>
                <div className="table-panel">
                  <table className="oseo-table">
                    <thead>
                      <tr>
                        <th>Palavra-chave</th>
                        <th>Posição</th>
                        <th>Buscas/mês</th>
                        <th>Página</th>
                      </tr>
                    </thead>
                    <tbody>
                      {data.top_keywords.map((row) => (
                        <tr key={row.keyword}>
                          <td className="oseo-term">{row.keyword}</td>
                          <td>{row.position ?? EMPTY}</td>
                          <td>{number(row.volume)}</td>
                          <td className="oseo-term">{row.url ? <a href={row.url} target="_blank" rel="noreferrer">{pathOf(row.url)}</a> : EMPTY}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            ) : null}
            <p className="hint">Dados de {shortDate(data.fetched_at)}.</p>
          </>
        ) : null}
      </div>
      {confirm ? (
        <CostDialog
          title="Visão do domínio"
          busy={busy}
          confirmLabel="Buscar"
          onConfirm={() => void fetchDomain()}
          onClose={() => setConfirm(false)}
          message={<p>A consulta de {domain} no DataForSEO gasta de 100 a 300 créditos. O resultado fica guardado por 12 horas.</p>}
        />
      ) : null}
    </section>
  );
}

function RateStat({ label, rate }: { label: string; rate: Rate }) {
  const change = rate.change;
  return (
    <Stat
      label={label}
      value={points(rate.current)}
      note={change === null ? 'sem comparação' : `${change > 0 ? '+' : ''}${change.toLocaleString('pt-BR', { maximumFractionDigits: 1 })} p.p.`}
      tone={change === null ? undefined : change > 0 ? 'good' : change < 0 ? 'bad' : undefined}
    />
  );
}

function AiPanel() {
  const [days, setDays] = useState(28);
  const { data, loading, error, reload } = useOpenSeo<AiVisibility>(`/ai?days=${days}`);

  return (
    <section className="panel oseo-panel">
      <PanelHead title="Visibilidade em IA" hint="Com que frequência ChatGPT e outras IAs citam a Rubia Rocha ao responder perguntas do seu mercado." link={data && 'link' in data ? data.link : null} loading={loading} onRefresh={() => void reload(true)}>
        <div className="range-chips" role="tablist" aria-label="Período">
          {[7, 28, 90].map((value) => (
            <button key={value} type="button" role="tab" aria-selected={days === value} className={`range-chip${days === value ? ' is-active' : ''}`} onClick={() => setDays(value)}>{value} dias</button>
          ))}
        </div>
      </PanelHead>
      <div className="panel-body">
        {error ? <div className="form-error">{error}</div> : null}
        {!data && loading ? <Loading /> : null}
        {data && isFailure(data) ? <Unavailable failure={data} /> : null}
        {data?.state === 'none' ? <p className="empty-inline">O acompanhamento em IA não está configurado. Monte as perguntas no OpenSEO.</p> : null}
        {data?.state === 'ready' ? (
          <>
            {data.comparison && COMPARISON[data.comparison] ? <p className="hint">{COMPARISON[data.comparison]}</p> : null}
            <div className="oseo-stats">
              <RateStat label="Respostas que citam a marca" rate={data.mentions} />
              <RateStat label="Respostas que linkam o site" rate={data.citations} />
              <Stat label="Respostas coletadas" value={`${number(data.coverage.answered)}/${number(data.coverage.expected)}`} note={data.coverage.failed ? `${data.coverage.failed} falharam` : undefined} tone={data.coverage.expected && data.coverage.answered / data.coverage.expected < 0.9 ? 'bad' : undefined} />
              <Stat label="Perguntas monitoradas" value={number(data.tracker.prompts)} note={data.tracker.schedule ? `checagem ${SCHEDULE[data.tracker.schedule] ?? data.tracker.schedule}, próxima ${shortDate(data.tracker.next_check_at)}` : undefined} />
            </div>
            {data.tracker.topics.length > 0 ? (
              <div className="oseo-block">
                <h4>Temas</h4>
                <div className="oseo-chips">{data.tracker.topics.map((topic) => <span key={topic}>{topic}</span>)}</div>
              </div>
            ) : null}
            {data.runs.length > 0 ? (
              <div className="oseo-block">
                <h4>Coletas</h4>
                <div className="table-panel">
                  <table className="oseo-table">
                    <thead>
                      <tr>
                        <th>Data</th>
                        <th>Citam a marca</th>
                        <th>Linkam o site</th>
                        <th>Respostas</th>
                      </tr>
                    </thead>
                    <tbody>
                      {[...data.runs].reverse().map((run, index) => (
                        <tr key={`${run.created_at}-${index}`}>
                          <td>{shortDate(run.created_at)}</td>
                          <td>{points(run.mention_rate)}</td>
                          <td>{points(run.citation_rate)}</td>
                          <td>{run.answered}/{run.expected}{run.failed ? <span className="oseo-warn"> ({run.failed} falharam)</span> : null}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            ) : null}
          </>
        ) : null}
      </div>
    </section>
  );
}

export function SeoPerformance({ sitemapPaths, canEdit, onOpenIntegrations }: { sitemapPaths: string[]; canEdit: boolean; onOpenIntegrations: () => void }) {
  const overview = useOpenSeo<Overview>('');
  const [panel, setPanel] = useState<PanelKey>(() => {
    const stored = new URLSearchParams(window.location.search).get('painel');
    return PANELS.some((item) => item.key === stored) ? stored as PanelKey : 'audit';
  });

  useEffect(() => {
    const url = new URL(window.location.href);
    url.searchParams.set('painel', panel);
    window.history.replaceState(window.history.state, '', url);
  }, [panel]);

  const info = overview.data;
  const panels = useMemo(() => PANELS.filter((item) => item.key !== 'domain' || info?.paid), [info?.paid]);
  const current = panels.find((item) => item.key === panel) ?? panels[0];
  const siteUrl = info?.site_url ?? '';
  const sitemapUrls = useMemo(() => [...new Set(sitemapPaths.map((path) => `${siteUrl}${path.startsWith('/') ? path : `/${path}`}`))], [sitemapPaths, siteUrl]);

  if (!info && overview.loading) {
    return <Loading />;
  }

  if (overview.error || !info || !info.configured) {
    return (
      <div className="panel">
        <div className="panel-body oseo-setup">
          <h3>Integrações de SEO</h3>
          <p>{overview.error || 'OpenSEO não configurado. Preencha a URL do MCP e o ID do projeto na aba Integrações.'}</p>
          <div className="toolbar">
            <button className="btn btn-primary btn-sm" type="button" onClick={onOpenIntegrations}>Abrir Integrações</button>
            <button className="btn btn-secondary btn-sm" type="button" onClick={() => void overview.reload(true)}><RefreshCw size={12} /> Tentar de novo</button>
          </div>
        </div>
      </div>
    );
  }

  const openSeoMissing = !info.configured || (info.project === null && info.message !== null);

  return (
    <div className="oseo">
      <div className="oseo-bar">
        <div className="oseo-project">
          <strong>{info.project?.name ?? (info.configured ? 'Projeto OpenSEO' : 'OpenSEO desligado')}</strong>
          <span>{info.project?.domain ?? ''}{info.paid ? '' : `${info.project?.domain ? ' · ' : ''}sem consultas pagas`}</span>
        </div>
        <nav className="oseo-nav" data-tour="seo-openseo-nav" aria-label="Blocos de desempenho">
          {panels.map((item) => (
            <button key={item.key} type="button" className={item.key === current.key ? 'is-on' : ''} aria-current={item.key === current.key ? 'page' : undefined} title={item.hint} onClick={() => setPanel(item.key)}>
              {item.label}
            </button>
          ))}
        </nav>
      </div>
      <div key={current.key}>
        {openSeoMissing ? (
          <section className="panel oseo-panel">
            <div className="panel-body">
              <Unavailable failure={{ state: 'unconfigured', message: info.message ?? 'OpenSEO indisponível.' }} />
              <button className="btn btn-secondary btn-sm" type="button" onClick={onOpenIntegrations}>Abrir Integrações</button>
            </div>
          </section>
        ) : (
          <>
            {current.key === 'audit' ? <AuditPanel canEdit={canEdit} /> : null}
            {current.key === 'search' ? <SearchConsolePanel /> : null}
            {current.key === 'keywords' ? <KeywordsPanel canEdit={canEdit} paid={info.paid} /> : null}
            {current.key === 'index' ? <IndexPanel key={siteUrl} urls={sitemapUrls} /> : null}
            {current.key === 'domain' ? <DomainPanel canEdit={canEdit} /> : null}
            {current.key === 'ai' ? <AiPanel /> : null}
          </>
        )}
      </div>
    </div>
  );
}
