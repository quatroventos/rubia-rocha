import { type FormEvent, useEffect, useState } from 'react';
import { AppShell } from '../components/AppShell';
import { LogosField } from '../components/LogosField';
import { MediaField } from '../components/MediaField';
import { api, firstError } from '../api';

type SiteForm = Record<string, string | number | boolean | null | undefined>;

type ClientLogo = { name?: string; image?: string; image_alt?: string };

type SitePayload = SiteForm & { plan_links?: Record<string, string> | null; client_logos?: ClientLogo[] | null };

type WhatsAppDefaults = {
  admin_template: string;
  visitor_template: string;
  variables: string[];
};

export function SiteScreen() {
  const [form, setForm] = useState<SiteForm>({});
  const [error, setError] = useState('');
  const [ok, setOk] = useState(false);
  const [whatsAppDefaults, setWhatsAppDefaults] = useState<WhatsAppDefaults | null>(null);
  const [whatsAppTest, setWhatsAppTest] = useState<{ ok: boolean; message: string } | null>(null);
  const [testing, setTesting] = useState(false);
  const [hostingPlans, setHostingPlans] = useState<Record<string, string>>({});
  const [planLinks, setPlanLinks] = useState<Record<string, string>>({});
  const [clientLogos, setClientLogos] = useState<ClientLogo[]>([]);

  useEffect(() => {
    void api<{
      data: SitePayload;
      whatsapp_defaults: WhatsAppDefaults;
      hosting_plans: Record<string, string>;
    }>('/site').then((payload) => {
      const { plan_links: links, client_logos: logos, ...data } = payload.data;
      setForm({
        ...data,
        smtp_password: '',
        evolution_api_key: '',
      });
      setPlanLinks(links ?? {});
      setClientLogos(logos ?? []);
      setWhatsAppDefaults(payload.whatsapp_defaults);
      setHostingPlans(payload.hosting_plans ?? {});
    });
  }, []);

  async function testWhatsApp() {
    setWhatsAppTest(null);
    setTesting(true);
    try {
      await api('/site/whatsapp/test', { method: 'POST' });
      setWhatsAppTest({ ok: true, message: 'Mensagem de teste enviada para o número de avisos.' });
    } catch (err) {
      setWhatsAppTest({ ok: false, message: firstError(err) });
    } finally {
      setTesting(false);
    }
  }

  async function save(event: FormEvent) {
    event.preventDefault();
    setError('');
    setOk(false);
    try {
      const body = {
        ...form,
        smtp_enabled: Boolean(form.smtp_enabled),
        smtp_port: form.smtp_port === '' || form.smtp_port === null || form.smtp_port === undefined
          ? null
          : Number(form.smtp_port),
        smtp_encryption: form.smtp_encryption || null,
        smtp_password: typeof form.smtp_password === 'string' && form.smtp_password.trim() !== ''
          ? form.smtp_password
          : null,
        whatsapp_enabled: Boolean(form.whatsapp_enabled),
        evolution_api_key: typeof form.evolution_api_key === 'string' && form.evolution_api_key.trim() !== ''
          ? form.evolution_api_key
          : null,
        plan_links: planLinks,
        client_logos: clientLogos.filter((logo) => logo.image),
      };
      const payload = await api<{ data: SitePayload }>('/site', {
        method: 'PUT',
        body: JSON.stringify(body),
      });
      const { plan_links: links, client_logos: logos, ...data } = payload.data;
      setForm({
        ...data,
        smtp_password: '',
        evolution_api_key: '',
      });
      setPlanLinks(links ?? {});
      setClientLogos(logos ?? []);
      setOk(true);
    } catch (err) {
      setError(firstError(err));
    }
  }

  const text = (name: string) => String(form[name] ?? '');

  const field = (name: string, label: string, type = 'text') => (
    <div className="field" key={name}>
      <label>{label}</label>
      <input
        type={type}
        value={text(name)}
        onChange={(event) => setForm({ ...form, [name]: event.target.value })}
      />
    </div>
  );

  return (
    <AppShell eyebrow="Configuração" title="Identidade do site">
      <form id="site-form" className="panel" onSubmit={(event) => void save(event)}>
        <div className="panel-body">
          {error ? <div className="form-error">{error}</div> : null}
          {ok ? <div className="form-success">Identidade atualizada.</div> : null}
          <div data-tour="site-identity">
            {field('name', 'Nome')}
            {field('tagline', 'Tagline')}
          </div>
          <div data-tour="site-logo">
            <MediaField label="Logo" value={text('logo_path')} onChange={(value) => setForm({ ...form, logo_path: value ?? '' })} />
            <MediaField label="Favicon" value={text('favicon_path')} onChange={(value) => setForm({ ...form, favicon_path: value ?? '' })} />
            <p className="desc">Ícone da aba do navegador. PNG ou SVG quadrado (32–180px) funciona melhor.</p>
          </div>
          <div data-tour="site-contacts">
            {field('phone', 'Telefone')}
            {field('phone_href', 'Link do telefone')}
            {field('email', 'E-mail')}
            {field('whatsapp_url', 'WhatsApp')}
            <div className="field">
              <label>Endereço</label>
              <textarea value={text('address')} onChange={(event) => setForm({ ...form, address: event.target.value })} />
            </div>
            <div className="field">
              <label>URL do mapa</label>
              <textarea value={text('map_embed_url')} onChange={(event) => setForm({ ...form, map_embed_url: event.target.value })} />
            </div>
          </div>
          <div data-tour="site-social">
            {field('instagram_url', 'Instagram')}
            {field('linkedin_url', 'LinkedIn')}
            {field('facebook_url', 'Facebook')}
          </div>
          <div data-tour="site-smtp" className="settings-block">
            <h3 className="settings-block__title">SMTP</h3>
            <p className="desc">Configuração geral de envio de e-mails do site. Tem prioridade sobre o .env.</p>
            <label className="check">
              <input
                type="checkbox"
                checked={Boolean(form.smtp_enabled)}
                onChange={(event) => setForm({ ...form, smtp_enabled: event.target.checked })}
              />
              Usar SMTP do painel
            </label>
            {field('smtp_host', 'Host')}
            {field('smtp_port', 'Porta', 'number')}
            {field('smtp_username', 'Usuário')}
            <div className="field">
              <label>Senha{form.smtp_password_set ? ' (deixe em branco para manter)' : ''}</label>
              <input
                type="password"
                autoComplete="new-password"
                value={text('smtp_password')}
                placeholder={form.smtp_password_set ? '••••••••' : ''}
                onChange={(event) => setForm({ ...form, smtp_password: event.target.value })}
              />
            </div>
            <div className="field">
              <label>Criptografia</label>
              <select
                value={text('smtp_encryption')}
                onChange={(event) => setForm({ ...form, smtp_encryption: event.target.value || null })}
              >
                <option value="">Nenhuma</option>
                <option value="tls">TLS</option>
                <option value="ssl">SSL</option>
              </select>
            </div>
            {field('smtp_from_address', 'Remetente (e-mail)', 'email')}
            {field('smtp_from_name', 'Remetente (nome)')}
          </div>
          <div className="settings-block">
            <h3 className="settings-block__title">Logos de clientes</h3>
            <p className="desc">
              Aparecem em todas as seções Logos do site, exceto nas que estiverem com "Usar logos próprios nesta seção" ligado.
            </p>
            <LogosField label="Logos" value={clientLogos} onChange={setClientLogos} />
          </div>
          <div className="settings-block">
            <h3 className="settings-block__title">Links de compra dos planos de hospedagem</h3>
            <p className="desc">
              Botão "Contratar" de cada plano em /hospedagem-de-sites. Em branco, vale o checkout padrão da Firefly Host.
            </p>
            {Object.entries(hostingPlans).map(([id, label]) => (
              <div className="field" key={id}>
                <label>{label}</label>
                <input
                  type="url"
                  value={planLinks[id] ?? ''}
                  placeholder="https://"
                  onChange={(event) => setPlanLinks({ ...planLinks, [id]: event.target.value })}
                />
              </div>
            ))}
          </div>
          <div className="settings-block">
            <h3 className="settings-block__title">WhatsApp (Evolution API)</h3>
            <p className="desc">
              A cada envio dos formulários de contato e de lead, avisa o seu número e manda uma confirmação para o WhatsApp do visitante. O e-mail continua sendo enviado normalmente.
            </p>
            <label className="check">
              <input
                type="checkbox"
                checked={Boolean(form.whatsapp_enabled)}
                onChange={(event) => setForm({ ...form, whatsapp_enabled: event.target.checked })}
              />
              Enviar avisos por WhatsApp
            </label>
            {field('evolution_url', 'URL da Evolution API', 'url')}
            <div className="field">
              <label>Apikey{form.evolution_api_key_set ? ' (deixe em branco para manter)' : ''}</label>
              <input
                type="password"
                autoComplete="new-password"
                value={text('evolution_api_key')}
                placeholder={form.evolution_api_key_set ? '••••••••' : ''}
                onChange={(event) => setForm({ ...form, evolution_api_key: event.target.value })}
              />
            </div>
            {field('evolution_instance', 'Nome da instância')}
            {field('whatsapp_notify_number', 'Número que recebe os avisos (com DDD)', 'tel')}
            <div className="field">
              <label>Mensagem para você</label>
              <textarea
                rows={6}
                value={text('whatsapp_admin_template')}
                placeholder={whatsAppDefaults?.admin_template}
                onChange={(event) => setForm({ ...form, whatsapp_admin_template: event.target.value })}
              />
            </div>
            <div className="field">
              <label>Mensagem para o visitante</label>
              <textarea
                rows={3}
                value={text('whatsapp_visitor_template')}
                placeholder={whatsAppDefaults?.visitor_template}
                onChange={(event) => setForm({ ...form, whatsapp_visitor_template: event.target.value })}
              />
            </div>
            <p className="desc">
              Em branco, vale o texto padrão mostrado no campo. Variáveis: {whatsAppDefaults?.variables.map((name) => `{${name}}`).join(' ')}
            </p>
            {whatsAppTest ? (
              <div className={whatsAppTest.ok ? 'form-success' : 'form-error'}>{whatsAppTest.message}</div>
            ) : null}
            <button className="btn btn-secondary" type="button" disabled={testing} onClick={() => void testWhatsApp()}>
              {testing ? 'Testando…' : 'Testar conexão'}
            </button>
            <p className="desc">O teste usa os dados já salvos: salve antes de testar.</p>
          </div>
          <div className="form-actions" data-tour="site-save">
            <button className="btn btn-primary" type="submit">Salvar</button>
          </div>
        </div>
      </form>
    </AppShell>
  );
}

