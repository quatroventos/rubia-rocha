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
