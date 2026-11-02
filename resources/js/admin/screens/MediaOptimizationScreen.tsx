import { type FormEvent, useEffect, useMemo, useState } from 'react';
import { ImageDown, RotateCcw, Settings2, Sparkles } from 'lucide-react';
import { AppShell } from '../components/AppShell';
import { ConfirmDialog } from '../components/ConfirmDialog';
import { Modal } from '../components/Modal';
import { api, firstError } from '../api';
import { useAuth } from '../auth';
import { useTour } from '../tour/TourProvider';
import { formatDate } from '../lib';

type OptimizationItem = {
  path: string;
  name: string;
  url: string;
  optimized_url: string | null;
  status: 'pending' | 'optimized' | 'error';
  original_bytes: number;
  optimized_bytes: number | null;
  quality: number | null;
  max_width: number | null;
  width: number | null;
  height: number | null;
  error: string | null;
  optimized_at: string | null;
};

type OptimizationPanel = {
  settings: { quality: number; max_width: number | null };
  capabilities?: { webp: boolean };
  summary: {
    total: number;
    optimized: number;
    pending: number;
    errors: number;
    original_bytes: number;
    optimized_bytes: number;
    saved_bytes: number;
  };
  items: OptimizationItem[];
  error_log?: Array<{ path: string; name: string; error: string; at: string | null }>;
  processed?: number;
  failed?: number;
  reverted?: number;
  failures?: Array<{ path: string; error: string }>;
};

function formatBytes(value: number): string {
  if (value < 1024) {
    return `${value} B`;
  }
  if (value < 1024 * 1024) {
    return `${(value / 1024).toFixed(1).replace('.', ',')} KB`;
  }
  return `${(value / (1024 * 1024)).toFixed(1).replace('.', ',')} MB`;
}

function statusLabel(status: OptimizationItem['status']): string {
  if (status === 'optimized') {
    return 'Otimizada';
  }
  if (status === 'error') {
    return 'Erro';
  }
  return 'Original';
}

