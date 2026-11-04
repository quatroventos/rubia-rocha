import { type FormEvent, useEffect, useMemo, useState } from 'react';
import { AppShell } from '../components/AppShell';
import { BatchRunner } from '../components/BatchRunner';
import { GooglePreview } from '../components/GooglePreview';
import { MediaField } from '../components/MediaField';
import { SeoLength } from '../components/SeoLength';
import { SeoIntegrations } from '../components/SeoIntegrations';
import { SeoPerformance } from '../components/SeoPerformance';
import { api, firstError } from '../api';
import { useAuth } from '../auth';
import { useTour } from '../tour/TourProvider';
import { composeSeoTitle, resolveSeoDescription, type SeoDefaults, type SeoPagePreview } from '../seo';

type SitemapEntry = {
  source: string;
  slug: string | null;
  path: string;
  loc: string;
  lastmod: string | null;
};

type SeoPayload = SeoDefaults & {
  sitemap_extra: string[];
  sitemap_url: string;
  robots_url: string;
  llms_url: string;
  robots_extra: string;
  llms_summary: string;
  llms_details: string;
  robots_preview: string;
  llms_preview: string;
  search_console_url: string;
  pages: SeoPagePreview[];
  entries: SitemapEntry[];
};

const emptyForm: SeoDefaults = {
  seo_separator: '|',
  seo_title_template: '%page% %sep% %site%',
  seo_description: '',
  seo_og_image: '',
  seo_og_image_alt: '',
  seo_robots: 'index, follow, max-image-preview:large',
  og_site_name: '',
  og_locale: 'pt_BR',
  twitter_card: 'summary_large_image',
};

type SeoTab = 'performance' | 'settings' | 'integrations';

const TAB_PARAM: Record<SeoTab, string> = { performance: 'desempenho', settings: 'configuracao', integrations: 'integracoes' };