type CacheStatus = {
  store: string;
  driver: string;
  redis: boolean;
  ttl: number;
  ttl_presets: Record<string, string>;
  flushed_at: string | null;
  keys: Record<string, boolean>;
};

const keyLabels: Record<string, string> = {
  site: 'Identidade e settings',
  'menus:locale': 'Menus',
  certs: 'Certificações',
  'form:contact': 'Formulário de contato',
};

export function CacheScreen() {
  const [status, setStatus] = useState<CacheStatus | null>(null);
  const [ttl, setTtl] = useState(3600);
  const [error, setError] = useState('');
  const [ok, setOk] = useState('');
  const [busy, setBusy] = useState(false);

  const load = (payload: CacheStatus) => {
    setStatus(payload);
    setTtl(payload.ttl);
  };

  useEffect(() => {
    void api<{ data: CacheStatus }>('/cache').then((payload) => load(payload.data));
  }, []);

  async function saveTtl(event: FormEvent) {
    event.preventDefault();
    setError('');
    setOk('');
    setBusy(true);
    try {
      const payload = await api<{ data: CacheStatus }>('/cache', {
        method: 'PUT',
        body: JSON.stringify({ ttl }),
      });
      load(payload.data);
      setOk('TTL atualizado e cache limpo.');
    } catch (err) {
      setError(firstError(err));
    } finally {
      setBusy(false);
    }
  }

  async function run(path: '/cache/flush' | '/cache/warm', message: string) {
    setError('');
    setOk('');
    setBusy(true);
    try {
      const payload = await api<{ data: CacheStatus }>(path, { method: 'POST' });
      load(payload.data);
      setOk(message);
    } catch (err) {
      setError(firstError(err));
    } finally {
      setBusy(false);
    }
  }

  const keys = status?.keys ?? {};

  return (
    <AppShell
      eyebrow="Sistema"
      title="Cache"
      subtitle="Camada Redis das opções públicas: identidade, menu, certificações e formulários."
    >
      <div className="stat-grid" data-tour="cache-stats">
        <article className="stat-card">
          <label>Store</label>
          <strong>{status?.store ?? '—'}</strong>
        </article>
        <article className="stat-card">
          <label>Redis</label>
          <strong>{status ? (status.redis ? 'Conectado' : 'Off') : '—'}</strong>
        </article>
        <article className="stat-card">
          <label>Última limpeza</label>
          <strong>{status?.flushed_at ? new Date(status.flushed_at).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '—'}</strong>
        </article>
      </div>

      <form className="panel" onSubmit={(event) => void saveTtl(event)}>
        <div className="panel-body">
          {error ? <div className="form-error">{error}</div> : null}
          {ok ? <div className="form-success">{ok}</div> : null}
          <div className="field" data-tour="cache-ttl">
            <label>Tempo de vida</label>
            <select value={ttl} onChange={(event) => setTtl(Number(event.target.value))}>
              {Object.entries(status?.ttl_presets ?? {
                300: '5 minutos',
                900: '15 minutos',
                3600: '1 hora',
                21600: '6 horas',
                86400: '24 horas',
              }).map(([value, label]) => (
                <option key={value} value={value}>{label}</option>
              ))}
            </select>
          </div>
          <div className="form-actions">
            <button className="btn btn-secondary" type="button" data-tour="cache-flush" disabled={busy} onClick={() => void run('/cache/flush', 'Cache limpo.')}>
              Limpar
            </button>
            <button className="btn btn-secondary" type="button" data-tour="cache-warm" disabled={busy} onClick={() => void run('/cache/warm', 'Cache recarregado.')}>
              Recarregar
            </button>
            <button className="btn btn-primary" type="submit" disabled={busy}>Salvar TTL</button>
          </div>
          <ol className="rank-list" data-tour="cache-keys">
            {Object.entries(keyLabels).map(([key, label]) => (
              <li key={key}>
                <div>
                  <strong>{label}</strong>
                  <span>{key}</span>
                </div>
                <em>{keys[key] ? 'quente' : 'frio'}</em>
              </li>
            ))}
          </ol>
        </div>
      </form>
    </AppShell>
  );
}

