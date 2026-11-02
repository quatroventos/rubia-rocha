import { useEffect, useId, useRef, useState } from 'react';
import { ChevronDown, ChevronRight, ExternalLink, Gauge, Monitor, RefreshCw, ScanSearch, Smartphone, Sparkles, TrendingUp, Undo2 } from 'lucide-react';
import { api, firstError } from '../api';
import { getPath } from '../lib';
import { formatMs, ScoreRing, scoreTone } from './SiteHealth';
import { AI_UNAVAILABLE } from './SectionCopy';

export type SeoTarget = { type: 'seo_title' | 'seo_description' | 'section' | 'field'; index?: number; path?: string; name?: string };

export type SeoInput = {
  keyword: string;
  seo_title: string;
  seo_description: string;
  slug: string;
  path: string;
  title?: string;
  summary?: string;
  client?: string;
  cover_alt?: string;
  og_image_alt?: string;
  sections: { type: string; data: Record<string, unknown> }[];
};

type Check = {
  id: string;
  label: string;
  status: 'good' | 'ok' | 'bad';
  message: string;
  weight: number;
  fix: SeoTarget | null;
};

type Analysis = { score: number | null; keyword: string; words: number; rendered?: boolean; rendered_at?: string; checks: Check[] };

type Audit = { id: string; title: string; display: string | null; score: number; category: string };

type StrategyReport = {
  performance: number | null;
  accessibility: number | null;
  best_practices: number | null;
  seo: number | null;
  metrics: { fcp_ms: number | null; lcp_ms: number | null; tbt_ms: number | null; cls: number | null };
  audits?: Audit[];
};

type PageLighthouse = {
  url: string;
  status: 'idle' | 'running' | 'ready' | 'error' | 'unavailable';
  message?: string | null;
  fetched_at: string | null;
  mobile: StrategyReport | null;
  desktop: StrategyReport | null;
};

type MarketKeyword = { keyword: string; volume: number | null; difficulty: number | null; intent: string | null; cpc: number | null };

type MarketReport = {
  status: 'ready' | 'idle' | 'unconfigured';
  keyword: string;
  paid?: boolean;
  message?: string;
  fetched_at?: string;
  metrics?: MarketKeyword | null;
  related?: MarketKeyword[];
  serp?: { position: number; title: string; url: string; description: string }[];
  own_position?: number | null;
  search_console?: { query: string; clicks: number; impressions: number; ctr: number | null; position: number | null }[] | null;
  errors?: Record<string, string>;
};

const MARKET_PARTS: Record<string, string> = {
  metrics: 'Métricas',
  related: 'Variações',
  serp: 'Top 10',
  search_console: 'Search Console',
};

const LIGHTHOUSE_FIXES: Record<string, string> = {
  'meta-description': 'lighthouse:meta-description',
  'document-title': 'lighthouse:document-title',
};

const CATEGORY_LABELS: Record<string, string> = {
  performance: 'Performance',
  accessibility: 'Acessibilidade',
  'best-practices': 'Boas práticas',
  seo: 'SEO',
};

const STATUS_ORDER = { bad: 0, ok: 1, good: 2 };

const EMPTY = 'n/d';

/**
 * Checks abertos com correção, um por alvo: dois checks no mesmo campo (ex.: palavra-chave e tamanho do título)
 * viram uma correção só, a de maior peso.
 */
function fixQueue(checks: Check[]): Check[] {
  const seen = new Set<string>();
  return checks
    .filter((check) => check.fix && check.status !== 'good')
    .sort((a, b) => b.weight - a.weight)
    .filter((check) => {
      const key = JSON.stringify(check.fix);
      if (seen.has(key)) {
        return false;
      }
      seen.add(key);
      return true;
    });
}

function setDeep(value: unknown, keys: string[], next: unknown): unknown {
  if (keys.length === 0) {
    return next;
  }
  const [key, ...rest] = keys;
  if (Array.isArray(value)) {
    const copy = [...value];
    copy[Number(key)] = setDeep(copy[Number(key)], rest, next);
    return copy;
  }
  const record = value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
  return { ...record, [key]: setDeep(record[key], rest, next) };
}

