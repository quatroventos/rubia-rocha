import { type ChangeEvent, type FormEvent, useEffect, useRef, useState } from 'react';
import { Download, RotateCcw, Settings2, Trash2, Upload } from 'lucide-react';
import { AppShell } from '../components/AppShell';
import { ConfirmDialog } from '../components/ConfirmDialog';
import { Modal } from '../components/Modal';
import { api, apiPath, firstError } from '../api';
import { formatDate } from '../lib';
import { useTour } from '../tour/TourProvider';

type BackupItem = {
  id: number;
  filename: string | null;
  size: number;
  status: string;
  trigger: string;
  user_name: string | null;
  error: string | null;
  created_at: string | null;
};

type BackupPanel = {
  hour: number;
  retention_days: number;
  next_at: string;
  last_at: string | null;
  trashed?: boolean;
  items: BackupItem[];
};

function formatBytes(value: number): string {
  if (value < 1024) {
    return `${value} B`;
  }
  if (value < 1024 * 1024) {
    return `${(value / 1024).toFixed(1)} KB`;
  }
  return `${(value / (1024 * 1024)).toFixed(1)} MB`;
}

export function BackupScreen() {
  const { registerActions } = useTour();
  const [panel, setPanel] = useState<BackupPanel | null>(null);
  const [hour, setHour] = useState(3);
  const [retention, setRetention] = useState(7);
  const [error, setError] = useState('');
  const [ok, setOk] = useState('');
  const [busy, setBusy] = useState(false);
  const [settingsOpen, setSettingsOpen] = useState(false);
  const [restoreId, setRestoreId] = useState<number | null>(null);
  const [confirmText, setConfirmText] = useState('');
  const [trashed, setTrashed] = useState(false);
  const [pendingDeleteId, setPendingDeleteId] = useState<number | null>(null);
  const fileInput = useRef<HTMLInputElement>(null);

  const load = (payload: BackupPanel) => {
    setPanel(payload);
    setHour(payload.hour);
    setRetention(payload.retention_days);
  };

  async function refresh(nextTrashed = trashed) {
    const qs = nextTrashed ? '?trashed=1' : '';
    const payload = await api<{ data: BackupPanel }>(`/backup${qs}`);
    load(payload.data);
  }

  useEffect(() => {
    void refresh(trashed);
  }, [trashed]);

  useEffect(() => registerActions({
    'backup.openSettings': () => {
      if (panel) {
        setHour(panel.hour);
        setRetention(panel.retention_days);
      }
      setError('');
      setSettingsOpen(true);
    },
    'backup.closeSettings': () => setSettingsOpen(false),
  }), [panel, registerActions]);

  async function saveSettings(event: FormEvent) {
    event.preventDefault();
    setError('');
    setOk('');
    setBusy(true);
    try {
      const payload = await api<{ data: BackupPanel }>('/backup', {
        method: 'PUT',
        body: JSON.stringify({ hour, retention_days: retention }),
      });
      load(payload.data);
      setSettingsOpen(false);
      setOk('Agendamento atualizado.');
    } catch (err) {
      setError(firstError(err));
    } finally {
      setBusy(false);
    }
  }

  async function runNow() {
    setError('');
    setOk('');
    setBusy(true);
    try {
      const payload = await api<{ data: BackupPanel }>('/backup/run', { method: 'POST' });
      load(payload.data);
      setOk('Backup gerado.');
    } catch (err) {
      setError(firstError(err));
    } finally {
      setBusy(false);
    }
  }

  async function importFile(event: ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) {
      return;
    }
    setError('');
    setOk('');
    setBusy(true);
    try {
      const body = new FormData();
      body.append('file', file);
      const payload = await api<{ data: BackupPanel }>('/backup/import', { method: 'POST', body });
      setTrashed(false);
      load(payload.data);
      setOk('Backup importado. Use o botão de restaurar na linha "Importado" para aplicar.');
    } catch (err) {
      setError(firstError(err));
    } finally {
      setBusy(false);
    }
  }

  async function confirmDelete() {
    if (pendingDeleteId === null) {
      return;
    }
    setError('');
    setOk('');
    setBusy(true);
    try {
      const payload = await api<{ data: BackupPanel }>(`/backup/${pendingDeleteId}`, { method: 'DELETE' });
      load(payload.data);
      setPendingDeleteId(null);
      setOk('Backup movido para a lixeira.');
    } catch (err) {
      setError(firstError(err));
    } finally {
      setBusy(false);
    }
  }

  async function undelete(id: number) {
    setError('');
    setOk('');
    setBusy(true);
    try {
      await api<{ data: BackupPanel }>(`/backup/${id}/undelete`, { method: 'POST' });
      await refresh();
      setOk('Backup recuperado.');
    } catch (err) {
      setError(firstError(err));
    } finally {
      setBusy(false);
    }
  }

  async function restore() {
    if (restoreId === null || confirmText !== 'RESTAURAR') {
      return;
    }
    setError('');
    setOk('');
    setBusy(true);
    try {
      const payload = await api<{ data: BackupPanel }>(`/backup/${restoreId}/restore`, { method: 'POST' });
      load(payload.data);
      setRestoreId(null);
      setConfirmText('');
      setOk('Backup restaurado.');
    } catch (err) {
      setError(firstError(err));
    } finally {
      setBusy(false);
    }
  }

  function download(id: number) {
    window.location.href = apiPath(`/backup/${id}/download`);
  }

  function openSettings() {
    if (panel) {
      setHour(panel.hour);
      setRetention(panel.retention_days);
    }
    setError('');
    setSettingsOpen(true);
  }

  return (
    <AppShell
      eyebrow="Sistema"
      title="Backup"
      subtitle={panel
        ? `Snapshot diário do banco e dos arquivos. Próximo: ${formatDate(panel.next_at)} · Retenção: ${panel.retention_days} dias.`
        : 'Snapshot diário do banco e dos arquivos.'}
      actions={(
        <div className="backup-actions">
          <button className="btn btn-secondary" type="button" data-tour="backup-config" disabled={busy} onClick={openSettings}>
            <Settings2 size={14} />
            Configurar backups
          </button>
          <input ref={fileInput} type="file" accept=".zip,application/zip" hidden onChange={(event) => void importFile(event)} />
          <button className="btn btn-secondary" type="button" disabled={busy} onClick={() => fileInput.current?.click()}>
            <Upload size={14} />
            Importar backup
          </button>
          <button className="btn btn-primary" type="button" data-tour="backup-manual" disabled={busy} onClick={() => void runNow()}>
            Backup manual
          </button>
        </div>
      )}
    >
      {error ? <div className="form-error">{error}</div> : null}
      {ok ? <div className="form-success">{ok}</div> : null}

      <div className="toolbar">
        <div className="resource-tabs" data-tour="backup-trash-tabs">
          <button type="button" className={!trashed ? 'is-on' : ''} onClick={() => setTrashed(false)}>
            Ativos
          </button>
          <button type="button" className={trashed ? 'is-on' : ''} onClick={() => setTrashed(true)}>
            Lixeira
          </button>
        </div>
      </div>

      <div className="panel table-panel backup-table" data-tour="backup-table">
        <table>
          <thead>
            <tr>
              <th>Data</th>
              <th>Tamanho</th>
              <th>Origem</th>
              <th>Status</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {(panel?.items ?? []).map((item, index) => (
              <tr key={item.id}>
                <td className="person">
                  <strong>{formatDate(item.created_at)}</strong>
                  <span>{item.filename ?? '—'}</span>
                </td>
                <td>{formatBytes(item.size)}</td>
                <td>{item.trigger === 'schedule' ? 'Agendado' : item.trigger === 'upload' ? `Importado${item.user_name ? ` por ${item.user_name}` : ''}` : (item.user_name ?? 'Manual')}</td>
                <td>
                  <span className={`badge ${item.status === 'ok' ? 'badge-green' : 'badge-neutral'}`}>
                    {item.status === 'ok' ? 'Ok' : 'Falhou'}
                  </span>
                </td>
                <td>
                  <div className="row-actions">
                    {trashed ? (
                      <button className="icon-btn" type="button" disabled={busy} onClick={() => void undelete(item.id)} aria-label="Recuperar">
                        <RotateCcw size={14} />
                      </button>
                    ) : (
                      <>
                        <button className="icon-btn" type="button" data-tour={index === 0 ? 'backup-download' : undefined} disabled={busy || item.status !== 'ok'} onClick={() => download(item.id)} aria-label="Download">
                          <Download size={14} />
                        </button>
                        <button className="icon-btn" type="button" data-tour={index === 0 ? 'backup-restore' : undefined} disabled={busy || item.status !== 'ok'} onClick={() => { setRestoreId(item.id); setConfirmText(''); }} aria-label="Restaurar snapshot">
                          <RotateCcw size={14} />
                        </button>
                        <button className="icon-btn danger" type="button" disabled={busy} onClick={() => setPendingDeleteId(item.id)} aria-label="Excluir">
                          <Trash2 size={14} />
                        </button>
                      </>
                    )}
                  </div>
                </td>
              </tr>
            ))}
            {(panel?.items ?? []).length === 0 ? (
              <tr>
                <td colSpan={5}>{trashed ? 'Lixeira vazia.' : 'Nenhum backup ainda.'}</td>
              </tr>
            ) : null}
          </tbody>
        </table>
      </div>

      {settingsOpen ? (
        <Modal
          title="Configurar backups"
          eyebrow="Sistema"
          onClose={() => setSettingsOpen(false)}
          footer={(
            <>
              <button className="btn btn-secondary" type="button" onClick={() => setSettingsOpen(false)}>Cancelar</button>
              <button className="btn btn-primary" type="submit" form="backup-settings" disabled={busy}>Salvar</button>
            </>
          )}
        >
          <form id="backup-settings" onSubmit={(event) => void saveSettings(event)}>
            <div className="field">
              <label>Horário diário</label>
              <select value={hour} onChange={(event) => setHour(Number(event.target.value))}>
                {Array.from({ length: 24 }, (_, index) => (
                  <option key={index} value={index}>{`${String(index).padStart(2, '0')}:00`}</option>
                ))}
              </select>
            </div>
            <div className="field">
              <label>Retenção (dias)</label>
              <input
                type="number"
                min={1}
                max={90}
                value={retention}
                onChange={(event) => setRetention(Number(event.target.value))}
              />
            </div>
            <p className="hint">
              Último backup: {panel?.last_at ? formatDate(panel.last_at) : 'nenhum'}.
            </p>
          </form>
        </Modal>
      ) : null}

      {restoreId !== null ? (
        <Modal
          title="Restaurar backup"
          eyebrow="Atenção"
          onClose={() => { setRestoreId(null); setConfirmText(''); }}
          footer={(
            <>
              <button className="btn btn-secondary" type="button" onClick={() => { setRestoreId(null); setConfirmText(''); }}>Cancelar</button>
              <button className="btn btn-danger" type="button" disabled={busy || confirmText !== 'RESTAURAR'} onClick={() => void restore()}>
                Restaurar
              </button>
            </>
          )}
        >
          <p className="desc">Isso substitui o banco e os arquivos atuais pelo snapshot, inclusive os usuários: depois de restaurar, entre com um login que existia no backup. Digite <strong>RESTAURAR</strong> para confirmar.</p>
          <div className="field">
            <label>Confirmação</label>
            <input value={confirmText} onChange={(event) => setConfirmText(event.target.value)} placeholder="RESTAURAR" />
          </div>
        </Modal>
      ) : null}

      {pendingDeleteId !== null ? (
        <ConfirmDialog
          title="Mover backup para a lixeira?"
          message="O arquivo do snapshot permanece no disco e pode ser recuperado pela lixeira."
          confirmLabel="Mover para a lixeira"
          busy={busy}
          onClose={() => setPendingDeleteId(null)}
          onConfirm={() => void confirmDelete()}
        />
      ) : null}
    </AppShell>
  );
}
