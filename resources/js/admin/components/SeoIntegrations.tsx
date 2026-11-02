import { type FormEvent, type ReactNode, useEffect, useState } from 'react';
import { CheckCircle2, RotateCcw, XCircle } from 'lucide-react';
import { api, firstError } from '../api';

type Secret = 'openseo_api_key' | 'openai_api_key';

type Values = {
  openseo_url: string | null;
  openseo_host_header: string | null;
  openseo_project_id: string | null;
  openseo_site_url: string | null;
  openseo_location_code: number | null;
  openseo_language_code: string | null;
  openseo_paid_enabled: boolean | null;
  openai_model: string | null;
  prompt_writer: string | null;
  prompt_seo_title: string | null;
  prompt_seo_description: string | null;
  prompt_keyword: string | null;
};

type Payload = {
  values: Values;
  fallbacks: Partial<Record<keyof Values, string | number | boolean | null>>;
  secrets: Record<Secret, { set: boolean; env: boolean }>;
  prompt_defaults: { prompt_writer: string };
  paid: boolean;
};

type Service = 'openseo' | 'openai';

type TestResult = {
  ok: boolean;
  message: string;
  projects?: { id: string; name: string | null; domain: string | null }[];
};

const emptySecrets: Record<Secret, string> = { openseo_api_key: '', openai_api_key: '' };