export function TagsScreen() {
  const [form, setForm] = useState({ tag_header: '', tag_body: '', tag_footer: '' });
  const [error, setError] = useState('');
  const [ok, setOk] = useState(false);

  useEffect(() => {
    void api<{ data: typeof form }>('/tags').then((payload) => setForm({
      tag_header: payload.data.tag_header ?? '',
      tag_body: payload.data.tag_body ?? '',
      tag_footer: payload.data.tag_footer ?? '',
    }));
  }, []);

  async function save(event: FormEvent) {
    event.preventDefault();
    setError('');
    try {
      await api('/tags', { method: 'PUT', body: JSON.stringify(form) });
      setOk(true);
    } catch (err) {
      setError(firstError(err));
    }
  }

  return (
    <AppShell eyebrow="Sistema" title="Tags de marketing">
      <form id="tags-form" className="panel" onSubmit={(event) => void save(event)}>
        <div className="panel-body">
          {error ? <div className="form-error">{error}</div> : null}
          {ok ? <div className="form-success">Tags atualizadas.</div> : null}
          <div className="field" data-tour="tags-header">
            <label>header</label>
            <textarea rows={8} value={form.tag_header} onChange={(event) => setForm({ ...form, tag_header: event.target.value })} />
          </div>
          <div className="field" data-tour="tags-body">
            <label>body</label>
            <textarea rows={8} value={form.tag_body} onChange={(event) => setForm({ ...form, tag_body: event.target.value })} />
          </div>
          <div className="field" data-tour="tags-footer">
            <label>footer</label>
            <textarea rows={8} value={form.tag_footer} onChange={(event) => setForm({ ...form, tag_footer: event.target.value })} />
          </div>
          <div className="form-actions" data-tour="tags-save">
            <button className="btn btn-primary" type="submit">Salvar</button>
          </div>
        </div>
      </form>
    </AppShell>
  );
}
