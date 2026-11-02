import { type FormEvent, useState } from 'react';
import { AppShell } from '../components/AppShell';
import { RecoveryCodes } from '../components/RecoveryCodes';
import { api, firstError } from '../api';
import { useAuth } from '../auth';

export function ProfileScreen() {
  const { user, refresh } = useAuth();
  const [name, setName] = useState(user?.name ?? '');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [ok, setOk] = useState(false);
  const [codesPassword, setCodesPassword] = useState('');
  const [codesError, setCodesError] = useState('');
  const [codes, setCodes] = useState<string[] | null>(null);

  async function save(event: FormEvent) {
    event.preventDefault();
    setError('');
    setOk(false);
    try {
      await api('/profile', {
        method: 'PUT',
        body: JSON.stringify({ name, password: password || undefined }),
      });
      setPassword('');
      setOk(true);
      await refresh();
    } catch (err) {
      setError(firstError(err));
    }
  }

  async function regenerateCodes(event: FormEvent) {
    event.preventDefault();
    setCodesError('');
    try {
      const payload = await api<{ recovery_codes: string[] }>('/profile/recovery-codes', {
        method: 'POST',
        body: JSON.stringify({ password: codesPassword }),
      });
      setCodesPassword('');
      setCodes(payload.recovery_codes);
      await refresh();
    } catch (err) {
      setCodesError(firstError(err));
    }
  }

  return (
    <AppShell eyebrow="Sistema" title="Perfil">
      <form id="profile-form" className="panel" onSubmit={(event) => void save(event)}>
        <div className="panel-body">
          {error ? <div className="form-error">{error}</div> : null}
          {ok ? <div className="form-success">Perfil atualizado.</div> : null}
          <div className="field" data-tour="profile-name">
            <label>Nome</label>
            <input value={name} onChange={(event) => setName(event.target.value)} />
          </div>
          <div className="field" data-tour="profile-password">
            <label>Nova senha</label>
            <input type="password" value={password} onChange={(event) => setPassword(event.target.value)} />
          </div>
          <div className="form-actions" data-tour="profile-save">
            <button className="btn btn-primary" type="submit">Salvar</button>
          </div>
        </div>
      </form>

      {user?.impersonating ? null : (
        <form className="panel profile-two-factor" onSubmit={(event) => void regenerateCodes(event)}>
          <div className="panel-head">
            <h3>Autenticação em dois fatores</h3>
            <p>Ativa. Restam {user?.recovery_codes_left ?? 0} códigos de recuperação. Gerar novos invalida os anteriores.</p>
          </div>
          <div className="panel-body">
            {codesError ? <div className="form-error">{codesError}</div> : null}
            {codes ? (
              <>
                <div className="form-success">Guarde estes códigos agora. Eles não serão exibidos de novo.</div>
                <RecoveryCodes codes={codes} />
              </>
            ) : null}
            <div className="field">
              <label>Senha atual</label>
              <input
                type="password"
                value={codesPassword}
                onChange={(event) => setCodesPassword(event.target.value)}
                required
              />
            </div>
            <div className="form-actions">
              <button className="btn btn-secondary" type="submit">Gerar novos códigos</button>
            </div>
          </div>
        </form>
      )}
    </AppShell>
  );
}
