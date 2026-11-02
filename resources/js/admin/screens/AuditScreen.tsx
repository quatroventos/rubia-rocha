import { useEffect, useState } from 'react';
import { AppShell } from '../components/AppShell';
import { api } from '../api';
import { formatDate } from '../lib';

type AuditRow = {
  id: number;
  created_at: string;
  user_name: string | null;
  user_email: string | null;
  action: string;
  description: string;
  method: string;
  path: string;
  ip: string | null;
  location: string;
  status: number | null;
};

type AuditPage = {
  data: AuditRow[];
  current_page: number;
  last_page: number;
  total: number;
};

export function AuditScreen() {
  const [query, setQuery] = useState('');
  const [page, setPage] = useState(1);
  const [payload, setPayload] = useState<AuditPage | null>(null);

  useEffect(() => {
    const params = new URLSearchParams();
    params.set('page', String(page));
    if (query.trim() !== '') {
      params.set('q', query.trim());
    }

    void api<AuditPage>(`/audit?${params.toString()}`).then(setPayload);
  }, [page, query]);

  return (
    <AppShell
      eyebrow="Sistema"
      title="Log de ações"
      subtitle="Registro de todas as ações do painel."
      actions={(
        <input
          className="audit-search"
          value={query}
          placeholder="Buscar por usuário, ação ou IP"
          data-tour="audit-search"
          onChange={(event) => {
            setQuery(event.target.value);
            setPage(1);
          }}
        />
      )}
    >
      <div className="panel table-panel" data-tour="audit-table">
        <table>
          <thead>
            <tr>
              <th>Horário</th>
              <th>Quem</th>
              <th>Ação</th>
              <th>IP</th>
              <th>Local</th>
            </tr>
          </thead>
          <tbody>
            {(payload?.data ?? []).length === 0 ? (
              <tr>
                <td colSpan={5}>Nenhum registro.</td>
              </tr>
            ) : null}
            {(payload?.data ?? []).map((row) => (
              <tr key={row.id}>
                <td>{formatDate(row.created_at)}</td>
                <td className="person">
                  <strong>{row.user_name || '—'}</strong>
                  <span>{row.user_email || row.action}</span>
                </td>
                <td>
                  <strong>{row.description}</strong>
                  <span className="audit-path">{row.method} {row.path}</span>
                </td>
                <td>{row.ip || '—'}</td>
                <td>{row.location}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {payload && payload.last_page > 1 ? (
        <div className="audit-pager" data-tour="audit-pager">
          <button type="button" className="btn btn-secondary" disabled={page <= 1} onClick={() => setPage(page - 1)}>
            Anterior
          </button>
          <span>{payload.current_page} / {payload.last_page}</span>
          <button type="button" className="btn btn-secondary" disabled={page >= payload.last_page} onClick={() => setPage(page + 1)}>
            Próxima
          </button>
        </div>
      ) : null}
    </AppShell>
  );
}
