import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Pencil } from 'lucide-react';
import { AppShell } from '../components/AppShell';
import { api } from '../api';

type FormRow = {
  key: string;
  label: string;
  path: string;
  fields: number;
  options: number;
};

export function FormsScreen() {
  const navigate = useNavigate();
  const [forms, setForms] = useState<FormRow[]>([]);
  const [query, setQuery] = useState('');

  useEffect(() => {
    void api<{ data: FormRow[] }>('/forms').then((payload) => setForms(payload.data));
  }, []);

  const filtered = forms.filter((form) => JSON.stringify(form).toLowerCase().includes(query.toLowerCase()));

  return (
    <AppShell eyebrow="Configuração" title="Formulários" subtitle="Formulário de contato. Clique para editar os campos.">
      <div className="toolbar">
        <input className="search" placeholder="Buscar" value={query} data-tour="forms-search" onChange={(event) => setQuery(event.target.value)} />
      </div>
      <div className="panel table-panel" data-tour="forms-table">
        {filtered.length === 0 ? (
          <div className="empty">Nenhum formulário encontrado.</div>
        ) : (
          <table>
            <thead>
              <tr>
                <th>Formulário</th>
                <th>Página</th>
                <th>Campos</th>
                <th>Opções</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {filtered.map((form) => (
                <tr key={form.key} className="is-clickable" onClick={() => navigate(`/formularios/${form.key}`)}>
                  <td className="person">
                    <strong>{form.label}</strong>
                    <span>{form.key}</span>
                  </td>
                  <td>{form.path}</td>
                  <td>{form.fields}</td>
                  <td>{form.options}</td>
                  <td>
                    <div className="row-actions">
                      <Link className="icon-btn" to={`/formularios/${form.key}`} onClick={(event) => event.stopPropagation()}><Pencil size={14} /></Link>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </AppShell>
  );
}
