import { ArrowRight } from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import { type FormEvent, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api, firstError } from '../api';
import { useAuth } from '../auth';
import type { CmsUser } from '../lib';
import { DevCredit } from '../components/DevCredit';
import { BrandMark } from '../components/BrandMark';
import { RecoveryCodes } from '../components/RecoveryCodes';

type LoginResponse =
  | { status: 'authenticated'; user: CmsUser }
  | { status: 'two_factor_challenge' }
  | { status: 'two_factor_setup'; secret: string; otpauth: string };

type TwoFactorResponse = {
  status: 'authenticated';
  user: CmsUser;
  recovery_codes: string[] | null;
};

type Step =
  | { kind: 'credentials' }
  | { kind: 'challenge' }
  | { kind: 'setup'; secret: string; otpauth: string }
  | { kind: 'recovery'; codes: string[]; user: CmsUser };

export function LoginScreen() {
  const { setUser } = useAuth();
  const navigate = useNavigate();
  const [step, setStep] = useState<Step>({ kind: 'credentials' });
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [code, setCode] = useState('');
  const [useRecovery, setUseRecovery] = useState(false);
  const [error, setError] = useState('');

  function restart(message = '') {
    setStep({ kind: 'credentials' });
    setCode('');
    setUseRecovery(false);
    setError(message);
  }

  async function submitLogin(event: FormEvent) {
    event.preventDefault();
    setError('');
    try {
      const payload = await api<LoginResponse>('/login', {
        method: 'POST',
        body: JSON.stringify({ email, password }),
      });
      if (payload.status === 'authenticated') {
        setUser(payload.user);
        navigate('/');
        return;
      }
      setCode('');
      setStep(payload.status === 'two_factor_setup'
        ? { kind: 'setup', secret: payload.secret, otpauth: payload.otpauth }
        : { kind: 'challenge' });
    } catch (err) {
      setError(firstError(err));
    }
  }

  async function submitCode(event: FormEvent) {
    event.preventDefault();
    setError('');
    try {
      const payload = await api<TwoFactorResponse>('/login/two-factor', {
        method: 'POST',
        body: JSON.stringify(useRecovery ? { recovery_code: code } : { code }),
      });
      if (payload.recovery_codes) {
        setStep({ kind: 'recovery', codes: payload.recovery_codes, user: payload.user });
        return;
      }
      setUser(payload.user);
      navigate('/');
    } catch (err) {
      const message = firstError(err);
      if (/expirada|Muitas tentativas/.test(message)) {
        restart(message);
        return;
      }
      setError(message);
    }
  }

  function finish(user: CmsUser) {
    setUser(user);
    navigate('/');
  }

  return (
    <div className="login-screen">
      <div className="login-card">
        <BrandMark variant="login" />
        {step.kind === 'credentials' ? (
          <form onSubmit={(event) => void submitLogin(event)}>
            <h2>Entrar</h2>
            <p className="desc">Acesso ao painel do escritório.</p>
            {error ? <div className="form-error">{error}</div> : null}
            <div className="field">
              <label>E-mail</label>
              <input type="email" value={email} onChange={(event) => setEmail(event.target.value)} required />
            </div>
            <div className="field">
              <label>Senha</label>
              <input type="password" value={password} onChange={(event) => setPassword(event.target.value)} required />
            </div>
            <button className="btn btn-primary" type="submit">
              Entrar <ArrowRight size={16} />
            </button>
          </form>
        ) : null}

        {step.kind === 'setup' ? (
          <form onSubmit={(event) => void submitCode(event)}>
            <h2>Configure o 2FA</h2>
            <p className="desc">
              Escaneie o QR code com um app autenticador (Google Authenticator, 1Password, Authy) e digite o código gerado.
            </p>
            {error ? <div className="form-error">{error}</div> : null}
            <div className="two-factor-qr">
              <QRCodeSVG value={step.otpauth} size={176} marginSize={2} />
            </div>
            <p className="two-factor-secret">
              Chave manual: <code>{step.secret.match(/.{1,4}/g)?.join(' ')}</code>
            </p>
            <div className="field">
              <label>Código de 6 dígitos</label>
              <input
                inputMode="numeric"
                autoComplete="one-time-code"
                value={code}
                onChange={(event) => setCode(event.target.value)}
                autoFocus
                required
              />
            </div>
            <button className="btn btn-primary" type="submit">
              Ativar e entrar <ArrowRight size={16} />
            </button>
            <button className="btn-link" type="button" onClick={() => restart()}>Voltar</button>
          </form>
        ) : null}

        {step.kind === 'challenge' ? (
          <form onSubmit={(event) => void submitCode(event)}>
            <h2>Verificação</h2>
            <p className="desc">
              {useRecovery
                ? 'Digite um dos códigos de recuperação. Cada código funciona uma vez.'
                : 'Digite o código de 6 dígitos do seu app autenticador.'}
            </p>
            {error ? <div className="form-error">{error}</div> : null}
            <div className="field">
              <label>{useRecovery ? 'Código de recuperação' : 'Código'}</label>
              <input
                inputMode={useRecovery ? 'text' : 'numeric'}
                autoComplete="one-time-code"
                value={code}
                onChange={(event) => setCode(event.target.value)}
                autoFocus
                required
              />
            </div>
            <button className="btn btn-primary" type="submit">
              Entrar <ArrowRight size={16} />
            </button>
            <button
              className="btn-link"
              type="button"
              onClick={() => {
                setUseRecovery((current) => !current);
                setCode('');
                setError('');
              }}
            >
              {useRecovery ? 'Usar o app autenticador' : 'Perdi o acesso ao app'}
            </button>
          </form>
        ) : null}

        {step.kind === 'recovery' ? (
          <div>
            <h2>Códigos de recuperação</h2>
            <p className="desc">
              Guarde estes códigos em local seguro. Cada um permite entrar uma vez sem o app autenticador. Eles não serão exibidos de novo.
            </p>
            <RecoveryCodes codes={step.codes} />
            <button className="btn btn-primary" type="button" onClick={() => finish(step.user)}>
              Já guardei, entrar <ArrowRight size={16} />
            </button>
          </div>
        ) : null}
      </div>
      <DevCredit className="dev-credit--login" />
    </div>
  );
}
