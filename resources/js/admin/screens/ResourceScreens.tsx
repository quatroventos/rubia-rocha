import { useNavigate } from 'react-router-dom';
import { ShieldOff, UserRoundSearch } from 'lucide-react';
import { useAuth } from '../auth';
import { api, firstError } from '../api';
import { CrudPage } from './CrudPage';

export { MenuScreen } from './MenuScreen';

export function CertificationsScreen() {
  const typeLabels: Record<string, string> = {
    badge: 'Selo',
    placeholder: 'Espaço reservado',
  };

  return (
    <CrudPage<{ id: number; label: string; type: string }>
      eyebrow="Configuração"
      title="Certificações"
      description="Selos do rodapé."
      endpoint="/certifications"
      createLabel="Adicionar selo"
      columns={[
        { key: 'label', label: 'Label' },
        {
          key: 'type',
          label: 'Tipo',
          render: (row) => typeLabels[row.type] ?? row.type,
        },
      ]}
      fields={[
        { name: 'label', label: 'Label' },
        { name: 'type', label: 'Tipo', type: 'select', options: [
          { value: 'badge', label: 'Selo' },
          { value: 'placeholder', label: 'Espaço reservado' },
        ] },
        { name: 'image_path', label: 'Imagem', type: 'media' },
        { name: 'url', label: 'URL' },
        { name: 'alt', label: 'Alt' },
        { name: 'sort', label: 'Ordem', type: 'number' },
      ]}
    />
  );
}

type RedirectRow = {
  id: number;
  from_path: string;
  to_url: string;
  status_code: number;
  is_active: boolean;
  hits: number;
  last_hit_at: string | null;
};

export function RedirectsScreen() {
  return (
    <CrudPage<RedirectRow>
      eyebrow="Configuração"
      title="Redirecionamentos"
      description="Endereços antigos que levam para as páginas atuais. Só valem para URLs que não existem no site."
      endpoint="/redirects"
      createLabel="Adicionar redirect"
      initialForm={{ status_code: '301', is_active: true }}
      mapForm={(row) => ({ ...row, status_code: String(row.status_code) })}
      columns={[
        { key: 'from_path', label: 'Endereço antigo', render: (row) => <code>{row.from_path}</code> },
        { key: 'to_url', label: 'Destino', render: (row) => <code>{row.to_url}</code> },
        { key: 'status_code', label: 'Tipo', render: (row) => (row.status_code === 302 ? '302 temporário' : '301 permanente') },
        { key: 'is_active', label: 'Status', render: (row) => (row.is_active ? 'Ativo' : 'Pausado') },
        {
          key: 'hits',
          label: 'Acessos',
          render: (row) => (row.last_hit_at
            ? `${row.hits} (último em ${new Date(row.last_hit_at).toLocaleDateString('pt-BR')})`
            : String(row.hits)),
        },
      ]}
      fields={[
        { name: 'from_path', label: 'Endereço antigo (ex.: /sobre-2)' },
        { name: 'to_url', label: 'Destino (/caminho ou https://...)' },
        { name: 'status_code', label: 'Tipo', type: 'select', options: [
          { value: '301', label: '301 permanente' },
          { value: '302', label: '302 temporário' },
        ] },
        { name: 'is_active', label: 'Ativo', type: 'toggle' },
      ]}
    />
  );
}

export function UsersScreen() {
  const { user, setUser } = useAuth();
  const navigate = useNavigate();

  async function impersonate(row: { id: number; name: string }) {
    if (!confirm(`Acessar o painel como ${row.name}?`)) {
      return;
    }
    try {
      const payload = await api<{ user: NonNullable<typeof user> }>(`/users/${row.id}/impersonate`, { method: 'POST' });
      setUser(payload.user);
      navigate('/');
    } catch (err) {
      alert(firstError(err));
    }
  }

  async function resetTwoFactor(row: { id: number; name: string }, reload: () => Promise<void>) {
    if (!confirm(`Zerar o 2FA de ${row.name}? A sessão atual dessa pessoa cai e ela configura um novo autenticador no próximo login.`)) {
      return;
    }
    try {
      await api(`/users/${row.id}/two-factor/reset`, { method: 'POST' });
      await reload();
    } catch (err) {
      alert(firstError(err));
    }
  }

  return (
    <CrudPage<{ id: number; name: string; email: string; role: string; two_factor: boolean }>
      eyebrow="Sistema"
      title="Usuários"
      description="Acesso ao CMS."
      endpoint="/users"
      createLabel="Adicionar usuário"
      canDeleteRow={(row) => row.id !== user?.id}
      rowActions={(row, reload) => (
        user?.abilities.impersonate && row.id !== user?.id ? (
          <>
            <button
              className="icon-btn"
              type="button"
              title="Acessar como"
              aria-label={`Acessar como ${row.name}`}
              data-tour="users-impersonate"
              onClick={() => void impersonate(row)}
            >
              <UserRoundSearch size={14} />
            </button>
            {row.two_factor ? (
              <button
                className="icon-btn"
                type="button"
                title="Zerar 2FA"
                aria-label={`Zerar 2FA de ${row.name}`}
                onClick={() => void resetTwoFactor(row, reload)}
              >
                <ShieldOff size={14} />
              </button>
            ) : null}
          </>
        ) : null
      )}
      columns={[
        { key: 'name', label: 'Nome' },
        { key: 'email', label: 'E-mail' },
        { key: 'role', label: 'Papel', render: (row) => (row.role === 'root' ? 'Root' : 'Usuário') },
        { key: 'two_factor', label: '2FA', render: (row) => (row.two_factor ? 'Ativo' : 'Pendente') },
      ]}
      fields={[
        { name: 'name', label: 'Nome' },
        { name: 'email', label: 'E-mail' },
        { name: 'password', label: 'Senha', type: 'password' },
        { name: 'role', label: 'Papel', type: 'select', options: [
          { value: 'root', label: 'Root' },
          { value: 'user', label: 'Usuário' },
        ] },
      ]}
    />
  );
}