export function SeoIntegrations({ canEdit }: { canEdit: boolean }) {
  const [payload, setPayload] = useState<Payload | null>(null);
  const [form, setForm] = useState<Values | null>(null);
  const [secrets, setSecrets] = useState<Record<Secret, string>>(emptySecrets);
  const [clear, setClear] = useState<Secret[]>([]);
  const [tests, setTests] = useState<Partial<Record<Service, TestResult>>>({});
  const [testing, setTesting] = useState<Service | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [ok, setOk] = useState('');

  function apply(data: Payload) {
    setPayload(data);
    setForm({ ...data.values, prompt_writer: data.values.prompt_writer ?? data.prompt_defaults.prompt_writer });
    setSecrets(emptySecrets);
    setClear([]);
  }

  useEffect(() => {
    void api<{ data: Payload }>('/seo/integrations').then((response) => apply(response.data)).catch((err) => setError(firstError(err)));
  }, []);

  async function persist(): Promise<boolean> {
    if (!form) {
      return false;
    }
    setBusy(true);
    setError('');
    setOk('');
    try {
      const response = await api<{ data: Payload }>('/seo/integrations', {
        method: 'PUT',
        body: JSON.stringify({
          ...form,
          openseo_paid_enabled: Boolean(form.openseo_paid_enabled ?? payload?.paid),
          secrets: Object.fromEntries(Object.entries(secrets).filter(([, value]) => value.trim() !== '')),
          clear,
        }),
      });
      apply(response.data);
      return true;
    } catch (err) {
      setError(firstError(err));
      return false;
    } finally {
      setBusy(false);
    }
  }

  async function save(event: FormEvent) {
    event.preventDefault();
    if (await persist()) {
      setOk('Integrações salvas. Os workers da fila reiniciam sozinhos para pegar as chaves novas.');
    }
  }

  async function test(service: Service) {
    if (!(await persist())) {
      return;
    }
    setTesting(service);
    try {
      const result = await api<TestResult>(`/seo/integrations/test/${service}`, { method: 'POST' });
      setTests((current) => ({ ...current, [service]: result }));
    } catch (err) {
      setTests((current) => ({ ...current, [service]: { ok: false, message: firstError(err) } }));
    } finally {
      setTesting(null);
    }
  }

  if (!form || !payload) {
    return error ? <div className="form-error">{error}</div> : <div className="oseo-loading"><div className="spinner" /></div>;
  }

  const set = <K extends keyof Values>(key: K, value: Values[K]) => setForm({ ...form, [key]: value });
  const fallback = (key: keyof Values) => {
    const value = payload.fallbacks[key];
    return value === null || value === undefined || value === '' ? '' : `Padrão do .env: ${String(value)}`;
  };

  const text = (key: keyof Values, label: string, hint?: ReactNode, placeholder?: string) => (
    <div className="field">
      <label>{label}</label>
      <input
        value={(form[key] as string | number | null) ?? ''}
        disabled={!canEdit}
        placeholder={placeholder ?? fallback(key)}
        onChange={(event) => set(key, (event.target.value === '' ? null : event.target.value) as Values[typeof key])}
      />
      {hint ? <p className="hint">{hint}</p> : null}
    </div>
  );

  const secret = (key: Secret, label: string, hint?: ReactNode) => {
    const state = payload.secrets[key];
    const removing = clear.includes(key);
    const placeholder = removing
      ? 'Será removida ao salvar'
      : state.set ? '•••••••• salva no CMS (deixe vazio para manter)' : state.env ? 'Definida no .env (preencha para sobrescrever)' : 'Não definida';
    return (
      <div className="field">
        <label>{label}</label>
        <div className="seo-int-secret">
          <input type="password" autoComplete="new-password" value={secrets[key]} disabled={!canEdit} placeholder={placeholder} onChange={(event) => setSecrets({ ...secrets, [key]: event.target.value })} />
          {canEdit && state.set ? (
            <button className="btn btn-secondary btn-sm" type="button" onClick={() => setClear(removing ? clear.filter((item) => item !== key) : [...clear, key])}>
              {removing ? 'Manter' : 'Remover'}
            </button>
          ) : null}
        </div>
        {hint ? <p className="hint">{hint}</p> : null}
      </div>
    );
  };

  const testBlock = (service: Service, label: string) => {
    const result = tests[service];
    return (
      <div className="seo-int-test">
        {canEdit ? (
          <button className="btn btn-secondary btn-sm" type="button" disabled={busy || testing !== null} onClick={() => void test(service)}>
            {testing === service ? 'Testando...' : `Salvar e testar ${label}`}
          </button>
        ) : null}
        {result ? (
          <p className={`seo-int-result ${result.ok ? 'is-ok' : 'is-bad'}`}>
            {result.ok ? <CheckCircle2 size={14} /> : <XCircle size={14} />}
            {result.message}
          </p>
        ) : null}
      </div>
    );
  };

  const projects = tests.openseo?.projects ?? [];
  const paid = Boolean(form.openseo_paid_enabled ?? payload.paid);

  return (
    <form id="seo-integrations-form" className="seo-int" onSubmit={(event) => void save(event)}>
      {error ? <div className="form-error">{error}</div> : null}
      {ok ? <div className="form-success">{ok}</div> : null}

      <div className="panel" data-tour="seo-int-openseo">
        <div className="panel-head">
          <h3>OpenSEO</h3>
          <p>MCP com auditoria, Search Console, palavras salvas, indexação e visibilidade em IA. Ler esses dados não custa nada.</p>
        </div>
        <div className="panel-body">
          <div className="seo-int-grid">
            {text('openseo_url', 'URL do MCP', 'De dentro do Docker local: http://host.docker.internal:3002/mcp. No EasyPanel, o endereço interno do serviço + /mcp.')}
            {secret('openseo_api_key', 'Chave de API', 'Só para o OpenSEO hospedado ou self-hosted com autenticação. Sem auth, deixe vazio.')}
            {text('openseo_host_header', 'Host header', 'Só quando o OpenSEO roda em local_noauth e recusa o host do Docker (ex.: 127.0.0.1:3002).')}
            <div className="field">
              <label>ID do projeto</label>
              {projects.length > 0 ? (
                <select value={form.openseo_project_id ?? ''} disabled={!canEdit} onChange={(event) => set('openseo_project_id', event.target.value || null)}>
                  <option value="">{fallback('openseo_project_id') || 'Escolha um projeto'}</option>
                  {projects.map((project) => <option key={project.id} value={project.id}>{project.name ?? project.id}{project.domain ? ` (${project.domain})` : ''}</option>)}
                </select>
              ) : (
                <input value={form.openseo_project_id ?? ''} disabled={!canEdit} placeholder={fallback('openseo_project_id')} onChange={(event) => set('openseo_project_id', event.target.value || null)} />
              )}
              <p className="hint">Teste a conexão para escolher na lista.</p>
            </div>
            {text('openseo_site_url', 'URL pública do site', 'Base das URLs enviadas ao Search Console e à indexação.')}
            {text('openseo_location_code', 'Código do país (DataForSEO)', 'Brasil = 2076. Vazio usa o padrão do projeto.')}
            {text('openseo_language_code', 'Idioma', 'Ex.: pt. Vazio usa o padrão do projeto.')}
          </div>
          <label className="check seo-int-paid">
            <input type="checkbox" checked={paid} disabled={!canEdit} onChange={(event) => set('openseo_paid_enabled', event.target.checked)} />
            Liberar consultas pagas (DataForSEO)
          </label>
          <p className="hint">
            {paid
              ? 'Pesquisa de ideias, volume e dificuldade no editor e visão do domínio ficam disponíveis e gastam créditos (sempre pedem confirmação).'
              : 'Desligado: nada no CMS gasta créditos. Volume, dificuldade, pesquisa de ideias e visão do domínio somem.'}
          </p>
          {testBlock('openseo', 'OpenSEO')}
        </div>
      </div>

      <div className="panel" data-tour="seo-int-ai">
        <div className="panel-head">
          <h3>IA de SEO</h3>
          <p>Chave e modelo da OpenAI usados para escrever títulos, descriptions, seções, cases e traduções, e os prompts das correções de SEO.</p>
        </div>
        <div className="panel-body">
          <div className="seo-int-grid">
            {secret('openai_api_key', 'Chave da OpenAI')}
            {text('openai_model', 'Modelo')}
          </div>
          {testBlock('openai', 'OpenAI')}
          <div className="field">
            <div className="seo-int-label">
              <label>Prompt de sistema (estilo de escrita)</label>
              {canEdit && form.prompt_writer !== payload.prompt_defaults.prompt_writer ? (
                <button className="btn btn-secondary btn-sm" type="button" onClick={() => set('prompt_writer', payload.prompt_defaults.prompt_writer)}>
                  <RotateCcw size={12} /> Restaurar padrão
                </button>
              ) : null}
            </div>
            <textarea className="seo-int-prompt" rows={16} maxLength={8000} disabled={!canEdit} value={form.prompt_writer ?? ''} onChange={(event) => set('prompt_writer', event.target.value)} />
            <p className="hint">Vale para toda escrita com IA no editor. O guia de escrita do site continua anexado no fim.</p>
          </div>
          <div className="field">
            <label>Instruções extras para o título SEO</label>
            <textarea rows={3} maxLength={2000} disabled={!canEdit} value={form.prompt_seo_title ?? ''} placeholder="Ex.: prefira termos locais como Curitiba quando a página for de serviço." onChange={(event) => set('prompt_seo_title', event.target.value || null)} />
            <p className="hint">Somadas às regras fixas de tamanho e palavra-chave.</p>
          </div>
          <div className="field">
            <label>Instruções extras para a meta description</label>
            <textarea rows={3} maxLength={2000} disabled={!canEdit} value={form.prompt_seo_description ?? ''} onChange={(event) => set('prompt_seo_description', event.target.value || null)} />
          </div>
          <div className="field">
            <label>Instruções extras para as correções de palavra-chave</label>
            <textarea rows={3} maxLength={2000} disabled={!canEdit} value={form.prompt_keyword ?? ''} onChange={(event) => set('prompt_keyword', event.target.value || null)} />
            <p className="hint">Usadas quando a análise SEO reescreve um trecho da página para encaixar a palavra-chave.</p>
          </div>
        </div>
      </div>

      {canEdit ? (
        <div className="form-actions">
          <button className="btn btn-primary" type="submit" disabled={busy}>{busy ? 'Salvando...' : 'Salvar integrações'}</button>
        </div>
      ) : null}
    </form>
  );
}
