import { type ReactNode, useEffect, useId, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { ChevronDown, ChevronRight, History, PanelLeft, PanelLeftClose, Save, X } from 'lucide-react';
import { api, firstError } from '../api';
import { AppShell } from '../components/AppShell';
import { SchemaForm, type Section } from '../components/SchemaForm';
import { LivePreviewButton } from '../components/LivePreview';
import { OgCaptureButton } from '../components/OgCapture';
import { AI_UNAVAILABLE, FieldAiWrap, type FieldAiContext } from '../components/SectionCopy';
import { SeoLength } from '../components/SeoLength';
import { SeoInsights, writeTarget, type SeoTarget } from '../components/SeoInsights';
import { MediaField } from '../components/MediaField';
import { PageOutline, type OutlineSection } from '../components/PageOutline';
import type { LibraryItem } from '../components/SectionPicker';
import { GooglePreview } from '../components/GooglePreview';
import { Modal } from '../components/Modal';
import { formatDate } from '../lib';
import { scoreTone } from '../components/SiteHealth';
import { composeSeoTitle, resolveSeoDescription, type SeoDefaults } from '../seo';
import { useTour } from '../tour/TourProvider';
import { TranslationTabs, useTranslationConfig, useTranslationJob, type TranslationState } from '../components/TranslationBar';

type PageSection = {
  id: string;
  type: string;
  data: Record<string, unknown>;
};

type PageDetail = {
  id: number;
  slug: string;
  locale: string;
  path: string;
  translation_of: number | null;
  source_id: number;
  source_slug: string;
  stale: boolean;
  translated_at: string | null;
  translations: Record<string, TranslationState>;
  template: string;
  status: string;
  seo_title: string;
  seo_description: string | null;
  seo_keyword: string | null;
  canonical: string | null;
  og_image: string | null;
  og_image_alt: string | null;
  robots: string | null;
  twitter_card: string | null;
  content: { sections: PageSection[] };
};

type LibraryGroup = {
  group: string;
  group_label: string;
  items: LibraryItem[];
};

type PageRevision = {
  id: number;
  created_at: string | null;
  user_name: string | null;
  status: string | null;
  seo_title: string | null;
};

function newId(): string {
  if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
    return crypto.randomUUID().replace(/-/g, '').slice(0, 26);
  }
  return `s${Date.now().toString(36)}${Math.random().toString(36).slice(2, 10)}`;
}

type BoxState = { page: boolean; seo: boolean; insights: boolean };

const BOXES_KEY = 'cms-page-boxes';

function readBoxes(): BoxState {
  const fallback = { page: true, seo: true, insights: true };
  try {
    return { ...fallback, ...JSON.parse(localStorage.getItem(BOXES_KEY) ?? '{}') };
  } catch {
    return fallback;
  }
}

function EditorBox({
  title,
  meta,
  open,
  onToggle,
  children,
}: {
  title: string;
  meta?: ReactNode;
  open: boolean;
  onToggle: () => void;
  children: ReactNode;
}) {
  const bodyId = useId();

  return (
    <section className={`editor-box${open ? ' is-open' : ''}`}>
      <button type="button" className="editor-box-toggle" aria-expanded={open} aria-controls={bodyId} onClick={onToggle}>
        {open ? <ChevronDown size={14} /> : <ChevronRight size={14} />}
        {title}
        {meta}
      </button>
      {open ? <div className="editor-box-body" id={bodyId}>{children}</div> : null}
    </section>
  );
}

