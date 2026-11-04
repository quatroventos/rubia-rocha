import { type FormEvent, type ReactNode, useEffect, useState } from 'react';
import { Pencil, Plus, RotateCcw, Trash2 } from 'lucide-react';
import { AppShell } from '../components/AppShell';
import { ConfirmDialog } from '../components/ConfirmDialog';
import { Modal } from '../components/Modal';
import { api, firstError } from '../api';
import { MediaField } from '../components/MediaField';
import { RichText } from '../components/RichText';
import { useTour } from '../tour/TourProvider';

export type CrudField = {
  name: string;
  label: string;
  type?: 'text' | 'textarea' | 'select' | 'toggle' | 'number' | 'password' | 'media' | 'rich';
  options?: { value: string; label: string }[];
  required?: boolean;
};

export function CrudPage<T extends { id: number }>({
  eyebrow,
  title,
  description,
  endpoint,
  columns,
  fields,
  createLabel,
  mapForm,
  initialForm,
  canCreate = true,
  canDelete = true,
  canDeleteRow,
  rowActions,
}: {
  eyebrow: string;
  title: string;
  description: string;
  endpoint: string;
  columns: { key: string; label: string; render?: (row: T) => ReactNode }[];
  fields: CrudField[];
  createLabel: string;
  mapForm?: (row: T) => Record<string, unknown>;
  initialForm?: Record<string, unknown>;
  canCreate?: boolean;
  canDelete?: boolean;
  canDeleteRow?: (row: T) => boolean;
  rowActions?: (row: T, reload: () => Promise<void>) => ReactNode;
}) {
  const { registerActions } = useTour();
  const [rows, setRows] = useState<T[]>([]);
  const [query, setQuery] = useState('');
  const [trashed, setTrashed] = useState(false);
  const [open, setOpen] = useState(false);
  const [current, setCurrent] = useState<T | null>(null);
  const [form, setForm] = useState<Record<string, unknown>>({});
  const [error, setError] = useState('');
  const [pendingDelete, setPendingDelete] = useState<T | null>(null);
  const [busy, setBusy] = useState(false);

  async function load(nextTrashed = trashed) {
    const qs = nextTrashed ? '?trashed=1' : '';
    const payload = await api<{ data: T[] }>(`${endpoint}${qs}`);
    setRows(payload.data);
  }

  useEffect(() => {
    void load(trashed);
  }, [endpoint, trashed]);

  function start(row?: T) {
    setCurrent(row ?? null);
    setForm(row ? (mapForm ? mapForm(row) : { ...row }) : { ...(initialForm ?? {}) });
    setError('');
    setOpen(true);
  }

  useEffect(() => registerActions({
    'crud.openCreate': () => start(),
    'crud.close': () => setOpen(false),
  }), [registerActions]);

  async function save(event: FormEvent) {
    event.preventDefault();
    setError('');
    try {
      await api(current ? `${endpoint}/${current.id}` : endpoint, {
        method: current ? 'PUT' : 'POST',
        body: JSON.stringify(form),
      });
      setOpen(false);
      await load();
    } catch (err) {
      setError(firstError(err));
    }
  }

  async function confirmDelete() {
    if (!pendingDelete) {
      return;
    }
    setBusy(true);
    try {
      await api(`${endpoint}/${pendingDelete.id}`, { method: 'DELETE' });
      setPendingDelete(null);
      await load();
    } finally {
      setBusy(false);
    }
  }

  async function restore(row: T) {
    setBusy(true);
    try {
      await api(`${endpoint}/${row.id}/restore`, { method: 'POST' });
      await load();
    } finally {
      setBusy(false);
    }
  }

  const filtered = rows.filter((row) => JSON.stringify(row).toLowerCase().includes(query.toLowerCase()));

  return (
    <AppShell
      eyebrow={eyebrow}
      title={title}
      subtitle={description}
      actions={canCreate && !trashed ? (
        <button className="btn btn-primary" type="button" data-tour="crud-add" onClick={() => start()}>
          <Plus size={16} /> {createLabel}
        </button>
      ) : undefined}
    >
      <div className="toolbar">
        <input
          className="search"
          placeholder="Buscar"
          value={query}
          data-tour="crud-search"
          onChange={(event) => setQuery(event.target.value)}
        />
        {canDelete ? (
          <div className="resource-tabs" data-tour="crud-trash-tabs">
            <button type="button" className={!trashed ? 'is-on' : ''} onClick={() => setTrashed(false)}>
              Ativos
            </button>
            <button type="button" className={trashed ? 'is-on' : ''} onClick={() => setTrashed(true)}>
              Lixeira
            </button>
          </div>
        ) : null}
      </div>
      <div className="panel table-panel" data-tour="crud-table">
        {filtered.length === 0 ? (
          <div className="empty">{trashed ? 'Lixeira vazia.' : 'Nenhum registro encontrado.'}</div>
        ) : (
          <table>
            <thead>
              <tr>
                {columns.map((column) => <th key={column.key}>{column.label}</th>)}
                <th />
              </tr>
            </thead>
            <tbody>
              {filtered.map((row, index) => (
                <tr key={row.id}>
                  {columns.map((column) => (
                    <td key={column.key}>
                      {column.render ? column.render(row) : String((row as Record<string, unknown>)[column.key] ?? '—')}
                    </td>
                  ))}
                  <td>
                    <div className="row-actions">
                      {trashed ? (
                        <button
                          className="icon-btn"
                          type="button"
                          disabled={busy}
                          data-tour={index === 0 ? 'crud-restore' : undefined}
                          onClick={() => void restore(row)}
                          aria-label="Recuperar"
                        >
                          <RotateCcw size={14} />
                        </button>
                      ) : (
                        <>
                          {rowActions?.(row, () => load())}
                          <button
                            className="icon-btn"
                            type="button"
                            data-tour={index === 0 ? 'crud-edit' : undefined}
                            onClick={() => start(row)}
                          >
                            <Pencil size={14} />
                          </button>
                          {canDelete && (!canDeleteRow || canDeleteRow(row)) ? (
                            <button
                              className="icon-btn danger"
                              type="button"
                              data-tour={index === 0 ? 'crud-delete' : undefined}
                              onClick={() => setPendingDelete(row)}
                            >
                              <Trash2 size={14} />
                            </button>
                          ) : null}
                        </>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
      {open ? (
        <Modal
          title={current ? 'Editar' : createLabel}
          onClose={() => setOpen(false)}
          footer={(
            <>
              <button className="btn btn-secondary" type="button" onClick={() => setOpen(false)}>Cancelar</button>
              <button className="btn btn-primary" type="submit" form="crud-form" data-tour="crud-modal-save">Salvar</button>
            </>
          )}
        >
          <form id="crud-form" onSubmit={(event) => void save(event)} data-tour="crud-modal">
            {error ? <div className="form-error">{error}</div> : null}
            {fields.map((field) => (
              <div className="field" key={field.name} data-tour={`crud-field-${field.name}`}>
                <label>{field.label}</label>
                {field.type === 'textarea' ? (
                  <textarea
                    value={String(form[field.name] ?? '')}
                    onChange={(event) => setForm({ ...form, [field.name]: event.target.value })}
                  />
                ) : field.type === 'rich' ? (
                  <RichText
                    value={String(form[field.name] ?? '')}
                    onChange={(value) => setForm({ ...form, [field.name]: value })}
                  />
                ) : field.type === 'media' ? (
                  <MediaField
                    label=""
                    value={String(form[field.name] ?? '')}
                    onChange={(value) => setForm({ ...form, [field.name]: value })}
                  />
                ) : field.type === 'select' ? (
                  <select
                    value={String(form[field.name] ?? '')}
                    onChange={(event) => setForm({ ...form, [field.name]: event.target.value })}
                  >
                    <option value="">Selecione</option>
                    {field.options?.map((option) => (
                      <option key={option.value} value={option.value}>{option.label}</option>
                    ))}
                  </select>
                ) : field.type === 'toggle' ? (
                  <input
                    type="checkbox"
                    checked={Boolean(form[field.name])}
                    onChange={(event) => setForm({ ...form, [field.name]: event.target.checked })}
                  />
                ) : (
                  <input
                    type={field.type === 'password' ? 'password' : field.type === 'number' ? 'number' : 'text'}
                    value={String(form[field.name] ?? '')}
                    onChange={(event) => setForm({
                      ...form,
                      [field.name]: field.type === 'number' ? Number(event.target.value) : event.target.value,
                    })}
                  />
                )}
              </div>
            ))}
          </form>
        </Modal>
      ) : null}
      {pendingDelete ? (
        <ConfirmDialog
          title="Mover para a lixeira?"
          message="O registro sai da lista ativa, mas pode ser recuperado depois na lixeira."
          confirmLabel="Mover para a lixeira"
          busy={busy}
          onClose={() => setPendingDelete(null)}
          onConfirm={() => void confirmDelete()}
        />
      ) : null}
    </AppShell>
  );
}