export function MediaOptimizationScreen() {
  const { user } = useAuth();
  const { registerActions } = useTour();
  const isRoot = Boolean(user?.abilities.is_root);
  const [panel, setPanel] = useState<OptimizationPanel | null>(null);
  const [quality, setQuality] = useState(90);
  const [maxWidth, setMaxWidth] = useState(2560);
  const [noResize, setNoResize] = useState(false);
  const [selected, setSelected] = useState<string[]>([]);
  const [error, setError] = useState('');
  const [ok, setOk] = useState('');
  const [busy, setBusy] = useState(false);
  const [settingsOpen, setSettingsOpen] = useState(false);
  const [confirmRevertAll, setConfirmRevertAll] = useState(false);

  const load = (payload: OptimizationPanel) => {
    setPanel(payload);
    setQuality(payload.settings.quality);
    setNoResize(payload.settings.max_width == null);
    setMaxWidth(payload.settings.max_width ?? 2560);
    setSelected((current) => current.filter((path) => payload.items.some((item) => item.path === path)));
  };

  async function refresh() {
    const payload = await api<{ data: OptimizationPanel }>('/media-optimizations');
    load(payload.data);
  }

  useEffect(() => {
    void refresh().catch((err) => setError(firstError(err)));
  }, []);

  useEffect(() => registerActions({
    'images.openSettings': () => {
      if (panel) {
        setQuality(panel.settings.quality);
        setNoResize(panel.settings.max_width == null);
        setMaxWidth(panel.settings.max_width ?? 2560);
      }
      setError('');
      setSettingsOpen(true);
    },
    'images.closeSettings': () => setSettingsOpen(false),
  }), [panel, registerActions]);

  function openSettings() {
    if (panel) {
      setQuality(panel.settings.quality);
      setNoResize(panel.settings.max_width == null);
      setMaxWidth(panel.settings.max_width ?? 2560);
    }
    setError('');
    setSettingsOpen(true);
  }

  const allSelected = useMemo(() => {
    if (!panel || panel.items.length === 0) {
      return false;
    }
    return panel.items.every((item) => selected.includes(item.path));
  }, [panel, selected]);

  function toggleAll() {
    if (!panel) {
      return;
    }
    setSelected(allSelected ? [] : panel.items.map((item) => item.path));
  }

  function togglePath(path: string) {
    setSelected((current) => (
      current.includes(path)
        ? current.filter((item) => item !== path)
        : [...current, path]
    ));
  }

  async function saveSettings(event: FormEvent) {
    event.preventDefault();
    setError('');
    setOk('');
    setBusy(true);
    try {
      const payload = await api<{ data: OptimizationPanel }>('/media-optimizations/settings', {
        method: 'PUT',
        body: JSON.stringify({
          quality,
          max_width: noResize ? 0 : maxWidth,
        }),
      });
      load(payload.data);
      setSettingsOpen(false);
      setOk('Opções salvas.');
    } catch (err) {
      setError(firstError(err));
    } finally {
      setBusy(false);
    }
  }

  async function runOptimize(paths?: string[]) {
    setError('');
    setOk('');
    setBusy(true);
    try {
      const payload = await api<{ data: OptimizationPanel }>('/media-optimizations/optimize', {
        method: 'POST',
        body: JSON.stringify({
          paths: paths ?? [],
          quality,
          max_width: noResize ? 0 : maxWidth,
        }),
      });
      load(payload.data);
      const processed = payload.data.processed ?? 0;
      const failed = payload.data.failed ?? 0;
      if (failed > 0 && !payload.data.capabilities?.webp) {
        setError('Este servidor não tem suporte a WebP no PHP/GD. É preciso rebuild da imagem Docker.');
      }
      setOk(
        failed > 0
          ? `${processed} otimizada(s), ${failed} com erro.`
          : `${processed} imagem(ns) otimizada(s) em WebP.`,
      );
    } catch (err) {
      setError(firstError(err));
    } finally {
      setBusy(false);
    }
  }

  async function runRevert(paths?: string[]) {
    setError('');
    setOk('');
    setBusy(true);
    try {
      const payload = await api<{ data: OptimizationPanel }>('/media-optimizations/revert', {
        method: 'POST',
        body: JSON.stringify({ paths: paths ?? [] }),
      });
      load(payload.data);
      setConfirmRevertAll(false);
      setOk(`${payload.data.reverted ?? 0} imagem(ns) revertida(s) ao original.`);
    } catch (err) {
      setError(firstError(err));
    } finally {
      setBusy(false);
    }
  }

  const summary = panel?.summary;

  return (
    <AppShell
      eyebrow="Sistema"
      title="Otimizar imagens"
      subtitle="Gera cópias WebP sem apagar o original. O site passa a servir a versão otimizada automaticamente."
      actions={(
        <>
          <button className="btn btn-secondary" type="button" data-tour="images-options" disabled={busy} onClick={openSettings}>
            <Settings2 size={14} />
            Opções
          </button>
          <button className="btn btn-secondary" type="button" data-tour="images-revert" disabled={busy || !summary?.optimized} onClick={() => setConfirmRevertAll(true)}>
            <RotateCcw size={14} />
            Reverter tudo
          </button>
          <button
            className="btn btn-primary"
            type="button"
            data-tour="images-optimize"
            disabled={busy || !panel?.items.length}
            onClick={() => void runOptimize(selected.length ? selected : undefined)}
          >
            <Sparkles size={14} />
            {selected.length ? `Otimizar (${selected.length})` : 'Otimizar todas'}
          </button>
        </>
      )}
    >
      <div className="stat-grid" data-tour="images-stats">
        <article className="stat-card">
          <label>Imagens</label>
          <strong>{summary?.total ?? '—'}</strong>
        </article>
        <article className="stat-card">
          <label>Otimizadas</label>
          <strong>{summary?.optimized ?? '—'}</strong>
        </article>
        <article className="stat-card">
          <label>Erros</label>
          <strong>{summary?.errors ?? '—'}</strong>
        </article>
        <article className="stat-card">
          <label>Economia</label>
          <strong>{summary ? formatBytes(summary.saved_bytes) : '—'}</strong>
        </article>
      </div>

      {panel && panel.capabilities?.webp === false ? (
        <div className="form-error">
          PHP sem suporte a WebP (GD). No Docker: rebuild da imagem com libwebp e depois tente de novo.
        </div>
      ) : null}

      {isRoot && (panel?.error_log?.length ?? 0) > 0 ? (
        <section className="panel table-panel" data-tour="images-errors">
          <div className="panel-head">
            <div>
              <h3>Log de erros</h3>
              <p>Visível para root. Também entra no Log de ações quando a otimização falha.</p>
            </div>
          </div>
          <div className="panel-body">
            <div className="backup-table">
              <table>
                <thead>
                  <tr>
                    <th>Quando</th>
                    <th>Arquivo</th>
                    <th>Erro</th>
                  </tr>
                </thead>
                <tbody>
                  {panel?.error_log?.map((entry) => (
                    <tr key={`${entry.path}-${entry.at ?? 'x'}`}>
                      <td>{entry.at ? formatDate(entry.at) : '—'}</td>
                      <td className="person">
                        <strong>{entry.name}</strong>
                        <span>{entry.path}</span>
                      </td>
                      <td><span className="media-opt-error-text">{entry.error}</span></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </section>
      ) : null}

      <section className="panel table-panel" data-tour="images-table">
        <div className="panel-head">
          <div>
            <h3>Biblioteca</h3>
            <p>
              Qualidade {panel?.settings.quality ?? 90}%
              {' · '}
              {panel?.settings.max_width
                ? `largura máx. ${panel.settings.max_width}px`
                : 'sem redimensionar'}
              {' · '}
              saída sempre WebP
            </p>
          </div>
        </div>
        <div className="panel-body">
          {error ? <div className="form-error">{error}</div> : null}
          {ok ? <div className="form-success">{ok}</div> : null}

          <div className="backup-table media-opt-table">
            <table>
              <thead>
                <tr>
                  <th>
                    <input
                      type="checkbox"
                      checked={allSelected}
                      onChange={toggleAll}
                      aria-label="Selecionar todas"
                      disabled={!panel?.items.length}
                    />
                  </th>
                  <th>Arquivo</th>
                  <th>Status</th>
                  <th>Original</th>
                  <th>WebP</th>
                  <th>Economia</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {(panel?.items ?? []).map((item) => {
                  const saved = item.optimized_bytes != null
                    ? Math.max(0, item.original_bytes - item.optimized_bytes)
                    : 0;

                  return (
                    <tr key={item.path}>
                      <td>
                        <input
                          type="checkbox"
                          checked={selected.includes(item.path)}
                          onChange={() => togglePath(item.path)}
                          aria-label={`Selecionar ${item.name}`}
                        />
                      </td>
                      <td>
                        <div className="media-opt-file">
                          <img src={item.optimized_url ?? item.url} alt="" loading="lazy" />
                          <div>
                            <strong title={item.path}>{item.name}</strong>
                            <span>{item.path}</span>
                          </div>
                        </div>
                      </td>
                      <td>
                        <em className={`media-opt-status is-${item.status}`} title={item.error ?? undefined}>
                          {statusLabel(item.status)}
                        </em>
                        {item.status === 'error' && item.error ? (
                          <span className="media-opt-error-text">{item.error}</span>
                        ) : null}
                      </td>
                      <td>{formatBytes(item.original_bytes)}</td>
                      <td>{item.optimized_bytes != null ? formatBytes(item.optimized_bytes) : '—'}</td>
                      <td>{item.optimized_bytes != null ? formatBytes(saved) : '—'}</td>
                      <td>
                        <div className="row-actions">
                          {item.status === 'optimized' ? (
                            <button
                              className="btn btn-secondary btn-sm"
                              type="button"
                              disabled={busy}
                              onClick={() => void runRevert([item.path])}
                            >
                              <RotateCcw size={12} />
                              Reverter
                            </button>
                          ) : (
                            <button
                              className="btn btn-secondary btn-sm"
                              type="button"
                              disabled={busy}
                              onClick={() => void runOptimize([item.path])}
                            >
                              <ImageDown size={12} />
                              Otimizar
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                })}
                {!panel?.items.length ? (
                  <tr>
                    <td colSpan={7}>Nenhuma imagem em storage/assets ou storage/media.</td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
        </div>
      </section>

      {settingsOpen ? (
        <Modal
          title="Opções de otimização"
          eyebrow="Sistema"
          onClose={() => setSettingsOpen(false)}
          footer={(
            <>
              <button className="btn btn-secondary" type="button" onClick={() => setSettingsOpen(false)}>Cancelar</button>
              <button className="btn btn-primary" type="submit" form="media-opt-settings" disabled={busy}>Salvar</button>
            </>
          )}
        >
          <form id="media-opt-settings" onSubmit={(event) => void saveSettings(event)}>
            <div className="field">
              <label htmlFor="media-opt-quality">Qualidade WebP ({quality}%)</label>
              <input
                id="media-opt-quality"
                type="range"
                min={60}
                max={100}
                step={1}
                value={quality}
                onChange={(event) => setQuality(Number(event.target.value))}
              />
              <p className="desc">90–100 mantém aparência próxima do original. Valores menores geram arquivos menores.</p>
            </div>
            <div className="field">
              <label>
                <input
                  type="checkbox"
                  checked={noResize}
                  onChange={(event) => setNoResize(event.target.checked)}
                />
                {' '}Não redimensionar
              </label>
            </div>
            {!noResize ? (
              <div className="field">
                <label htmlFor="media-opt-width">Largura máxima (px)</label>
                <input
                  id="media-opt-width"
                  type="number"
                  min={640}
                  max={5000}
                  value={maxWidth}
                  onChange={(event) => setMaxWidth(Number(event.target.value))}
                />
                <p className="desc">Imagens mais largas que isso são reduzidas proporcionalmente antes do WebP.</p>
              </div>
            ) : null}
          </form>
        </Modal>
      ) : null}

      {confirmRevertAll ? (
        <ConfirmDialog
          title="Reverter otimizações"
          message="Remove todas as cópias WebP. Os originais voltam a ser servidos no site."
          confirmLabel="Reverter tudo"
          busy={busy}
          onClose={() => setConfirmRevertAll(false)}
          onConfirm={() => void runRevert()}
        />
      ) : null}
    </AppShell>
  );
}
