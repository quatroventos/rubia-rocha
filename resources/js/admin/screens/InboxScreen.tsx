import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { RotateCcw, Trash2 } from 'lucide-react';
import { AppShell } from '../components/AppShell';
import { ConfirmDialog } from '../components/ConfirmDialog';
import { api } from '../api';
import { formatDate } from '../lib';
import { useAuth } from '../auth';
import { useTour } from '../tour/TourProvider';

type ExtraMap = Record<string, { label?: string; value?: string | null } | string | null>;

type Message = {
  id: number;
  name: string;
  email: string;
  phone: string;
  destination: string;
  message: string;
  extra?: ExtraMap | null;
  created_at: string;
};

type Career = {
  id: number;
  name: string;
  email: string;
  phone: string;
  location: string;
  linkedin?: string;
  area: string;
  seniority: string;
  resume_name: string;
  extra?: ExtraMap | null;
  created_at: string;
};

type Tab = 'messages' | 'careers';

function ExtraFields({ extra }: { extra?: ExtraMap | null }) {
  if (!extra || Object.keys(extra).length === 0) {
    return null;
  }

  return (
    <>
      {Object.entries(extra).map(([key, item]) => {
        const label = item && typeof item === 'object' ? (item.label ?? key) : key;
        const value = item && typeof item === 'object' ? (item.value ?? '—') : (item ?? '—');

        return (
          <p key={key}><strong>{label}</strong><br />{String(value || '—')}</p>
        );
      })}
    </>
  );
}

export function InboxScreen() {
  const { user } = useAuth();
  const { registerActions } = useTour();
  const [params, setParams] = useSearchParams();
  const tab: Tab = params.get('tipo') === 'curriculos' ? 'careers' : 'messages';
  const [trashed, setTrashed] = useState(false);
  const [items, setItems] = useState<Array<Message | Career>>([]);
  const [current, setCurrent] = useState<Message | Career | null>(null);
  const [pendingDelete, setPendingDelete] = useState<Message | Career | null>(null);
  const [busy, setBusy] = useState(false);
  const canDelete = tab === 'messages' ? user?.abilities.messages_delete : user?.abilities.careers_delete;

  async function load(next: Tab, nextTrashed = trashed) {
    const qs = nextTrashed ? '?trashed=1' : '';
    const payload = await api<{ data: Array<Message | Career> }>(`/${next}${qs}`);
    setItems(payload.data);
    setCurrent(payload.data[0] ?? null);
  }

  useEffect(() => {
    void load(tab, trashed);
  }, [tab, trashed]);

  useEffect(() => registerActions({
    'inbox.openCareers': () => setParams({ tipo: 'curriculos' }, { replace: true }),
    'inbox.openMessages': () => setParams({}, { replace: true }),
  }), [registerActions, setParams]);

  function selectTab(next: Tab) {
    setTrashed(false);
    setParams(next === 'careers' ? { tipo: 'curriculos' } : {}, { replace: true });
  }

  async function confirmDelete() {
    if (!pendingDelete) {
      return;
    }
    setBusy(true);
    try {
      await api(`/${tab}/${pendingDelete.id}`, { method: 'DELETE' });
      setPendingDelete(null);
      await load(tab);
    } finally {
      setBusy(false);
    }
  }

  async function restore(item: Message | Career) {
    setBusy(true);
    try {
      await api(`/${tab}/${item.id}/restore`, { method: 'POST' });
      await load(tab);
    } finally {
      setBusy(false);
    }
  }

  return (
    <AppShell title="Mensagens recebidas" subtitle="Contato e currículos" inbox>
      <div className="inbox-head">
        <div className="inbox-tabs" data-tour="inbox-tabs">
          <button type="button" className={tab === 'messages' ? 'is-on' : ''} onClick={() => selectTab('messages')}>
            Contato
          </button>
          <button type="button" className={tab === 'careers' ? 'is-on' : ''} onClick={() => selectTab('careers')}>
            Currículos
          </button>
        </div>
        {canDelete ? (
          <div className="resource-tabs" data-tour="inbox-trash-tabs">
            <button type="button" className={!trashed ? 'is-on' : ''} onClick={() => setTrashed(false)}>
              Ativos
            </button>
            <button type="button" className={trashed ? 'is-on' : ''} onClick={() => setTrashed(true)}>
              Lixeira
            </button>
          </div>
        ) : null}
      </div>
      <div className="inbox-layout">
        <div className="inbox-list" data-tour="inbox-list">
          {items.length === 0 ? <div className="empty">{trashed ? 'Lixeira vazia.' : 'Nada por aqui.'}</div> : null}
          {items.map((item) => (
            <button
              key={item.id}
              type="button"
              className={`inbox-item${current?.id === item.id ? ' active' : ''}`}
              onClick={() => setCurrent(item)}
            >
              <strong>{item.name}</strong>
              <span>{item.email} · {formatDate(item.created_at)}</span>
            </button>
          ))}
        </div>
        <div className="inbox-detail" data-tour="inbox-detail">
          {!current ? (
            <div className="empty">Selecione um item.</div>
          ) : (
            <>
              <div className="section-header">
                <div>
                  <h2>{current.name}</h2>
                  <p className="desc">{current.email}</p>
                </div>
                <div className="row-actions">
                  {trashed ? (
                    <button
                      className="btn btn-secondary"
                      type="button"
                      disabled={busy}
                      data-tour="inbox-restore"
                      onClick={() => void restore(current)}
                    >
                      <RotateCcw size={14} /> Recuperar
                    </button>
                  ) : (
                    <>
                      {tab === 'careers' ? (
                        <a
                          className="btn btn-secondary"
                          href={`${window.__ADMIN__.path}/api/careers/${current.id}/download`}
                          data-tour="inbox-download"
                        >
                          Baixar currículo
                        </a>
                      ) : null}
                      {canDelete ? (
                        <button
                          className="btn btn-danger"
                          type="button"
                          data-tour="inbox-delete"
                          onClick={() => setPendingDelete(current)}
                        >
                          <Trash2 size={14} /> Excluir
                        </button>
                      ) : null}
                    </>
                  )}
                </div>
              </div>
              <div className="panel">
                <div className="panel-body">
                  {'destination' in current ? (
                    <>
                      <p><strong>Telefone</strong><br />{current.phone}</p>
                      <p><strong>Destino</strong><br />{current.destination}</p>
                      <p><strong>Mensagem</strong><br />{current.message}</p>
                      <ExtraFields extra={current.extra} />
                    </>
                  ) : (
                    <>
                      <p><strong>Telefone</strong><br />{current.phone}</p>
                      <p><strong>Localização</strong><br />{current.location}</p>
                      <p><strong>Área</strong><br />{current.area} · {current.seniority}</p>
                      <p><strong>Arquivo</strong><br />{current.resume_name}</p>
                      <ExtraFields extra={current.extra} />
                    </>
                  )}
                </div>
              </div>
            </>
          )}
        </div>
      </div>
      {pendingDelete ? (
        <ConfirmDialog
          title="Mover para a lixeira?"
          message="O registro sai da caixa de entrada, mas pode ser recuperado depois na lixeira."
          confirmLabel="Mover para a lixeira"
          busy={busy}
          onClose={() => setPendingDelete(null)}
          onConfirm={() => void confirmDelete()}
        />
      ) : null}
    </AppShell>
  );
}