export function readTarget(input: SeoInput, target: SeoTarget): string {
  if (target.type === 'seo_title' || target.type === 'seo_description') {
    return input[target.type] ?? '';
  }
  if (target.type === 'field' && target.name) {
    return String((input as Record<string, unknown>)[target.name] ?? '');
  }
  const section = input.sections[target.index ?? -1];
  const value = section && target.path ? getPath(section.data, target.path) : '';
  return typeof value === 'string' ? value : '';
}

export function writeTarget(input: SeoInput, target: SeoTarget, value: string): SeoInput {
  if (target.type === 'seo_title' || target.type === 'seo_description') {
    return { ...input, [target.type]: value };
  }
  if (target.type === 'field' && target.name) {
    return { ...input, [target.name]: value };
  }
  return {
    ...input,
    sections: input.sections.map((section, index) => (
      index === target.index && target.path ? { ...section, data: setDeep(section.data, target.path.split('.'), value) as Record<string, unknown> } : section
    )),
  };
}

/**
 * Painel lateral de SEO: nota por palavra-chave calculada sobre o rascunho, correções por IA e Lighthouse da versão no ar.
 * `onApply` recebe cada correção para o editor aplicar no formulário; nada é salvo aqui.
 * `aiNote` explica por que a IA está desligada; sem ele, usa a mensagem padrão de IA não configurada.
 * `onScore` repassa a nota para o editor exibir fora do painel.
 * `market` liga os dados de busca do OpenSEO; `onKeyword` troca a palavra-chave por uma variação sugerida.
 */