export function SeoScreen() {
  const { user } = useAuth();
  const { registerActions } = useTour();
  const [tab, setTab] = useState<SeoTab>(() => {
    const param = new URLSearchParams(window.location.search).get('aba');
    return (Object.keys(TAB_PARAM) as SeoTab[]).find((key) => TAB_PARAM[key] === param) ?? 'performance';
  });
  const [form, setForm] = useState<SeoDefaults>(emptyForm);
  const [extra, setExtra] = useState('');
  const [sitemapUrl, setSitemapUrl] = useState('');
  const [crawler, setCrawler] = useState({ robots_extra: '', llms_summary: '', llms_details: '' });
  const [crawlerFiles, setCrawlerFiles] = useState({ robots_url: '', llms_url: '', robots_preview: '', llms_preview: '' });
  const [searchConsoleUrl, setSearchConsoleUrl] = useState('https://search.google.com/search-console');
  const [pages, setPages] = useState<SeoPagePreview[]>([]);
  const [entries, setEntries] = useState<SitemapEntry[]>([]);
  const [previewSlug, setPreviewSlug] = useState('home');
  const [fillEmpty, setFillEmpty] = useState(false);
  const [copied, setCopied] = useState(false);
  const [error, setError] = useState('');
  const [ok, setOk] = useState('');
  const [loading, setLoading] = useState(true);

  async function load(message?: string) {
    const payload = await api<{ data: SeoPayload }>('/seo');
    applyPayload(payload.data);
    if (message) {
      setOk(message);
    }
  }

  function applyPayload(data: SeoPayload) {
    setForm({
      seo_separator: data.seo_separator,
      seo_title_template: data.seo_title_template,
      seo_description: data.seo_description ?? '',
      seo_og_image: data.seo_og_image ?? '',
      seo_og_image_alt: data.seo_og_image_alt ?? '',
      seo_robots: data.seo_robots,
      og_site_name: data.og_site_name,
      og_locale: data.og_locale,
      twitter_card: data.twitter_card,
    });
    setExtra((data.sitemap_extra ?? []).join('\n'));
    setSitemapUrl(data.sitemap_url);
    setCrawler({ robots_extra: data.robots_extra ?? '', llms_summary: data.llms_summary ?? '', llms_details: data.llms_details ?? '' });
    setCrawlerFiles({ robots_url: data.robots_url, llms_url: data.llms_url, robots_preview: data.robots_preview, llms_preview: data.llms_preview });
    setSearchConsoleUrl(data.search_console_url);
    setPages(data.pages ?? []);
    setEntries(data.entries ?? []);
    setPreviewSlug((current) => {
      if (data.pages.some((page) => page.slug === current)) {
        return current;
      }

      return data.pages[0]?.slug ?? 'home';
    });
  }

  useEffect(() => {
    void load().catch((err) => setError(firstError(err))).finally(() => setLoading(false));
  }, []);

  function changeTab(next: SeoTab) {
    setTab(next);
    const url = new URL(window.location.href);
    url.searchParams.set('aba', TAB_PARAM[next]);
    if (next !== 'performance') {
      url.searchParams.delete('painel');
    }
    window.history.replaceState(window.history.state, '', url);
  }

  useEffect(() => registerActions({
    'seo.showPerformance': () => changeTab('performance'),
    'seo.showSettings': () => changeTab('settings'),
    'seo.showIntegrations': () => changeTab('integrations'),
  }), [registerActions]);

  const sitemapPaths = useMemo(() => entries.map((entry) => entry.path), [entries]);

  const previewPage = useMemo(
    () => pages.find((page) => page.slug === previewSlug) ?? pages[0] ?? null,
    [pages, previewSlug],
  );

  const preview = useMemo(() => {
    const slug = previewPage?.slug ?? 'home';
    const defaults = {
      ...form,
      seo_description: form.seo_description || null,
      seo_og_image: form.seo_og_image || null,
      seo_og_image_alt: form.seo_og_image_alt || null,
    };

    return {
      slug,
      title: composeSeoTitle(previewPage?.seo_title ?? form.og_site_name, defaults),
      description: resolveSeoDescription(previewPage?.seo_description, defaults),
      canonical: previewPage?.canonical ?? sitemapUrl.replace(/\/sitemap\.xml$/, previewPage?.path ?? '/'),
    };
  }, [form, previewPage, sitemapUrl]);

  async function copySitemap() {
    try {
      if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(sitemapUrl);
      } else {
        throw new Error('clipboard');
      }
      setCopied(true);
      window.setTimeout(() => setCopied(false), 2000);
    } catch {
      setError('Não foi possível copiar o link. Selecione o campo e copie manualmente.');
    }
  }

  async function generateSitemap() {
    setError('');
    setOk('');
    try {
      await load('Sitemap atualizado. Copie o link e envie no Search Console.');
    } catch (err) {
      setError(firstError(err));
    }
  }

  async function save(event: FormEvent) {
    event.preventDefault();
    setError('');
    setOk('');
    try {
      const payload = await api<{ data: SeoPayload }>('/seo', {
        method: 'PUT',
        body: JSON.stringify({
          ...form,
          seo_description: form.seo_description || null,
          seo_og_image: form.seo_og_image || null,
          seo_og_image_alt: form.seo_og_image_alt || null,
          sitemap_extra: extra.split('\n').map((item) => item.trim()).filter(Boolean),
          robots_extra: crawler.robots_extra || null,
          llms_summary: crawler.llms_summary || null,
          llms_details: crawler.llms_details || null,
          fill_empty_pages: fillEmpty,
        }),
      });
      applyPayload(payload.data);
      setOk(fillEmpty ? 'SEO salvo. Campos vazios das páginas foram preenchidos.' : 'SEO salvo. Páginas vazias herdam este padrão.');
    } catch (err) {
      setError(firstError(err));
    }
  }

  const field = (name: keyof SeoDefaults, label: string) => (
    <div className="field">
      <label>{label}</label>
      <input value={form[name] ?? ''} onChange={(event) => setForm({ ...form, [name]: event.target.value })} />
    </div>
  );

  if (loading) {
    return (
      <AppShell eyebrow="Configuração" title="SEO">
        <div className="auth-loading"><div className="spinner" /></div>
      </AppShell>
    );
  }

  return (
    <AppShell
      eyebrow="Configuração"
      title="SEO do site"
      subtitle={{
        performance: 'Como o site vai no Google e nas IAs, com dados do OpenSEO.',
        settings: 'Campos vazios nas páginas herdam este padrão. O título segue o template, salvo se a página já incluir o nome do site.',
        integrations: 'Chaves, endereços e prompts do OpenSEO e da IA. Campo vazio usa o valor do .env.',
      }[tab]}
    >
      <div className="resource-tabs seo-tabs" data-tour="seo-tabs" role="tablist" aria-label="Seções de SEO">
        <button type="button" role="tab" aria-selected={tab === 'performance'} className={tab === 'performance' ? 'is-on' : ''} onClick={() => changeTab('performance')}>Desempenho</button>
        <button type="button" role="tab" aria-selected={tab === 'settings'} className={tab === 'settings' ? 'is-on' : ''} onClick={() => changeTab('settings')}>Configuração</button>
        <button type="button" role="tab" aria-selected={tab === 'integrations'} className={tab === 'integrations' ? 'is-on' : ''} onClick={() => changeTab('integrations')}>Integrações</button>
      </div>
      {tab === 'performance' ? <SeoPerformance sitemapPaths={sitemapPaths} canEdit={Boolean(user?.abilities.seo)} onOpenIntegrations={() => changeTab('integrations')} /> : null}
      {tab === 'integrations' ? <SeoIntegrations canEdit={Boolean(user?.abilities.seo)} /> : null}
      {tab === 'settings' && error ? <div className="form-error">{error}</div> : null}
      {tab === 'settings' && ok ? <div className="form-success">{ok}</div> : null}
      {tab === 'settings' ? (
        <div className="seo-layout">
          <form id="seo-form" className="seo-main" onSubmit={(event) => void save(event)}>
            <div className="panel" data-tour="seo-sitemap">
              <div className="panel-head">
                <h3>Sitemap</h3>
                <p>O XML é gerado com as páginas publicadas. Copie o link e envie no Google Search Console.</p>
              </div>
              <div className="panel-body">
                <div className="field">
                  <label>Link do sitemap</label>
                  <div className="seo-copy" data-tour="seo-copy">
                    <input value={sitemapUrl} readOnly />
                    <button className="btn btn-secondary" type="button" onClick={() => void copySitemap()}>
                      {copied ? 'Copiado' : 'Copiar link'}
                    </button>
                  </div>
                </div>
                <div className="toolbar">
                  <button className="btn btn-primary" type="button" data-tour="seo-generate" onClick={() => void generateSitemap()}>Gerar sitemap</button>
                  <a className="btn btn-secondary" href={sitemapUrl} target="_blank" rel="noreferrer">Abrir XML</a>
                  <a className="btn btn-secondary" href={searchConsoleUrl} target="_blank" rel="noreferrer">Search Console</a>
                </div>
                <div className="field" data-tour="seo-extra">
                  <label>URLs extras</label>
                  <textarea rows={5} value={extra} onChange={(event) => setExtra(event.target.value)} placeholder="/pagina-extra" />
                  <p className="hint">Uma URL por linha. Entram no sitemap.xml público.</p>
                </div>
                <div className="table-panel seo-entries">
                  <table>
                    <thead>
                      <tr>
                        <th>URL</th>
                        <th>Origem</th>
                      </tr>
                    </thead>
                    <tbody>
                      {entries.map((entry) => (
                        <tr key={`${entry.source}-${entry.loc}`}>
                          <td>{entry.loc}</td>
                          <td>{entry.source === 'page' ? 'Página' : 'Extra'}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <div className="panel">
              <div className="panel-head">
                <h3>robots.txt e llms.txt</h3>
                <p>Gerados sozinhos sempre que uma página, o menu ou as configurações mudam. Os campos abaixo entram no arquivo gerado; salve para atualizar.</p>
              </div>
              <div className="panel-body">
                <div className="field">
                  <label htmlFor="seo-robots-extra">Regras extras do robots.txt</label>
                  <textarea
                    id="seo-robots-extra"
                    rows={4}
                    value={crawler.robots_extra}
                    placeholder={'User-agent: *\nDisallow: /pasta-privada/'}
                    onChange={(event) => setCrawler({ ...crawler, robots_extra: event.target.value })}
                  />
                  <p className="hint">Entram antes da linha do sitemap. A liberação geral e a dos robôs de IA já vêm no arquivo.</p>
                </div>
                <div className="crawler-preview">
                  <div className="crawler-preview-head">
                    <span>robots.txt atual</span>
                    <a href={crawlerFiles.robots_url} target="_blank" rel="noreferrer">Abrir</a>
                  </div>
                  <pre>{crawlerFiles.robots_preview}</pre>
                </div>
                <div className="field">
                  <label htmlFor="seo-llms-summary">Resumo do llms.txt</label>
                  <textarea
                    id="seo-llms-summary"
                    rows={3}
                    maxLength={500}
                    value={crawler.llms_summary}
                    placeholder={form.seo_description ?? ''}
                    onChange={(event) => setCrawler({ ...crawler, llms_summary: event.target.value })}
                  />
                  <p className="hint">Uma ou duas frases sobre o negócio, lidas primeiro pelas IAs. Vazio usa a description padrão.</p>
                </div>
                <div className="field">
                  <label htmlFor="seo-llms-details">Informações adicionais do llms.txt</label>
                  <textarea
                    id="seo-llms-details"
                    rows={6}
                    value={crawler.llms_details}
                    placeholder="Atendo em Curitiba e região. Ensaios em estúdio ou ao ar livre..."
                    onChange={(event) => setCrawler({ ...crawler, llms_details: event.target.value })}
                  />
                  <p className="hint">Texto livre em Markdown, entre o resumo e a lista de páginas. As páginas publicadas e o contato entram sozinhos.</p>
                </div>
                <div className="crawler-preview">
                  <div className="crawler-preview-head">
                    <span>llms.txt atual</span>
                    <a href={crawlerFiles.llms_url} target="_blank" rel="noreferrer">Abrir</a>
                  </div>
                  <pre>{crawlerFiles.llms_preview}</pre>
                </div>
              </div>
            </div>

            <div className="panel" data-tour="seo-defaults">
              <div className="panel-head">
                <h3>Padrão das páginas</h3>
                <p>Template de título, description, robots e Open Graph usados quando a página não define o campo.</p>
              </div>
              <div className="panel-body">
                {field('og_site_name', 'Nome do site')}
                {field('seo_separator', 'Separador')}
                {field('seo_title_template', 'Template do título')}
                <p className="hint">Tokens: %page% título da página, %sep% separador, %site% nome do site.</p>
                <div className="field">
                  <label>Description padrão</label>
                  <textarea
                    rows={4}
                    maxLength={320}
                    value={form.seo_description ?? ''}
                    onChange={(event) => setForm({ ...form, seo_description: event.target.value })}
                  />
                  <SeoLength kind="description" text={form.seo_description} />
                </div>
                <MediaField
                  label="OG image padrão"
                  value={form.seo_og_image || null}
                  onChange={(value) => setForm({ ...form, seo_og_image: value ?? '' })}
                />
                {field('seo_og_image_alt', 'Alt da OG image')}
                <div className="field">
                  <label>Cards de OG de todas as páginas e cases</label>
                  <BatchRunner endpoint="/og-image/all" startLabel="Gerar todas as OG images" unit="imagens">
                    <p className="hint">Gera o card (logo, fundo do hero, título e endereço) de cada página e case publicado, em todos os idiomas, e substitui a OG atual, inclusive as enviadas à mão. Depois disso, cada card é refeito sozinho quando o título da página muda.</p>
                  </BatchRunner>
                </div>
                {field('seo_robots', 'Robots padrão')}
                {field('og_locale', 'og:locale')}
                <div className="field">
                  <label>Twitter card</label>
                  <select value={form.twitter_card} onChange={(event) => setForm({ ...form, twitter_card: event.target.value })}>
                    <option value="summary_large_image">summary_large_image</option>
                    <option value="summary">summary</option>
                  </select>
                </div>
                <label className="check" data-tour="seo-fill">
                  <input type="checkbox" checked={fillEmpty} onChange={(event) => setFillEmpty(event.target.checked)} />
                  Preencher agora os campos vazios das páginas com este padrão
                </label>
              </div>
            </div>
          </form>

          <aside className="seo-side" data-tour="seo-preview">
            <div className="seo-side-actions form-actions" data-tour="seo-save">
              <button className="btn btn-primary" type="submit" form="seo-form">Salvar</button>
            </div>
            <div className="panel">
              <div className="panel-head">
                <h3>Prévia no Google</h3>
                <p>Como o resultado tende a aparecer na busca, com o padrão atual.</p>
              </div>
              <div className="panel-body">
                <div className="field">
                  <label>Página</label>
                  <select value={preview.slug} onChange={(event) => setPreviewSlug(event.target.value)}>
                    {pages.map((page) => (
                      <option key={page.slug} value={page.slug}>{page.seo_title || page.slug}</option>
                    ))}
                  </select>
                </div>
                <GooglePreview
                  title={preview.title}
                  description={preview.description}
                  canonical={preview.canonical}
                  slug={preview.slug}
                />
              </div>
            </div>
          </aside>
        </div>
      ) : null}
    </AppShell>
  );
}
