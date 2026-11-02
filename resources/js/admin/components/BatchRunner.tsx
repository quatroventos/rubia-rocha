import { type ReactNode, useEffect, useRef, useState } from 'react';
import { api, firstError } from '../api';

export type BatchStatus = {
  state: 'idle' | 'running' | 'done' | 'cancelled';
  total: number;
  processed: number;
  failed: number;
  progress: number;
  started_at: string | null;
  finished_at: string | null;
};

const POLL_MS = 3000;

function when(value: string | null): string {
  return value ? new Date(value).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '';
}

/**
 * Dispara um lote de jobs no servidor e acompanha o progresso até terminar.
 */
export function BatchRunner({
  endpoint,
  startLabel,
  unit,
  body,
  children,
  onDone,
}: {
  endpoint: string;
  startLabel: string;
  unit: string;
  body?: () => unknown;
  children?: ReactNode;
  onDone?: () => void;
}) {
  const [status, setStatus] = useState<BatchStatus | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const timer = useRef<number | null>(null);
  const wasRunning = useRef(false);

  useEffect(() => {
    void load();
    return () => {
      if (timer.current !== null) {
        window.clearTimeout(timer.current);
      }
    };
  }, [endpoint]);

  function track(next: BatchStatus) {
    setStatus(next);
    if (timer.current !== null) {
      window.clearTimeout(timer.current);
      timer.current = null;
    }
    if (next.state === 'running') {
      wasRunning.current = true;
      timer.current = window.setTimeout(() => void load(), POLL_MS);
      return;
    }
    if (wasRunning.current) {
      wasRunning.current = false;
      onDone?.();
    }
  }

  async function load() {
    try {
      track(await api<BatchStatus>(endpoint));
    } catch (err) {
      setError(firstError(err));
    }
  }

  async function run(method: 'POST' | 'DELETE') {
    setBusy(true);
    setError('');
    try {
      track(await api<BatchStatus>(endpoint, { method, ...(method === 'POST' && body ? { body: JSON.stringify(body()) } : {}) }));
    } catch (err) {
      setError(firstError(err));
    } finally {
      setBusy(false);
    }
  }

  const running = status?.state === 'running';
  const percent = status && status.total ? Math.round((status.processed / status.total) * 100) : 0;

  return (
    <div className="batch-runner">
      {children}
      {running && status ? (
        <div className="oseo-progress" role="status">
          <div className="oseo-progress-bar"><span style={{ width: `${Math.max(4, percent)}%` }} /></div>
          <p>
            {status.processed} de {status.total} {unit}
            {status.failed ? `, ${status.failed} com falha` : ''}. Roda na fila do servidor; pode sair desta tela.
          </p>
        </div>
      ) : null}
      {!running && status && status.state !== 'idle' ? (
        <p className="hint" role="status">
          {status.state === 'cancelled' ? 'Interrompido' : 'Concluído'} em {when(status.finished_at ?? status.started_at)}: {status.processed - status.failed} de {status.total} {unit}
          {status.failed ? `, ${status.failed} com falha` : ''}.
        </p>
      ) : null}
      <div className="toolbar">
        {running ? (
          <button className="btn btn-secondary" type="button" disabled={busy} onClick={() => void run('DELETE')}>Interromper</button>
        ) : (
          <button className="btn btn-primary" type="button" disabled={busy} onClick={() => void run('POST')}>{busy ? 'Enfileirando...' : startLabel}</button>
        )}
      </div>
      {error ? <p className="field-ai-error">{error}</p> : null}
    </div>
  );
}