export function SeoInsights({
  kind,
  id,
  input,
  aiDisabled,
  aiNote,
  lighthouse,
  market = false,
  onApply,
  onScore,
  onKeyword,
}: {
  kind: 'page' | 'case';
  id: number | null;
  input: SeoInput;
  aiDisabled?: boolean;
  aiNote?: string;
  lighthouse: { kind: 'pages' | 'cases'; id: number } | null;
  market?: boolean;
  onApply: (target: SeoTarget, value: string) => void;
  onScore?: (score: number | null) => void;
  onKeyword?: (keyword: string) => void;
}) {
  const [marketVersion, setMarketVersion] = useState(0);
  const [analysis, setAnalysis] = useState<Analysis | null>(null);
  const [analysisError, setAnalysisError] = useState<string | null>(null);
  const [fixing, setFixing] = useState<string | null>(null);
  const [progress, setProgress] = useState<string | null>(null);
  const [fixError, setFixError] = useState<string | null>(null);
  const [history, setHistory] = useState<{ target: SeoTarget; previous: string; applied: string }[]>([]);
  const [rendered, setRendered] = useState<{ analysis: Analysis; input: string } | null>(null);
  const [rendering, setRendering] = useState(false);
  const [renderError, setRenderError] = useState<string | null>(null);
  const [showGood, setShowGood] = useState(false);
  const goodId = useId();
  const inputRef = useRef(input);
  const scoreRef = useRef(onScore);
  const json = JSON.stringify(input);
  inputRef.current = input;
  scoreRef.current = onScore;
  const disabledNote = aiNote ?? AI_UNAVAILABLE;

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void api<Analysis>('/seo/analyze', { method: 'POST', body: JSON.stringify({ kind, id, ...inputRef.current }) })
        .then((result) => {
          setAnalysis(result);
          setAnalysisError(null);
        })
        .catch((error) => setAnalysisError(firstError(error)));
    }, 600);
    return () => window.clearTimeout(timer);
  }, [json, kind, id, marketVersion]);

  const current = rendered && rendered.input === json ? rendered.analysis : analysis;
  const stale = rendered !== null && rendered.input !== json;

  useEffect(() => {
    scoreRef.current?.(current?.score ?? null);
  }, [current?.score]);

  async function analyzeRendered(working: SeoInput = inputRef.current) {
    setRendering(true);
    setRenderError(null);
    try {
      const result = await api<Analysis>('/seo/analyze/rendered', { method: 'POST', body: JSON.stringify({ kind, id, ...working }) });
      setRendered({ analysis: result, input: JSON.stringify(working) });
    } catch (error) {
      setRenderError(firstError(error));
    } finally {
      setRendering(false);
    }
  }

  async function runFix(check: Check, working: SeoInput): Promise<SeoInput> {
    const fromRendered = current?.rendered && check.fix ? { target: check.fix, status: check.status, message: check.message } : {};
    const result = await api<{ target: SeoTarget; value: string }>('/seo/fix', {
      method: 'POST',
      body: JSON.stringify({ kind, id, check: check.id, ...fromRendered, ...working }),
    });
    const previous = readTarget(working, result.target);
    setHistory((items) => [...items, { target: result.target, previous, applied: result.value }]);
    onApply(result.target, result.value);
    return writeTarget(working, result.target, result.value);
  }

  async function fixOne(checkId: string) {
    const check = checks.find((item) => item.id === checkId) ?? { id: checkId, label: '', status: 'bad', message: '', weight: 0, fix: null };
    const reanalyze = Boolean(current?.rendered);
    setFixing(checkId);
    setFixError(null);
    try {
      const working = await runFix(check, inputRef.current);
      if (reanalyze) {
        await analyzeRendered(working);
      }
    } catch (error) {
      setFixError(firstError(error));
    } finally {
      setFixing(null);
    }
  }

  async function fixAll() {
    const reanalyze = Boolean(current?.rendered);
    setFixing('all');
    setFixError(null);
    let working = inputRef.current;
    try {
      for (const [index, check] of queue.entries()) {
        setProgress(`${index + 1}/${queue.length} ${check.label}`);
        working = await runFix(check, working);
      }
      if (reanalyze) {
        setProgress('Analisando a página renderizada');
        await analyzeRendered(working);
      }
    } catch (error) {
      setFixError(firstError(error));
    } finally {
      setFixing(null);
      setProgress(null);
    }
  }

  function undoAll() {
    let working = inputRef.current;
    [...history].reverse().forEach((item) => {
      if (readTarget(working, item.target) !== item.applied) {
        return;
      }
      onApply(item.target, item.previous);
      working = writeTarget(working, item.target, item.previous);
    });
    setHistory([]);
  }

  const checks = [...(current?.checks ?? [])].sort((a, b) => STATUS_ORDER[a.status] - STATUS_ORDER[b.status] || b.weight - a.weight);
  const open = checks.filter((check) => check.status !== 'good');
  const good = checks.filter((check) => check.status === 'good');
  const queue = fixQueue(checks);
  const busy = fixing !== null || rendering;
  const renderedAt = current?.rendered_at ? new Date(current.rendered_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : null;

  return (
    <div className="seo-insights">
      <div className="seo-score">
        <ScoreRing score={current?.score ?? null} label="Nota SEO" />
        <div>
          <strong>{current?.keyword ? `"${current.keyword}"` : 'Sem palavra-chave'}</strong>
          <span>
            {current
              ? current.rendered
                ? `${current.words} palavras · página renderizada às ${renderedAt}`
                : `${current.words} palavras · prévia do editor`
              : 'Analisando...'}
          </span>
          <button
            className="btn btn-secondary seo-fix-all"
            type="button"
            disabled={busy}
            title="Renderiza a página com o conteúdo atual do editor e avalia o HTML final"
            onClick={() => void analyzeRendered()}
          >
            <ScanSearch size={13} /> {rendering ? 'Analisando...' : 'Analisar'}
          </button>
          {queue.length > 0 ? (
            <button
              className="btn btn-secondary seo-fix-all"
              type="button"
              disabled={aiDisabled || busy}
              title={aiDisabled ? disabledNote : undefined}
              onClick={() => void fixAll()}
            >
              <Sparkles size={13} /> {fixing === 'all' ? 'Corrigindo...' : `Corrigir ${queue.length} com IA`}
            </button>
          ) : null}
          {history.length > 0 && !busy ? (
            <button className="seo-undo" type="button" onClick={undoAll}>
              <Undo2 size={12} /> Desfazer correções ({history.length})
            </button>
          ) : null}
        </div>
      </div>
      {aiDisabled && queue.length > 0 ? <p className="hint">{disabledNote}</p> : null}
      {progress ? <p className="seo-progress">Corrigindo {progress}</p> : null}
      {fixError ? <p className="field-ai-error">{fixError}</p> : null}
      {analysisError ? <p className="field-ai-error">{analysisError}</p> : null}
      {renderError ? <p className="field-ai-error">{renderError}</p> : null}
      {stale && !busy ? <p className="hint">O conteúdo mudou depois da última análise. A nota abaixo é a prévia do editor; clique em Analisar para conferir a página renderizada.</p> : null}

      <ul className="seo-checks">
        {open.map((check) => (
          <li key={check.id} className={`is-${check.status}`}>
            <i aria-hidden="true" />
            <div>
              <strong>{check.label}</strong>
              <p>{check.message}</p>
            </div>
            {check.fix ? (
              <button
                className={`field-ai-btn${fixing === check.id ? ' is-running' : ''}`}
                type="button"
                title={aiDisabled ? disabledNote : 'Corrigir com IA'}
                aria-label={`Corrigir com IA: ${check.label}`}
                disabled={aiDisabled || busy}
                onClick={() => void fixOne(check.id)}
              >
                <Sparkles size={12} />
              </button>
            ) : null}
          </li>
        ))}
      </ul>
      {good.length > 0 ? (
        <>
          <button
            className="seo-good-toggle"
            type="button"
            aria-expanded={showGood}
            aria-controls={goodId}
            onClick={() => setShowGood(!showGood)}
          >
            {showGood ? <ChevronDown size={12} /> : <ChevronRight size={12} />} {good.length} {good.length === 1 ? 'item certo' : 'itens certos'}
          </button>
          {showGood ? (
            <ul className="seo-checks" id={goodId}>
              {good.map((check) => (
                <li key={check.id} className="is-good">
                  <i aria-hidden="true" />
                  <div>
                    <strong>{check.label}</strong>
                    <p>{check.message}</p>
                  </div>
                </li>
              ))}
            </ul>
          ) : null}
        </>
      ) : null}

      {market ? (
        <MarketPanel
          kind={kind}
          id={id}
          keyword={input.keyword}
          path={input.path || null}
          onKeyword={onKeyword}
          onFetched={() => setMarketVersion((version) => version + 1)}
        />
      ) : null}

      {lighthouse ? (
        <LighthousePanel target={lighthouse} aiDisabled={aiDisabled} aiNote={disabledNote} busy={busy} onFix={(checkId) => void fixOne(checkId)} />
      ) : (
        <p className="hint">Salve para medir com o Lighthouse.</p>
      )}
    </div>
  );
}