export function PageEditorScreen() {
  const { slug } = useParams();
  const navigate = useNavigate();
  const { registerActions } = useTour();
  const [page, setPage] = useState<PageDetail | null>(null);
  const [baseline, setBaseline] = useState<string | null>(null);
  const [schema, setSchema] = useState<OutlineSection[]>([]);
  const [library, setLibrary] = useState<LibraryGroup[]>([]);
  const [defaults, setDefaults] = useState<SeoDefaults | null>(null);
  const [section, setSection] = useState(0);
  const [error, setError] = useState('');
  const [saved, setSaved] = useState(false);
  const [sidebarOpen, setSidebarOpen] = useState(() => localStorage.getItem('cms-page-sidebar') !== '0');
  const [boxes, setBoxes] = useState<BoxState>(readBoxes);
  const [seoScore, setSeoScore] = useState<number | null>(null);
  const [versionsOpen, setVersionsOpen] = useState(false);
  const [revisions, setRevisions] = useState<PageRevision[]>([]);
  const [revisionsLoading, setRevisionsLoading] = useState(false);
  const [restoreId, setRestoreId] = useState<number | null>(null);
  const [restoring, setRestoring] = useState(false);
  const [removeIndex, setRemoveIndex] = useState<number | null>(null);
  const [leavingId, setLeavingId] = useState<string | null>(null);
  const libraryItems = useMemo(() => library.flatMap((group) => group.items), [library]);
  const [canvasFlash, setCanvasFlash] = useState(0);
  const [retranslateOpen, setRetranslateOpen] = useState(false);
  const translationConfig = useTranslationConfig();
  const translationJob = useTranslationJob('page', page?.source_id ?? null, (locale, id) => {
    if (id !== null) {
      navigate(`/paginas/${id}`);
      return;
    }
    void reload();
  });

  useEffect(() => {
    if (!canvasFlash) {
      return;
    }
    const timer = window.setTimeout(() => setCanvasFlash(0), 1000);
    return () => window.clearTimeout(timer);
  }, [canvasFlash]);

  useEffect(() => {
    if (!leavingId || !page) {
      return;
    }

    const index = schema.findIndex((item) => item.id === leavingId);
    if (index < 0) {
      setLeavingId(null);
      return;
    }

    const timer = window.setTimeout(() => {
      const sections = page.content.sections.filter((item) => item.id !== leavingId);
      const nextSchema = schema.filter((item) => item.id !== leavingId);
      patchPage({ ...page, content: { sections } }, nextSchema);
      setSection((current) => {
        if (sections.length === 0) {
          return 0;
        }
        if (current > index) {
          return current - 1;
        }
        return Math.min(current, sections.length - 1);
      });
      setLeavingId(null);
      setCanvasFlash(Date.now());
    }, 1200);

    return () => window.clearTimeout(timer);
  }, [leavingId]);

  function adoptPage(next: PageDetail, nextSchema?: OutlineSection[]) {
    setPage(next);
    setBaseline(JSON.stringify(next));
    if (nextSchema) {
      setSchema(nextSchema);
    }
  }

  function patchPage(next: PageDetail, nextSchema?: OutlineSection[]) {
    setPage(next);
    if (nextSchema) {
      setSchema(nextSchema);
    }
    setSaved(false);
  }

  async function reload() {
    const payload = await api<{ data: PageDetail; schema: OutlineSection[]; library: LibraryGroup[]; seo: SeoDefaults }>(`/pages/${slug}`);
    adoptPage(payload.data, payload.schema);
    setLibrary(payload.library);
    setDefaults(payload.seo);
    setSaved(false);
    setSection(0);
  }

  useEffect(() => {
    void reload();
  }, [slug]);

  function openLocale(locale: string) {
    if (!page) {
      return;
    }
    if (locale === translationConfig?.default) {
      navigate(`/paginas/${page.source_slug}`);
      return;
    }
    const target = page.translations[locale];
    if (target?.id) {
      navigate(`/paginas/${target.id}`);
    }
  }

  useEffect(() => {
    if (!versionsOpen) {
      return;
    }

    function onKey(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        setVersionsOpen(false);
      }
    }

    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [versionsOpen]);

  function toggleBox(key: keyof BoxState) {
    const next = { ...boxes, [key]: !boxes[key] };
    setBoxes(next);
    localStorage.setItem(BOXES_KEY, JSON.stringify(next));
  }

  function toggleSidebar() {
    const next = !sidebarOpen;
    setSidebarOpen(next);
    localStorage.setItem('cms-page-sidebar', next ? '1' : '0');
  }

  async function loadRevisions() {
    if (!slug) {
      return;
    }
    setRevisionsLoading(true);
    try {
      const payload = await api<{ data: PageRevision[] }>(`/pages/${slug}/revisions`);
      setRevisions(payload.data);
    } catch (err) {
      setError(firstError(err));
    } finally {
      setRevisionsLoading(false);
    }
  }

  useEffect(() => registerActions({
    'page-editor.openVersions': () => {
      setVersionsOpen(true);
      void loadRevisions();
    },
    'page-editor.closeVersions': () => setVersionsOpen(false),
  }), [registerActions, slug]);

  async function openVersions() {
    setVersionsOpen(true);
    await loadRevisions();
  }

  function reorderSections(from: number, to: number) {
    if (!page || from === to) {
      return;
    }
    const sections = [...page.content.sections];
    const [moved] = sections.splice(from, 1);
    sections.splice(to, 0, moved);
    const nextSchema = [...schema];
    const [movedSchema] = nextSchema.splice(from, 1);
    nextSchema.splice(to, 0, movedSchema);
    patchPage({ ...page, content: { sections } }, nextSchema);
    setSection(to);
  }

  function confirmRemoveSection() {
    if (!page || removeIndex === null) {
      return;
    }
    const target = schema[removeIndex];
    if (!target) {
      setRemoveIndex(null);
      return;
    }
    setLeavingId(target.id);
    setRemoveIndex(null);
  }

  function addSection(item: LibraryItem) {
    if (!page) {
      return;
    }
    const id = newId();
    const sections = [...page.content.sections, { id, type: item.type, data: {} }];
    const nextSchema: OutlineSection[] = [
      ...schema,
      { id, type: item.type, label: item.label, preview: item.preview, fields: item.fields },
    ];
    patchPage({ ...page, content: { sections } }, nextSchema);
    setSection(sections.length - 1);
    setCanvasFlash(Date.now());
  }

  async function save() {
    if (!page) {
      return;
    }
    setError('');
    setSaved(false);
    try {
      const payload = await api<{ data: PageDetail; schema?: OutlineSection[] }>(`/pages/${slug}`, {
        method: 'PUT',
        body: JSON.stringify({ ...page, status: isTranslation ? page.status : 'published' }),
      });
      const nextSchema = payload.schema ?? schema;
      adoptPage(payload.data, nextSchema);
      setSaved(true);
      if (!isTranslation && payload.data.slug !== slug) {
        navigate(`/paginas/${payload.data.slug}`, { replace: true });
      }
    } catch (err) {
      setError(firstError(err));
    }
  }

  async function restoreRevision() {
    if (!slug || restoreId === null) {
      return;
    }
    setRestoring(true);
    setError('');
    try {
      const payload = await api<{ data: PageDetail; schema?: OutlineSection[] }>(`/pages/${slug}/revisions/${restoreId}/restore`, {
        method: 'POST',
      });
      const refreshed = await api<{ data: PageDetail; schema: OutlineSection[]; library: LibraryGroup[]; seo: SeoDefaults }>(`/pages/${payload.data.slug}`);
      adoptPage(refreshed.data, refreshed.schema);
      setLibrary(refreshed.library);
      setSaved(true);
      setRestoreId(null);
      setVersionsOpen(false);
      if (!isTranslation && payload.data.slug !== slug) {
        navigate(`/paginas/${payload.data.slug}`, { replace: true });
      }
    } catch (err) {
      setError(firstError(err));
    } finally {
      setRestoring(false);
    }
  }

  if (!page || !slug) {
    return (
      <AppShell title="Páginas" full>
        <div className="auth-loading"><div className="spinner" /></div>
      </AppShell>
    );
  }

  const current = schema[section];
  const currentData = page.content.sections[section]?.data ?? {};
  const isHome = page.template === 'home';
  const dirty = baseline !== null && JSON.stringify(page) !== baseline;
  const isTranslation = page.translation_of !== null;
  const pathPrefix = page.path.replace(/\/[^/]*$/, '/').replace(/^\/$/, '/');
  const localeName = translationConfig?.locales.find((item) => item.code === page.locale)?.name ?? page.locale;
  const seoAi: FieldAiContext | undefined = isTranslation ? undefined : {
    disabled: !translationConfig?.configured,
    request: () => ({
      kind: 'page',
      target: 'seo',
      page_id: page.id,
      title: page.seo_title,
      path: page.path,
      seo_title: page.seo_title,
      seo_description: page.seo_description ?? '',
      seo_keyword: page.seo_keyword ?? '',
      sections: page.content.sections.map((item) => ({ type: item.type, data: item.data })),
    }),
  };

  function applySeoFix(target: SeoTarget, value: string) {
    setSaved(false);
    setPage((current) => {
      if (!current) {
        return current;
      }
      const next = writeTarget({
        keyword: current.seo_keyword ?? '',
        seo_title: current.seo_title,
        seo_description: current.seo_description ?? '',
        slug: current.slug,
        path: current.path,
        og_image_alt: current.og_image_alt ?? '',
        sections: current.content.sections.map((item) => ({ type: item.type, data: item.data })),
      }, target, value);
      return {
        ...current,
        seo_title: next.seo_title,
        seo_description: next.seo_description,
        og_image_alt: next.og_image_alt ?? current.og_image_alt,
        content: { sections: current.content.sections.map((item, index) => ({ ...item, data: next.sections[index]?.data ?? item.data })) },
      };
    });
  }

  return (
    <AppShell title={page.seo_title} subtitle="Editor de página" full>
      {error ? <div className="editor-banner form-error">{error}</div> : null}
      {translationJob.error ? <div className="editor-banner form-error">{translationJob.error}</div> : null}
      {saved && !dirty ? <div className="editor-banner form-success">Página atualizada.</div> : null}
      {isTranslation && page.stale ? <div className="editor-banner form-warning">O original em português mudou depois desta tradução. Revise ou retraduza.</div> : null}
      <div className="editor-langbar">
        <TranslationTabs
          current={page.locale}
          translations={page.translations}
          config={translationConfig}
          running={translationJob.running}
          onOpen={openLocale}
          onTranslate={(locale) => void translationJob.start(locale)}
        />
      </div>
      <div className={`editor-frame editor-split${sidebarOpen ? '' : ' is-collapsed'}`}>
        <aside className={`editor-nav${sidebarOpen ? '' : ' is-collapsed'}`} data-tour="editor-outline">
          <div className="editor-nav-head">
            {sidebarOpen ? <span className="eyebrow">Seções</span> : null}
            <button
              className="icon-btn"
              type="button"
              onClick={toggleSidebar}
              aria-label={sidebarOpen ? 'Recolher painel' : 'Expandir painel'}
            >
              {sidebarOpen ? <PanelLeftClose size={16} /> : <PanelLeft size={16} />}
            </button>
          </div>
          {sidebarOpen ? (
            <PageOutline
              sections={schema}
              active={section}
              leavingId={leavingId}
              onSelect={setSection}
              onReorder={reorderSections}
              onRemove={setRemoveIndex}
              library={libraryItems}
              onAdd={addSection}
            />
          ) : (
            <button className="icon-btn" type="button" disabled={!dirty} onClick={() => void save()} aria-label="Salvar">
              <Save size={16} />
            </button>
          )}
        </aside>
        <div className="editor-canvas" data-tour="editor-canvas">
          <div className={`panel${canvasFlash ? ' is-entering' : ''}`}>
            <div className="panel-head">
              <h3>{current?.label ?? 'Sem seções'}</h3>
            </div>
            <div className="panel-body">
              {current ? (
                <>
                  <SchemaForm
                    key={page.content.sections[section]?.id ?? section}
                    fields={current.fields}
                    content={currentData}
                    context={{ pageSlug: page.slug }}
                    ai={isTranslation ? undefined : {
                      disabled: !translationConfig?.configured,
                      request: () => ({
                        kind: 'page',
                        page_id: page.id,
                        index: section,
                        title: page.seo_title,
                        description: page.seo_description ?? undefined,
                        path: page.path,
                        sections: page.content.sections.map((item) => ({ type: item.type, data: item.data })),
                      }),
                    }}
                    onChange={(data) => {
                      const sections = page.content.sections.map((item, index) => (
                        index === section ? { ...item, data } : item
                      ));
                      patchPage({ ...page, content: { sections } });
                    }}
                  />
                </>
              ) : (
                <p className="desc">Adicione uma seção pelo painel lateral.</p>
              )}
            </div>
          </div>
        </div>
        <aside className="editor-inspect">
          <div className="inspect-actions" data-tour="editor-save">
            <LivePreviewButton
              previewPath={`preview/${page.id}`}
              endpoint={`/preview/pages/${page.id}`}
              body={{ content: page.content, seo_title: page.seo_title, seo_description: page.seo_description }}
            />
            <button className="btn btn-primary" type="button" disabled={!dirty} onClick={() => void save()}>
              Atualizar
            </button>
          </div>
          <div className="inspect-scroll">
            {isTranslation ? (
              <EditorBox title="Página" open={boxes.page} onToggle={() => toggleBox('page')}>
                <div className="field">
                  <span className="field-label" id="page-status-label">Status em {localeName}</span>
                  <div className="status-pills" role="group" aria-labelledby="page-status-label">
                    <button type="button" className={page.status === 'draft' ? 'is-on' : undefined} aria-pressed={page.status === 'draft'} onClick={() => patchPage({ ...page, status: 'draft' })}>Rascunho</button>
                    <button type="button" className={page.status === 'published' ? 'is-on' : undefined} aria-pressed={page.status === 'published'} onClick={() => patchPage({ ...page, status: 'published' })}>Publicada</button>
                  </div>
                  <p className="hint">{page.translated_at ? `Traduzida com IA em ${formatDate(page.translated_at)}.` : 'Tradução manual.'}</p>
                </div>
                <button
                  className="btn btn-secondary lang-retranslate"
                  type="button"
                  disabled={!translationConfig?.configured || translationJob.running[page.locale]}
                  onClick={() => setRetranslateOpen(true)}
                >
                  {translationJob.running[page.locale] ? 'Traduzindo' : 'Retraduzir a partir do PT'}
                </button>
              </EditorBox>
            ) : null}

            <div data-tour="editor-seo">
            <EditorBox
              title="SEO"
              meta={seoScore !== null ? <span className={`editor-box-score is-${scoreTone(seoScore)}`} title="Nota SEO">{seoScore}</span> : null}
              open={boxes.seo}
              onToggle={() => toggleBox('seo')}
            >
              <GooglePreview
                title={composeSeoTitle(page.seo_title, defaults)}
                description={resolveSeoDescription(page.seo_description, defaults)}
                canonical={page.canonical}
                slug={page.path.replace(/^\//, '') || page.slug || slug}
              />
              <div className="field">
                <label htmlFor="page-slug">Slug</label>
                <div className="slug-field">
                  <span>{pathPrefix}</span>
                  <input
                    id="page-slug"
                    aria-describedby="page-slug-hint"
                    value={isHome ? '' : page.slug}
                    placeholder={isHome ? '' : 'pagina'}
                    disabled={isHome}
                    onChange={(event) => patchPage({
                      ...page,
                      slug: event.target.value.trim().toLowerCase().replace(/[^a-z0-9-]/g, ''),
                    })}
                  />
                </div>
                <p className="hint" id="page-slug-hint">{isHome ? `A home publica em ${page.path}.` : 'URL pública da página.'}</p>
              </div>
              <div className="field">
                <label htmlFor="page-seo-keyword">Palavra-chave</label>
                <input
                  id="page-seo-keyword"
                  value={page.seo_keyword ?? ''}
                  maxLength={120}
                  placeholder="ex.: agentes de ia para atendimento"
                  aria-describedby="page-seo-keyword-hint"
                  onChange={(event) => patchPage({ ...page, seo_keyword: event.target.value })}
                />
                <p className="hint" id="page-seo-keyword-hint">Termo principal que esta página deve ranquear. A IA e a nota SEO usam este termo.</p>
              </div>
              <div className="field">
                <label htmlFor="page-seo-title">Título SEO</label>
                <FieldAiWrap ai={seoAi} type="text" path="seo_title" value={page.seo_title} onApply={(value) => patchPage({ ...page, seo_title: value })}>
                  <input
                    id="page-seo-title"
                    value={page.seo_title}
                    aria-describedby="page-seo-title-length page-seo-title-hint"
                    onChange={(event) => patchPage({ ...page, seo_title: event.target.value })}
                  />
                </FieldAiWrap>
                <SeoLength id="page-seo-title-length" kind="title" text={composeSeoTitle(page.seo_title, defaults)} fallback={!page.seo_title.trim()} />
                <p className="hint" id="page-seo-title-hint">Sem o nome do site, o template padrão entra no título público.</p>
              </div>
              <div className="field">
                <label htmlFor="page-seo-description">Descrição SEO</label>
                <FieldAiWrap ai={seoAi} type="textarea" path="seo_description" value={page.seo_description ?? ''} onApply={(value) => patchPage({ ...page, seo_description: value })}>
                  <textarea
                    id="page-seo-description"
                    value={page.seo_description ?? ''}
                    placeholder={defaults?.seo_description ?? ''}
                    aria-describedby="page-seo-description-length page-seo-description-hint"
                    onChange={(event) => patchPage({ ...page, seo_description: event.target.value })}
                  />
                </FieldAiWrap>
                <SeoLength id="page-seo-description-length" kind="description" text={resolveSeoDescription(page.seo_description, defaults)} fallback={!page.seo_description?.trim()} />
                <p className="hint" id="page-seo-description-hint">Vazio usa a descrição padrão do SEO do site.</p>
              </div>
              <MediaField
                label="OG image"
                value={page.og_image}
                hint="Vazio usa a OG image padrão do site."
                onChange={(value) => patchPage({ ...page, og_image: value })}
              />
              <OgCaptureButton
                endpoint={`/og-image/pages/${page.id}`}
                body={{ content: page.content, seo_title: page.seo_title, seo_description: page.seo_description }}
                onCaptured={(path) => {
                  setSaved(false);
                  setPage((current) => (current ? {
                    ...current,
                    og_image: path,
                    og_image_alt: current.og_image_alt?.trim() ? current.og_image_alt : composeSeoTitle(current.seo_title, defaults),
                  } : current));
                }}
              />
              <div className="field">
                <label htmlFor="page-og-alt">Alt da OG image</label>
                <input
                  id="page-og-alt"
                  value={page.og_image_alt ?? ''}
                  placeholder={defaults?.seo_og_image_alt ?? ''}
                  onChange={(event) => patchPage({ ...page, og_image_alt: event.target.value })}
                />
              </div>
              <details className="seo-advanced">
                <summary>Avançado</summary>
                <div className="field">
                  <label htmlFor="page-canonical">Canonical</label>
                  <input id="page-canonical" value={page.canonical ?? ''} onChange={(event) => patchPage({ ...page, canonical: event.target.value })} />
                </div>
                <div className="field">
                  <label htmlFor="page-robots">Robots</label>
                  <input
                    id="page-robots"
                    value={page.robots ?? ''}
                    placeholder={defaults?.seo_robots ?? ''}
                    onChange={(event) => patchPage({ ...page, robots: event.target.value })}
                  />
                </div>
                <div className="field">
                  <label htmlFor="page-twitter-card">Twitter card</label>
                  <input
                    id="page-twitter-card"
                    value={page.twitter_card ?? ''}
                    placeholder={defaults?.twitter_card ?? ''}
                    onChange={(event) => patchPage({ ...page, twitter_card: event.target.value })}
                  />
                </div>
              </details>
            </EditorBox>
            </div>

            <EditorBox title="Análise SEO e desempenho" open={boxes.insights} onToggle={() => toggleBox('insights')}>
              <SeoInsights
                kind="page"
                id={page.id}
                aiDisabled={!translationConfig?.configured || isTranslation}
                aiNote={isTranslation ? 'Correções por IA só na página original em português.' : AI_UNAVAILABLE}
                onScore={setSeoScore}
                market={!isTranslation}
                onKeyword={(keyword) => patchPage({ ...page, seo_keyword: keyword })}
                input={{
                  keyword: page.seo_keyword ?? '',
                  seo_title: page.seo_title,
                  seo_description: page.seo_description ?? '',
                  slug: page.slug,
                  path: page.path,
                  og_image_alt: page.og_image_alt ?? '',
                  sections: page.content.sections.map((item) => ({ type: item.type, data: item.data })),
                }}
                lighthouse={{ kind: 'pages', id: page.id }}
                onApply={(target, value) => applySeoFix(target, value)}
              />
            </EditorBox>

            <button className="versions-trigger" type="button" data-tour="editor-versions" onClick={() => void openVersions()}>
              <History size={13} />
              Versões
            </button>
          </div>
        </aside>
      </div>

      {versionsOpen ? (
        <div className="versions-backdrop" onClick={() => setVersionsOpen(false)}>
          <aside className="versions-drawer" onClick={(event) => event.stopPropagation()}>
            <div className="versions-head">
              <div>
                <span className="eyebrow">Controle de versão</span>
                <h3>Versões anteriores</h3>
              </div>
              <button className="icon-btn" type="button" onClick={() => setVersionsOpen(false)} aria-label="Fechar">
                <X size={14} />
              </button>
            </div>
            <div className="versions-body">
              {revisionsLoading ? (
                <div className="auth-loading"><div className="spinner" /></div>
              ) : revisions.length === 0 ? (
                <p className="desc">Nenhuma versão salva ainda.</p>
              ) : (
                <ul className="versions-list">
                  {revisions.map((revision) => (
                    <li key={revision.id}>
                      <div>
                        <strong>{formatDate(revision.created_at)}</strong>
                        <span>{revision.user_name ?? 'Sistema'}</span>
                      </div>
                      <button
                        className="btn btn-secondary"
                        type="button"
                        onClick={() => setRestoreId(revision.id)}
                      >
                        Restaurar
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </aside>
        </div>
      ) : null}

      {restoreId !== null ? (
        <Modal
          title="Restaurar versão"
          eyebrow="Versões"
          onClose={() => setRestoreId(null)}
          footer={(
            <>
              <button className="btn btn-secondary" type="button" onClick={() => setRestoreId(null)}>Cancelar</button>
              <button className="btn btn-primary" type="button" disabled={restoring} onClick={() => void restoreRevision()}>
                Restaurar
              </button>
            </>
          )}
        >
          <p className="desc">A versão atual será salva antes da restauração, para você poder voltar se precisar.</p>
        </Modal>
      ) : null}

      {retranslateOpen ? (
        <Modal
          title="Retraduzir página"
          eyebrow={localeName}
          onClose={() => setRetranslateOpen(false)}
          footer={(
            <>
              <button className="btn btn-secondary" type="button" onClick={() => setRetranslateOpen(false)}>Cancelar</button>
              <button
                className="btn btn-primary"
                type="button"
                onClick={() => {
                  setRetranslateOpen(false);
                  void translationJob.start(page.locale);
                }}
              >
                Retraduzir
              </button>
            </>
          )}
        >
          <p className="desc">O conteúdo desta tradução será substituído por uma nova tradução do original em português. As edições manuais ficam salvas em Versões.</p>
        </Modal>
      ) : null}

      {removeIndex !== null ? (
        <Modal
          title="Remover seção"
          eyebrow="Seções"
          onClose={() => setRemoveIndex(null)}
          footer={(
            <>
              <button className="btn btn-secondary" type="button" onClick={() => setRemoveIndex(null)}>Cancelar</button>
              <button className="btn btn-primary" type="button" onClick={confirmRemoveSection}>Remover</button>
            </>
          )}
        >
          <p className="desc">A seção será removida desta página. O conteúdo só deixa de existir depois de atualizar.</p>
        </Modal>
      ) : null}
    </AppShell>
  );
}