/**
 * Dados de busca para a palavra-chave. Ler o cache é grátis; "Buscar dados" só gasta créditos com consultas pagas liberadas.
 */
function MarketPanel({
  kind,
  id,
  keyword,
  path,
  onKeyword,
  onFetched,
}: {
  kind: 'page' | 'case';
  id: number | null;
  keyword: string;
  path: string | null;
  onKeyword?: (keyword: string) => void;
  onFetched: () => void;
}) {
  const [report, setReport] = useState<MarketReport | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [serpOpen, setSerpOpen] = useState(false);
  const serpId = useId();
  const term = keyword.trim();
  const params = new URLSearchParams({ kind, keyword: term, ...(id ? { id: String(id) } : {}), ...(path ? { path } : {}) }).toString();

  useEffect(() => {
    setError(null);
    if (term === '') {
      setReport(null);
      return;
    }
    const timer = window.setTimeout(() => {
      void api<MarketReport>(`/seo/market?${params}`).then(setReport).catch((err) => setError(firstError(err)));
    }, 600);
    return () => window.clearTimeout(timer);
  }, [params, term]);

  async function fetchNow() {
    setLoading(true);
    setError(null);
    try {
      setReport(await api<MarketReport>('/seo/market', { method: 'POST', body: JSON.stringify({ kind, id, keyword: term, path }) }));
      onFetched();
    } catch (err) {
      setError(firstError(err));
    } finally {
      setLoading(false);
    }
  }

  const ready = report?.status === 'ready';
  const metrics = ready ? report.metrics ?? null : null;
  const related = ready ? report.related ?? [] : [];
  const queries = ready ? report.search_console ?? null : null;
  const serp = ready ? report.serp ?? [] : [];
  const partErrors = Object.entries(report?.errors ?? {});
  const paid = report?.paid ?? false;

  return (
    <div className="seo-market">
      <div className="seo-lighthouse-head">
        <span><TrendingUp size={13} /> Dados de busca</span>
        {ready ? (
          <button className="icon-btn" type="button" title={paid ? 'Buscar de novo (gasta créditos)' : 'Buscar de novo'} aria-label="Buscar dados de novo" disabled={loading} onClick={() => void fetchNow()}>
            <RefreshCw size={13} className={loading ? 'is-spinning' : undefined} />
          </button>
        ) : null}
      </div>

      {term === '' ? <p className="hint">Defina a palavra-chave para ver demanda, concorrentes e buscas reais.</p> : null}
      {report?.status === 'unconfigured' ? <p className="hint">{report.message}</p> : null}
      {error ? <p className="field-ai-error">{error}</p> : null}
      {loading ? <p className="hint">Consultando o Google. Leva até um minuto.</p> : null}

      {report?.status === 'idle' && term !== '' ? (
        <div className="seo-lh-empty">
          <p className="hint">{paid
            ? 'Volume, dificuldade, top 10 do Google e buscas do Search Console. Gasta créditos do DataForSEO e fica guardado por 30 dias.'
            : 'Buscas do Search Console, sem custo. Fica guardado por 30 dias.'}</p>
          <button className="btn btn-secondary" type="button" disabled={loading} onClick={() => void fetchNow()}>
            <TrendingUp size={13} /> {loading ? 'Buscando...' : 'Buscar dados'}
          </button>
        </div>
      ) : null}

      {ready ? (
        <>
          {metrics || paid ? (
            <dl className="seo-market-stats">
              <div><dt>Buscas/mês</dt><dd>{metrics?.volume ?? EMPTY}</dd></div>
              <div className={`is-${metrics?.difficulty == null ? 'none' : metrics.difficulty <= 40 ? 'green' : metrics.difficulty <= 70 ? 'gold' : 'coral'}`}>
                <dt>Dificuldade</dt><dd>{metrics?.difficulty ?? EMPTY}</dd>
              </div>
              <div><dt>Intenção</dt><dd>{metrics?.intent ?? EMPTY}</dd></div>
            </dl>
          ) : null}
          {serp.length > 0 ? (
            <p className="hint">
              {report.own_position ? `O site está em ${report.own_position}º no Google para esta palavra-chave.` : `O site não aparece no top ${serp.length} do Google para esta palavra-chave.`}
            </p>
          ) : null}

          {related.length > 0 ? (
            <div className="seo-market-block">
              <strong>Variações com demanda</strong>
              <ul className="seo-market-list">
                {related.map((row) => (
                  <li key={row.keyword}>
                    <span>{row.keyword}</span>
                    <em>{row.volume ?? EMPTY}/mês{row.difficulty != null ? ` · dif. ${row.difficulty}` : ''}</em>
                    {onKeyword ? (
                      <button type="button" className="seo-market-use" onClick={() => onKeyword(row.keyword)} aria-label={`Usar "${row.keyword}" como palavra-chave`}>
                        Usar
                      </button>
                    ) : null}
                  </li>
                ))}
              </ul>
            </div>
          ) : null}

          {queries !== null ? (
            <div className="seo-market-block">
              <strong>Buscas que trazem a página (Search Console, 28 dias)</strong>
              {queries.length > 0 ? (
                <ul className="seo-market-list">
                  {queries.map((row) => (
                    <li key={row.query}>
                      <span>{row.query}</span>
                      <em>{row.impressions} impr.{row.position != null ? ` · pos. ${row.position}` : ''}</em>
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="hint">Nenhuma impressão registrada para esta URL.</p>
              )}
            </div>
          ) : null}

          {serp.length > 0 ? (
            <div className="seo-market-block">
              <button className="seo-good-toggle" type="button" aria-expanded={serpOpen} aria-controls={serpId} onClick={() => setSerpOpen(!serpOpen)}>
                {serpOpen ? <ChevronDown size={12} /> : <ChevronRight size={12} />} Top {serp.length} no Google
              </button>
              {serpOpen ? (
                <ol className="seo-serp" id={serpId}>
                  {serp.map((row) => (
                    <li key={`${row.position}-${row.url}`} data-position={row.position}>
                      <a href={row.url} target="_blank" rel="noreferrer">{row.title}</a>
                      <span>{row.url.replace(/^https?:\/\/(www\.)?/, '')}</span>
                    </li>
                  ))}
                </ol>
              ) : null}
            </div>
          ) : null}

          {partErrors.length > 0 ? (
            <p className="hint">
              {partErrors.map(([part, message]) => `${MARKET_PARTS[part] ?? part}: ${message}`).join(' ')}
            </p>
          ) : null}
          <p className="hint">
            {report.fetched_at ? `Dados de ${new Date(report.fetched_at).toLocaleDateString('pt-BR')}. ` : ''}
            A nota e a IA de SEO usam estes dados.
          </p>
        </>
      ) : null}
    </div>
  );
}

function LighthousePanel({
  target,
  aiDisabled,
  aiNote,
  busy,
  onFix,
}: {
  target: { kind: 'pages' | 'cases'; id: number };
  aiDisabled?: boolean;
  aiNote: string;
  busy: boolean;
  onFix: (checkId: string) => void;
}) {
  const [report, setReport] = useState<PageLighthouse | null>(null);
  const [strategy, setStrategy] = useState<'mobile' | 'desktop'>('mobile');
  const [error, setError] = useState<string | null>(null);
  const endpoint = `/seo/lighthouse/${target.kind}/${target.id}`;

  useEffect(() => {
    void api<PageLighthouse>(endpoint).then(setReport).catch((err) => setError(firstError(err)));
  }, [endpoint]);

  useEffect(() => {
    if (report?.status !== 'running') {
      return;
    }
    const timer = window.setTimeout(() => {
      void api<PageLighthouse>(endpoint).then(setReport).catch(() => undefined);
    }, 5000);
    return () => window.clearTimeout(timer);
  }, [report, endpoint]);

  function measure() {
    setError(null);
    void api<PageLighthouse>(endpoint, { method: 'POST' }).then(setReport).catch((err) => setError(firstError(err)));
  }

  const data = report?.[strategy] ?? null;
  const running = report?.status === 'running';
  const measured = Boolean(report?.mobile || report?.desktop);
  const unavailable = report?.status === 'unavailable';
  const scores: { key: keyof Pick<StrategyReport, 'performance' | 'accessibility' | 'best_practices' | 'seo'>; label: string }[] = [
    { key: 'performance', label: 'Desempenho' },
    { key: 'accessibility', label: 'Acessibilidade' },
    { key: 'best_practices', label: 'Boas práticas' },
    { key: 'seo', label: 'SEO' },
  ];

  return (
    <div className="seo-lighthouse">
      <div className="seo-lighthouse-head">
        <span><Gauge size={13} /> Lighthouse</span>
        {measured ? (
          <>
            <div className="seo-strategy">
              <button type="button" className={strategy === 'mobile' ? 'is-on' : undefined} aria-pressed={strategy === 'mobile'} onClick={() => setStrategy('mobile')} aria-label="Mobile" title="Mobile"><Smartphone size={12} /></button>
              <button type="button" className={strategy === 'desktop' ? 'is-on' : undefined} aria-pressed={strategy === 'desktop'} onClick={() => setStrategy('desktop')} aria-label="Desktop" title="Desktop"><Monitor size={12} /></button>
            </div>
            <button className="icon-btn" type="button" title="Medir de novo" aria-label="Medir de novo" disabled={running || unavailable} onClick={measure}>
              <RefreshCw size={13} className={running ? 'is-spinning' : undefined} />
            </button>
          </>
        ) : null}
      </div>

      {report?.message ? <p className="hint">{report.message}</p> : null}
      {error ? <p className="field-ai-error">{error}</p> : null}
      {running ? <p className="hint">Medindo a versão publicada. Leva até um minuto.</p> : null}
      {!measured && !unavailable && report ? (
        <div className="seo-lh-empty">
          {!running ? <p className="hint">Ainda não medida.</p> : null}
          <button className="btn btn-secondary" type="button" disabled={running} onClick={measure}>
            <RefreshCw size={13} className={running ? 'is-spinning' : undefined} /> {running ? 'Medindo...' : 'Medir agora'}
          </button>
        </div>
      ) : null}

      {data ? (
        <>
          <div className="seo-lh-scores">
            {scores.map((score) => (
              <div key={score.key} className={`is-${scoreTone(data[score.key])}`}>
                <strong>{data[score.key] ?? EMPTY}</strong>
                <span>{score.label}</span>
              </div>
            ))}
          </div>
          <dl className="seo-lh-metrics">
            <div><dt>LCP</dt><dd>{formatMs(data.metrics.lcp_ms)}</dd></div>
            <div><dt>FCP</dt><dd>{formatMs(data.metrics.fcp_ms)}</dd></div>
            <div><dt>TBT</dt><dd>{formatMs(data.metrics.tbt_ms)}</dd></div>
            <div><dt>CLS</dt><dd>{data.metrics.cls ?? EMPTY}</dd></div>
          </dl>
          {(data.audits ?? []).length > 0 ? (
            <ul className="seo-checks seo-audits">
              {(data.audits ?? []).map((audit) => (
                <li key={audit.id} className={audit.score < 50 ? 'is-bad' : 'is-ok'}>
                  <i aria-hidden="true" />
                  <div>
                    <strong>{audit.title}</strong>
                    <p>{CATEGORY_LABELS[audit.category] ?? audit.category}{audit.display ? ` · ${audit.display}` : ''}</p>
                  </div>
                  {LIGHTHOUSE_FIXES[audit.id] ? (
                    <button
                      className="field-ai-btn"
                      type="button"
                      title={aiDisabled ? aiNote : 'Corrigir com IA'}
                      aria-label={`Corrigir com IA: ${audit.title}`}
                      disabled={aiDisabled || busy}
                      onClick={() => onFix(LIGHTHOUSE_FIXES[audit.id])}
                    >
                      <Sparkles size={12} />
                    </button>
                  ) : null}
                </li>
              ))}
            </ul>
          ) : (
            <p className="hint">Nenhuma auditoria reprovada.</p>
          )}
          <p className="hint">
            {report?.fetched_at ? `Medido em ${new Date(report.fetched_at).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}. ` : ''}
            Mede a versão publicada.{' '}
            {report?.url ? <a href={report.url} target="_blank" rel="noreferrer">Abrir <ExternalLink size={10} /></a> : null}
          </p>
        </>
      ) : null}
    </div>
  );
}
