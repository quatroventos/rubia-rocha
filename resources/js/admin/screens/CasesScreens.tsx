import { type ReactNode, useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { ExternalLink, Pencil, Plus, RotateCcw, Sparkles, Star, Trash2, X } from 'lucide-react';
import { api, firstError } from '../api';
import { AppShell } from '../components/AppShell';
import { LangDots } from '../components/LangDots';
import { TranslationTabs, useTranslationConfig, useTranslationJob, type TranslationState } from '../components/TranslationBar';
import { ConfirmDialog } from '../components/ConfirmDialog';
import { MediaField } from '../components/MediaField';
import { OgCaptureButton } from '../components/OgCapture';
import { Modal } from '../components/Modal';
import { PageOutline, type OutlineSection } from '../components/PageOutline';
import { SchemaForm } from '../components/SchemaForm';
import { LivePreviewButton } from '../components/LivePreview';
import { FieldAiWrap, type FieldAiContext } from '../components/SectionCopy';
import { SeoLength } from '../components/SeoLength';
import { SeoInsights, writeTarget, type SeoInput, type SeoTarget } from '../components/SeoInsights';
import { composeSeoTitle, type SeoDefaults } from '../seo';
import type { LibraryItem } from '../components/SectionPicker';

type Option = { value: string; label: string };

type CaseSection = { id: string; type: string; data: Record<string, unknown> };

type LibraryGroup = { group: string; group_label: string; items: LibraryItem[] };

type CaseDraft = {
  client: string;
  sector: string;
  services: string[];
  year: number | null;
  title: string;
  summary: string;
  tags: string[];
  seo_title: string;
  seo_description: string;
  sections: CaseSection[];
  cover: { path: string; alt: string } | null;
  source_read: boolean;
  prints_failed: number;
  mockups: number;
  mockups_failed: number;
  mockups_unavailable: boolean;
};

type DraftStatus = { state: 'running' | 'done' | 'failed'; message?: string; draft?: CaseDraft };

function hasContent(section: CaseSection): boolean {
  return Object.values(section.data ?? {}).some((value) => (Array.isArray(value) ? value.length > 0 : Boolean(value)));
}

function hasImages(section: CaseSection): boolean {
  const items = section.data?.items;
  return section.type === 'site.gallery' && Array.isArray(items) && items.some((item) => Boolean((item as { image?: string }).image));
}

const BRIEFING_GUIDE = `Cliente e segmento:
Quem usa o sistema (equipe, clientes finais, parceiros):

Como era antes (processo manual, planilhas, sistema antigo, gargalos):
Problema principal que o projeto resolve:

O que construímos (sistema, app, site, automação, agente de IA):
Funcionalidades principais:
- 
- 
- 

Integrações (ERP, pagamentos, WhatsApp, APIs, bancos de dados):
Tecnologias usadas:
Como foi o projeto (etapas, prazo, equipe):

Resultados medidos (só números reais, com período):
Depoimento do cliente (literal, com nome e cargo):

Não citar (nomes de parceiros, fornecedores, dados sensíveis):
Ano de entrega:`;

const STARTER: Array<{ type: string; data: Record<string, unknown> }> = [
  { type: 'site.prose', data: { title: 'Desafio' } },
  { type: 'site.prose', data: { title: 'Solução' } },
  { type: 'site.gallery', data: { title: 'Prints' } },
  { type: 'site.results', data: { title: 'Resultados' } },
];

type CaseSummary = {
  id: number;
  slug: string;
  status: 'draft' | 'published';
  client: string;
  title: string;
  sector: string | null;
  services: string[];
  cover_path: string | null;
  featured: boolean;
  sort: number;
  translations?: Record<string, TranslationState>;
};

type CaseDetail = {
  id?: number;
  locale?: string;
  path?: string;
  translation_of?: number | null;
  source_id?: number;
  stale?: boolean;
  translations?: Record<string, TranslationState>;
  slug: string;
  status: 'draft' | 'published';
  client: string;
  title: string;
  summary: string;
  sector: string;
  services: string[];
  tags: string[];
  year: number | null;
  cover_path: string | null;
  cover_alt: string;
  cover_ratio: string;
  content: { sections: CaseSection[] };
  url: string;
  featured: boolean;
  sort: number;
  seo_title: string;
  seo_description: string;
  seo_keyword: string;
  og_image: string | null;
};

const empty: CaseDetail = {
  slug: '',
  status: 'draft',
  client: '',
  title: '',
  summary: '',
  sector: '',
  services: [],
  tags: [],
  year: null,
  cover_path: null,
  cover_alt: '',
  cover_ratio: '',
  content: { sections: [] },
  url: '',
  featured: false,
  sort: 0,
  seo_title: '',
  seo_description: '',
  seo_keyword: '',
  og_image: null,
};

function slugify(value: string): string {
  return value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

function normalize(raw: Partial<CaseDetail> & Record<string, unknown>): CaseDetail {
  return {
    ...empty,
    ...raw,
    summary: (raw.summary as string) ?? '',
    sector: (raw.sector as string) ?? '',
    cover_alt: (raw.cover_alt as string) ?? '',
    cover_ratio: (raw.cover_ratio as string) ?? '',
    url: (raw.url as string) ?? '',
    seo_title: (raw.seo_title as string) ?? '',
    seo_description: (raw.seo_description as string) ?? '',
    seo_keyword: (raw.seo_keyword as string) ?? '',
    og_image: (raw.og_image as string) || null,
    services: Array.isArray(raw.services) ? raw.services : [],
    tags: Array.isArray(raw.tags) ? raw.tags : [],
    content: { sections: Array.isArray((raw.content as { sections?: unknown } | null)?.sections) ? (raw.content as { sections: CaseSection[] }).sections : [] },
  };
}

function newId(): string {
  if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
    return crypto.randomUUID().replace(/-/g, '').slice(0, 26);
  }
  return `s${Date.now().toString(36)}${Math.random().toString(36).slice(2, 10)}`;
}

function outlineFor(sections: CaseSection[], library: LibraryItem[]): OutlineSection[] {
  return sections.map((section) => {
    const item = library.find((entry) => entry.type === section.type);
    return { id: section.id, type: section.type, label: item?.label ?? section.type, preview: item?.preview, fields: item?.fields ?? [] };
  });
}

export function CasesScreen() {
  const navigate = useNavigate();
  const [rows, setRows] = useState<CaseSummary[]>([]);
  const [services, setServices] = useState<Option[]>([]);
  const [sectors, setSectors] = useState<Option[]>([]);
  const [trashed, setTrashed] = useState(false);
  const [query, setQuery] = useState('');
  const [pendingDelete, setPendingDelete] = useState<CaseSummary | null>(null);
  const [busy, setBusy] = useState(false);

  async function load(nextTrashed = trashed) {
    const payload = await api<{ data: CaseSummary[]; services: Option[]; sectors: Option[] }>(`/cases${nextTrashed ? '?trashed=1' : ''}`);
    setRows(payload.data);
    setServices(payload.services);
    setSectors(payload.sectors);
  }

  useEffect(() => {
    void load(trashed);
  }, [trashed]);

  const serviceLabel = useMemo(() => new Map(services.map((item) => [item.value, item.label])), [services]);
  const sectorLabel = useMemo(() => new Map(sectors.map((item) => [item.value, item.label])), [sectors]);
  const filtered = rows.filter((row) => `${row.client} ${row.title} ${row.slug}`.toLowerCase().includes(query.toLowerCase()));

  async function confirmDelete() {
    if (!pendingDelete) {
      return;
    }
    setBusy(true);
    try {
      await api(`/cases/${pendingDelete.id}`, { method: 'DELETE' });
      setPendingDelete(null);
      await load();
    } finally {
      setBusy(false);
    }
  }

  async function restore(row: CaseSummary) {
    setBusy(true);
    try {
      await api(`/cases/${row.id}/restore`, { method: 'POST' });
      await load();
    } finally {
      setBusy(false);
    }
  }

  return (
    <AppShell
      eyebrow="Conteúdo"
      title="Cases"
      subtitle="Projetos com prints e detalhamento. Cada case pode aparecer nas páginas dos serviços vinculados."
      actions={!trashed ? (
        <button className="btn btn-primary" type="button" onClick={() => navigate('/cases/novo')}>
          <Plus size={16} /> Novo case
        </button>
      ) : undefined}
    >
      <div className="toolbar">
        <input className="search" placeholder="Buscar" value={query} onChange={(event) => setQuery(event.target.value)} />
        <div className="resource-tabs">
          <button type="button" className={!trashed ? 'is-on' : ''} onClick={() => setTrashed(false)}>Ativos</button>
          <button type="button" className={trashed ? 'is-on' : ''} onClick={() => setTrashed(true)}>Lixeira</button>
        </div>
      </div>
      <div className="panel table-panel">
        {filtered.length === 0 ? (
          <div className="empty">{trashed ? 'Lixeira vazia.' : 'Nenhum case cadastrado.'}</div>
        ) : (
          <table>
            <thead>
              <tr>
                <th />
                <th>Cliente</th>
                <th>Título</th>
                <th>Setor</th>
                <th>Serviços</th>
                <th>Status</th>
                <th>Idiomas</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {filtered.map((row) => (
                <tr key={row.id}>
                  <td>{row.cover_path ? <img className="case-thumb" src={row.cover_path} alt="" /> : <span className="case-thumb is-empty" />}</td>
                  <td>
                    <strong>{row.client}</strong>
                    {row.featured ? <Star size={12} className="case-star" aria-label="Destaque" /> : null}
                  </td>
                  <td>{row.title}</td>
                  <td>{row.sector ? sectorLabel.get(row.sector) ?? row.sector : '—'}</td>
                  <td>{row.services.length ? row.services.map((slug) => serviceLabel.get(slug) ?? slug).join(', ') : '—'}</td>
                  <td>{row.status === 'published' ? 'Publicado' : 'Rascunho'}</td>
                  <td><LangDots translations={row.translations} linkTo={(translationId) => `/cases/${translationId}`} /></td>
                  <td>
                    <div className="row-actions">
                      {trashed ? (
                        <button className="icon-btn" type="button" disabled={busy} onClick={() => void restore(row)} aria-label="Recuperar">
                          <RotateCcw size={14} />
                        </button>
                      ) : (
                        <>
                          {row.status === 'published' ? (
                            <a className="icon-btn" href={`/cases/${row.slug}`} target="_blank" rel="noreferrer" aria-label="Ver no site">
                              <ExternalLink size={14} />
                            </a>
                          ) : null}
                          <button className="icon-btn" type="button" onClick={() => navigate(`/cases/${row.id}`)} aria-label="Editar">
                            <Pencil size={14} />
                          </button>
                          <button className="icon-btn danger" type="button" onClick={() => setPendingDelete(row)} aria-label="Excluir">
                            <Trash2 size={14} />
                          </button>
                        </>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
      {pendingDelete ? (
        <ConfirmDialog
          title="Mover para a lixeira?"
          message="O case sai do site e das páginas de serviço, mas pode ser recuperado depois na lixeira."
          confirmLabel="Mover para a lixeira"
          busy={busy}
          onClose={() => setPendingDelete(null)}
          onConfirm={() => void confirmDelete()}
        />
      ) : null}
    </AppShell>
  );
}

function Box({ title, hint, children }: { title: string; hint?: string; children: ReactNode }) {
  return (
    <div className="panel case-box">
      <div className="panel-head">
        <h3>{title}</h3>
        {hint ? <p>{hint}</p> : null}
      </div>
      <div className="panel-body">{children}</div>
    </div>
  );
}

function StringList({ label, value, placeholder, onChange }: { label: string; value: string[]; placeholder?: string; onChange: (value: string[]) => void }) {
  return (
    <div className="field">
      <label>{label}</label>
      {value.map((item, index) => (
        <div className="case-list-row" key={index}>
          <input
            value={item}
            placeholder={placeholder}
            onChange={(event) => onChange(value.map((current, i) => (i === index ? event.target.value : current)))}
          />
          <button className="icon-btn danger" type="button" onClick={() => onChange(value.filter((_, i) => i !== index))} aria-label="Remover">
            <Trash2 size={14} />
          </button>
        </div>
      ))}
      <button className="btn btn-secondary" type="button" onClick={() => onChange([...value, ''])}>
        <Plus size={14} /> Adicionar
      </button>
    </div>
  );
}

export function CaseEditorScreen() {
  const { id } = useParams();
  const navigate = useNavigate();
  const creating = id === 'novo';
  const [form, setForm] = useState<CaseDetail | null>(creating ? { ...empty } : null);
  const [baseline, setBaseline] = useState<string>(creating ? JSON.stringify(empty) : '');
  const [services, setServices] = useState<Option[]>([]);
  const [sectors, setSectors] = useState<Option[]>([]);
  const [seoDefaults, setSeoDefaults] = useState<SeoDefaults | null>(null);
  const [slugTouched, setSlugTouched] = useState(!creating);
  const [error, setError] = useState('');
  const [saved, setSaved] = useState(false);
  const [mockupOpen, setMockupOpen] = useState(false);
  const [mockupSrc, setMockupSrc] = useState('');
  const mockupDirty = useRef(false);
  const [saving, setSaving] = useState(false);
  const [reloadKey, setReloadKey] = useState(0);
  const [library, setLibrary] = useState<LibraryItem[]>([]);
  const [activeSection, setActiveSection] = useState(0);
  const [removeSection, setRemoveSection] = useState<number | null>(null);
  const [aiOpen, setAiOpen] = useState(false);
  const [aiPrompt, setAiPrompt] = useState('');
  const [aiUrl, setAiUrl] = useState('');
  const [aiPrints, setAiPrints] = useState(true);
  const [aiMockups, setAiMockups] = useState(true);
  const [aiMode, setAiMode] = useState<'replace' | 'append'>('replace');
  const [aiBusy, setAiBusy] = useState(false);
  const [aiError, setAiError] = useState('');
  const [aiNote, setAiNote] = useState('');
  const translationConfig = useTranslationConfig();
  const translationJob = useTranslationJob('case', form?.source_id ?? null, (_locale, translationId) => {
    if (translationId !== null) {
      navigate(`/cases/${translationId}`);
      return;
    }
    setReloadKey((value) => value + 1);
  });

  useEffect(() => {
    function onMessage(event: MessageEvent) {
      if (event.origin !== window.location.origin) {
        return;
      }

      const data = event.data as { type?: string; path?: string; alt?: string; dirty?: boolean } | null;
      if (data?.type === 'case-mockup-state') {
        mockupDirty.current = data.dirty === true;
        return;
      }
      if (!data || data.type !== 'case-mockup' || typeof data.path !== 'string' || data.path === '') {
        return;
      }
      mockupDirty.current = false;

      setForm((current) => (current ? {
        ...current,
        cover_path: data.path ?? null,
        cover_alt: typeof data.alt === 'string' ? data.alt : current.cover_alt,
      } : current));
      setSaved(false);
      setMockupOpen(false);
    }

    window.addEventListener('message', onMessage);
    return () => window.removeEventListener('message', onMessage);
  }, []);

  useEffect(() => {
    setActiveSection(0);

    if (creating) {
      void api<{ services: Option[]; sectors: Option[]; library: LibraryGroup[]; seo: SeoDefaults }>('/cases').then((payload) => {
        const items = payload.library.flatMap((group) => group.items);
        const starter = { ...empty, content: { sections: STARTER.filter((section) => items.some((item) => item.type === section.type)).map((section) => ({ id: newId(), ...section })) } };
        setLibrary(items);
        setForm(starter);
        setBaseline(JSON.stringify(starter));
        setServices(payload.services);
        setSectors(payload.sectors);
        setSeoDefaults(payload.seo);
      });
      return;
    }

    void api<{ data: Record<string, unknown>; services: Option[]; sectors: Option[]; library: LibraryGroup[]; seo: SeoDefaults }>(`/cases/${id}`).then((payload) => {
      const next = normalize(payload.data);
      setLibrary(payload.library.flatMap((group) => group.items));
      setForm(next);
      setBaseline(JSON.stringify(next));
      setServices(payload.services);
      setSectors(payload.sectors);
      setSeoDefaults(payload.seo);
    });
  }, [id, creating, reloadKey]);

  const outline = useMemo(() => outlineFor(form?.content.sections ?? [], library), [form?.content.sections, library]);

  if (!form) {
    return (
      <AppShell title="Cases">
        <div className="auth-loading"><div className="spinner" /></div>
      </AppShell>
    );
  }

  const dirty = JSON.stringify(form) !== baseline;

  function patch(next: Partial<CaseDetail>) {
    setForm((current) => (current ? { ...current, ...next } : current));
    setSaved(false);
  }

  function openMockup() {
    if (!form) {
      return;
    }

    const alt = form.cover_alt.trim() || [form.client, form.title].filter(Boolean).join(': ');
    mockupDirty.current = false;
    setMockupSrc(`/ferramentas/mockups?case=1&alt=${encodeURIComponent(alt)}`);
    setMockupOpen(true);
  }

  function closeMockup() {
    if (mockupDirty.current && !window.confirm('A foto gerada ainda não virou capa. Fechar descarta a foto.')) {
      return;
    }

    mockupDirty.current = false;
    setMockupOpen(false);
  }

  function toggleService(slug: string) {
    if (!form) {
      return;
    }
    patch({
      services: form.services.includes(slug)
        ? form.services.filter((item) => item !== slug)
        : [...form.services, slug],
    });
  }

  async function save() {
    if (!form) {
      return;
    }
    setSaving(true);
    setError('');
    try {
      const payload = await api<{ data: Record<string, unknown> }>(creating ? '/cases' : `/cases/${id}`, {
        method: creating ? 'POST' : 'PUT',
        body: JSON.stringify(form),
      });
      const next = normalize(payload.data);
      setForm(next);
      setBaseline(JSON.stringify(next));
      setSaved(true);
      if (creating && next.id) {
        navigate(`/cases/${next.id}`, { replace: true });
      }
    } catch (err) {
      setError(firstError(err));
    } finally {
      setSaving(false);
    }
  }

  const sections = form.content.sections;
  const current = outline[activeSection];

  function patchSections(next: CaseSection[]) {
    patch({ content: { sections: next } });
  }

  function addSection(item: LibraryItem) {
    patchSections([...sections, { id: newId(), type: item.type, data: {} }]);
    setActiveSection(sections.length);
  }

  function reorderSections(from: number, to: number) {
    if (from === to) {
      return;
    }
    const next = [...sections];
    const [moved] = next.splice(from, 1);
    next.splice(to, 0, moved);
    patchSections(next);
    setActiveSection(to);
  }

  const filled = sections.filter(hasContent);
  const hasAiUrl = /^https?:\/\/\S+\.\S+/i.test(aiUrl.trim());
  const filledFields = filled.length + [form.title, form.summary, form.client].filter(Boolean).length;

  const briefing = aiPrompt.trim() === BRIEFING_GUIDE.trim() ? '' : aiPrompt.trim();

  function openAi() {
    setAiError('');
    setAiPrompt((current) => current.trim() || BRIEFING_GUIDE);
    setAiUrl(form?.url ?? '');
    setAiOpen(true);
  }

  function applyDraft(draft: CaseDraft, mode: 'replace' | 'append') {
    if (!form) {
      return;
    }

    const pick = (current: string, next: string) => (mode === 'replace' ? next || current : current || next);
    let nextSections: CaseSection[];

    if (mode === 'append') {
      nextSections = [...sections, ...draft.sections];
    } else {
      const galleries = draft.sections.some((section) => section.type === 'site.gallery') ? [] : sections.filter(hasImages);
      const lastProse = draft.sections.map((section) => section.type).lastIndexOf('site.prose');
      nextSections = [...draft.sections];
      nextSections.splice(lastProse + 1, 0, ...galleries);
    }

    const client = pick(form.client, draft.client);

    patch({
      client,
      ...(!slugTouched && (creating || !form.slug) ? { slug: slugify(client) } : {}),
      sector: pick(form.sector, draft.sector),
      services: mode === 'replace' ? (draft.services.length ? draft.services : form.services) : (form.services.length ? form.services : draft.services),
      year: mode === 'replace' ? (draft.year ?? form.year) : (form.year ?? draft.year),
      url: aiUrl.trim() || form.url,
      title: pick(form.title, draft.title),
      summary: pick(form.summary, draft.summary),
      tags: mode === 'replace' ? (draft.tags.length ? draft.tags : form.tags) : (form.tags.length ? form.tags : draft.tags),
      seo_title: pick(form.seo_title, draft.seo_title),
      seo_description: pick(form.seo_description, draft.seo_description),
      content: { sections: nextSections },
      ...(draft.cover && !form.cover_path ? { cover_path: draft.cover.path, cover_alt: form.cover_alt || draft.cover.alt } : {}),
    });
    setActiveSection(mode === 'append' ? sections.length : 0);
  }

  async function generateDraft() {
    if (!form) {
      return;
    }

    setAiBusy(true);
    setAiError('');
    setAiNote('');
    try {
      const { token } = await api<{ token: string }>('/cases/draft', {
        method: 'POST',
        body: JSON.stringify({
          prompt: briefing || null,
          url: aiUrl.trim() || null,
          prints: hasAiUrl && aiPrints,
          mockups: hasAiUrl && aiPrints && aiMockups,
          case: {
            client: form.client,
            title: form.title,
            summary: form.summary,
            sector: sectors.find((option) => option.value === form.sector)?.label ?? '',
            year: form.year,
            services: form.services.map((slug) => services.find((option) => option.value === slug)?.label ?? slug),
          },
        }),
      });

      const deadline = Date.now() + 30 * 60 * 1000;
      let status: DraftStatus = { state: 'running' };
      while (status.state === 'running' && Date.now() < deadline) {
        await new Promise((resolve) => window.setTimeout(resolve, 2000));
        status = await api<DraftStatus>(`/cases/draft/${token}`);
      }

      if (status.state === 'failed' || !status.draft) {
        setAiError(status.message || 'A IA não devolveu o rascunho. Tente de novo.');
        return;
      }

      applyDraft(status.draft, filledFields ? aiMode : 'replace');
      setAiOpen(false);
      const notes = ['Rascunho aplicado. Revise os textos e salve.'];
      if (hasAiUrl && !status.draft.source_read) {
        notes.push('A URL do projeto não pôde ser lida, então o rascunho saiu sem o conteúdo do site e sem prints.');
      }
      if (status.draft.prints_failed > 0) {
        notes.push(`${status.draft.prints_failed} ${status.draft.prints_failed === 1 ? 'print falhou' : 'prints falharam'} na captura.`);
      }
      if (status.draft.mockups_unavailable) {
        notes.push('Os mockups não foram gerados porque falta META_API_KEY; a galeria saiu com as prints puras.');
      } else if (status.draft.mockups_failed > 0) {
        notes.push(`${status.draft.mockups_failed} ${status.draft.mockups_failed === 1 ? 'mockup não saiu' : 'mockups não saíram'} e ${status.draft.mockups_failed === 1 ? 'entrou como print pura' : 'entraram como print pura'}.`);
      }
      if (status.draft.cover && !form.cover_path) {
        notes.push('A capa foi preenchida com um dos mockups.');
      }
      setAiNote(notes.join(' '));
    } catch (err) {
      setAiError(firstError(err));
    } finally {
      setAiBusy(false);
    }
  }

  function confirmRemoveSection() {
    if (removeSection === null) {
      return;
    }
    const next = sections.filter((_, index) => index !== removeSection);
    patchSections(next);
    setActiveSection((index) => Math.max(0, Math.min(index > removeSection ? index - 1 : index, next.length - 1)));
    setRemoveSection(null);
  }

  const seoAi: FieldAiContext | undefined = form.translation_of ? undefined : {
    disabled: !translationConfig?.configured,
    request: () => ({
      kind: 'case',
      target: 'seo',
      title: [form.client, form.title].filter(Boolean).join(': '),
      description: form.summary,
      path: form.path ?? `/cases/${form.slug}`,
      seo_title: form.seo_title,
      seo_description: form.seo_description,
      seo_keyword: form.seo_keyword,
      sections: sections.map((section) => ({ type: section.type, data: section.data })),
    }),
  };

  const seoInput: SeoInput = {
    keyword: form.seo_keyword,
    seo_title: form.seo_title,
    seo_description: form.seo_description,
    slug: form.slug,
    path: form.path ?? `/cases/${form.slug}`,
    title: form.title,
    summary: form.summary,
    client: form.client,
    cover_alt: form.cover_alt,
    sections: sections.map((section) => ({ type: section.type, data: section.data })),
  };

  function applySeoFix(target: SeoTarget, value: string) {
    setSaved(false);
    setForm((current) => {
      if (!current) {
        return current;
      }
      const next = writeTarget({
        keyword: current.seo_keyword,
        seo_title: current.seo_title,
        seo_description: current.seo_description,
        slug: current.slug,
        path: current.path ?? '',
        title: current.title,
        summary: current.summary,
        client: current.client,
        cover_alt: current.cover_alt,
        sections: current.content.sections.map((section) => ({ type: section.type, data: section.data })),
      }, target, value);
      return {
        ...current,
        seo_title: next.seo_title,
        seo_description: next.seo_description,
        title: next.title ?? current.title,
        summary: next.summary ?? current.summary,
        cover_alt: next.cover_alt ?? current.cover_alt,
        content: { sections: current.content.sections.map((section, index) => ({ ...section, data: next.sections[index]?.data ?? section.data })) },
      };
    });
  }

  return (
    <AppShell
      eyebrow="Cases"
      title={creating ? 'Novo case' : form.client || 'Case'}
      subtitle={form.status === 'published' && !creating ? form.path ?? `/cases/${form.slug}` : 'Rascunho não aparece no site.'}
      titleAction={form.translation_of ? undefined : (
        <button
          className="btn btn-secondary"
          type="button"
          disabled={!translationConfig?.configured}
          title={translationConfig?.configured ? undefined : 'Defina OPENAI_API_KEY no .env'}
          onClick={openAi}
        >
          <Sparkles size={14} /> Gerar com IA
        </button>
      )}
      actions={(
        <div className="form-actions case-actions">
          <button className="btn btn-secondary" type="button" onClick={() => navigate('/cases')}>Voltar</button>
          {!creating ? (
            <LivePreviewButton
              previewPath={`preview/cases/${id}`}
              endpoint={`/preview/cases/${id}`}
              body={{
                content: form.content,
                client: form.client,
                title: form.title,
                summary: form.summary,
                sector: form.sector,
                year: form.year,
                url: form.url,
                tags: form.tags,
                services: form.services,
                cover_path: form.cover_path,
                cover_alt: form.cover_alt,
                cover_ratio: form.cover_ratio,
                seo_title: form.seo_title,
                seo_description: form.seo_description,
              }}
            />
          ) : null}
          {!creating && form.status === 'published' ? (
            <a className="btn btn-secondary" href={form.path ?? `/cases/${form.slug}`} target="_blank" rel="noreferrer">
              <ExternalLink size={14} /> Ver no site
            </a>
          ) : null}
          <button className="btn btn-primary" type="button" disabled={!dirty || saving} onClick={() => void save()}>
            {creating ? 'Criar case' : 'Salvar'}
          </button>
        </div>
      )}
    >
      {!creating && form.translations ? (
        <div className="case-langbar">
          <TranslationTabs
            current={form.locale ?? translationConfig?.default ?? 'pt-BR'}
            translations={form.translations}
            config={translationConfig}
            running={translationJob.running}
            onOpen={(locale) => {
              if (locale === translationConfig?.default) {
                navigate(`/cases/${form.source_id}`);
                return;
              }
              const target = form.translations?.[locale];
              if (target?.id) {
                navigate(`/cases/${target.id}`);
              }
            }}
            onTranslate={(locale) => void translationJob.start(locale)}
          />
          {form.translation_of ? (
            <button
              className="btn btn-secondary"
              type="button"
              disabled={!translationConfig?.configured || Boolean(form.locale && translationJob.running[form.locale])}
              onClick={() => {
                if (form.locale && window.confirm('Substituir esta tradução por uma nova tradução do original em português?')) {
                  void translationJob.start(form.locale);
                }
              }}
            >
              Retraduzir a partir do PT
            </button>
          ) : null}
        </div>
      ) : null}
      {translationJob.error ? <div className="form-error">{translationJob.error}</div> : null}
      {form.translation_of && form.stale ? <div className="form-warning">O original em português mudou depois desta tradução. Revise ou retraduza.</div> : null}
      {error ? <div className="form-error">{error}</div> : null}
      {saved && !dirty ? <div className="form-success">Case salvo.</div> : null}
      {aiNote ? <div className="form-success">{aiNote}</div> : null}

      <div className="case-editor">
        <div className="case-main">
          <Box title="Identificação">
            <div className="case-grid-2">
              <div className="field">
                <label>Cliente</label>
                <input
                  value={form.client}
                  onChange={(event) => patch({
                    client: event.target.value,
                    ...(slugTouched ? {} : { slug: slugify(event.target.value) }),
                  })}
                />
              </div>
              <div className="field">
                <label>Endereço</label>
                <div className="slug-field">
                  <span>/cases/</span>
                  <input
                    value={form.slug}
                    onChange={(event) => {
                      setSlugTouched(true);
                      patch({ slug: event.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '') });
                    }}
                  />
                </div>
              </div>
            </div>
            <div className="field">
              <label>Título</label>
              <input value={form.title} placeholder="O processo melhorado, em uma frase" onChange={(event) => patch({ title: event.target.value })} />
            </div>
            <div className="field">
              <label>Resumo</label>
              <textarea maxLength={300} value={form.summary} onChange={(event) => patch({ summary: event.target.value })} />
              <p className="hint">{form.summary.length}/300. Aparece no card da listagem e no bloco das páginas de serviço.</p>
            </div>
            <div className="case-grid-2">
              <div className="field">
                <label>Setor</label>
                <select value={form.sector} onChange={(event) => patch({ sector: event.target.value })}>
                  <option value="">Selecione</option>
                  {sectors.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                </select>
              </div>
              <div className="field">
                <label>Ano</label>
                <input
                  type="number"
                  value={form.year ?? ''}
                  onChange={(event) => patch({ year: event.target.value === '' ? null : Number(event.target.value) })}
                />
              </div>
            </div>
            <div className="field">
              <label>Link do projeto</label>
              <input value={form.url} placeholder="https://" onChange={(event) => patch({ url: event.target.value })} />
            </div>
            <StringList label="Etiquetas do card" value={form.tags} placeholder="Produto SaaS com IA" onChange={(tags) => patch({ tags })} />
            <p className="hint">Até 4. Aparecem abaixo do nome nos cards de case das páginas e da listagem.</p>
          </Box>

          <Box title="Capa" hint="A capa abre o case e ilustra os cards. Os prints dos cards vêm da primeira galeria das seções.">
            <MediaField label="Capa (16:10)" value={form.cover_path} onChange={(path) => patch({ cover_path: path })} />
            <button className="btn btn-secondary" type="button" onClick={openMockup}>Montar mockup</button>
            <p className="hint">Abre o estúdio, grava a foto em WebP otimizado e preenche o caminho e o alt da capa.</p>
            <div className="field">
              <label>Alt da capa</label>
              <input value={form.cover_alt} onChange={(event) => patch({ cover_alt: event.target.value })} />
            </div>
            <div className="field">
              <label>Proporção da capa no card em destaque</label>
              <select value={form.cover_ratio} onChange={(event) => patch({ cover_ratio: event.target.value })}>
                <option value="">Padrão (1,91:1)</option>
                <option value="16x9">16:9</option>
                <option value="16x10">16:10</option>
              </select>
            </div>
          </Box>

          <Box title="Seções" hint="Conteúdo da página do case, entre a capa e o próximo case. Funciona como o editor de páginas.">
            <div className="case-sections">
              <div className="case-sections-nav">
                <PageOutline
                  sections={outline}
                  active={activeSection}
                  onSelect={setActiveSection}
                  onReorder={reorderSections}
                  onRemove={setRemoveSection}
                  library={library}
                  onAdd={addSection}
                />
              </div>
              <div className="case-sections-form">
                {current ? (
                  <>
                    <h4>{current.label}</h4>
                    <SchemaForm
                      key={sections[activeSection]?.id ?? activeSection}
                      fields={current.fields}
                      content={sections[activeSection]?.data ?? {}}
                      context={{}}
                      ai={form.translation_of ? undefined : {
                        disabled: !translationConfig?.configured,
                        request: () => ({
                          kind: 'case',
                          index: activeSection,
                          title: [form.client, form.title].filter(Boolean).join(': '),
                          description: form.summary,
                          path: form.path ?? `/cases/${form.slug}`,
                          sections: sections.map((section) => ({ type: section.type, data: section.data })),
                        }),
                      }}
                      onChange={(data) => patchSections(sections.map((section, index) => (index === activeSection ? { ...section, data } : section)))}
                    />
                  </>
                ) : (
                  <p className="hint">Adicione uma seção pelo botão ao lado.</p>
                )}
              </div>
            </div>
          </Box>
        </div>

        <aside className="case-aside">
          <Box title="Publicação">
            <div className="status-pills">
              <button type="button" className={form.status === 'draft' ? 'is-on' : ''} onClick={() => patch({ status: 'draft' })}>Rascunho</button>
              <button type="button" className={form.status === 'published' ? 'is-on' : ''} onClick={() => patch({ status: 'published' })}>Publicado</button>
            </div>
            <div className="field">
              <label>
                <input type="checkbox" checked={form.featured} onChange={(event) => patch({ featured: event.target.checked })} /> Destaque em /cases
              </label>
            </div>
            <div className="field">
              <label>Ordem</label>
              <input type="number" value={form.sort} onChange={(event) => patch({ sort: Number(event.target.value) || 0 })} />
            </div>
          </Box>

          <Box title="Serviços" hint="O case fica disponível no bloco de cases destas páginas e no filtro de /cases.">
            <div className="case-checks">
              {services.map((option) => (
                <label key={option.value}>
                  <input type="checkbox" checked={form.services.includes(option.value)} onChange={() => toggleService(option.value)} />
                  <span>{option.label}</span>
                </label>
              ))}
              {services.length === 0 ? <p className="hint">Nenhuma página de serviço cadastrada.</p> : null}
            </div>
          </Box>

          <Box title="SEO" hint="Vazio usa o título e o resumo do case.">
            <div className="field">
              <label htmlFor="case-seo-keyword">Palavra-chave</label>
              <input
                id="case-seo-keyword"
                value={form.seo_keyword}
                maxLength={120}
                placeholder="ex.: automação de cobrança"
                aria-describedby="case-seo-keyword-hint"
                onChange={(event) => patch({ seo_keyword: event.target.value })}
              />
              <p className="hint" id="case-seo-keyword-hint">Termo principal que este case deve ranquear. A IA e a nota SEO usam este termo.</p>
            </div>
            <div className="field">
              <label htmlFor="case-seo-title">Título SEO</label>
              <FieldAiWrap ai={seoAi} type="text" path="seo_title" value={form.seo_title} onApply={(value) => patch({ seo_title: value })}>
                <input
                  id="case-seo-title"
                  value={form.seo_title}
                  placeholder={form.title ? `${form.title} | Case ${form.client}` : ''}
                  aria-describedby="case-seo-title-length"
                  onChange={(event) => patch({ seo_title: event.target.value })}
                />
              </FieldAiWrap>
              <SeoLength
                id="case-seo-title-length"
                kind="title"
                text={composeSeoTitle(form.seo_title.trim() || (form.title ? `${form.title} | Case ${form.client}` : ''), seoDefaults)}
                fallback={!form.seo_title.trim()}
              />
            </div>
            <div className="field">
              <label htmlFor="case-seo-description">Descrição SEO</label>
              <FieldAiWrap ai={seoAi} type="textarea" path="seo_description" value={form.seo_description} onApply={(value) => patch({ seo_description: value })}>
                <textarea
                  id="case-seo-description"
                  value={form.seo_description}
                  placeholder={form.summary}
                  aria-describedby="case-seo-description-length"
                  onChange={(event) => patch({ seo_description: event.target.value })}
                />
              </FieldAiWrap>
              <SeoLength id="case-seo-description-length" kind="description" text={form.seo_description.trim() || form.summary} fallback={!form.seo_description.trim()} />
            </div>
            <MediaField label="OG image" value={form.og_image} hint="Vazio usa a capa do case." onChange={(path) => patch({ og_image: path })} />
            {!creating ? (
              <OgCaptureButton
                endpoint={`/og-image/cases/${id}`}
                body={{
                  content: form.content,
                  client: form.client,
                  title: form.title,
                  summary: form.summary,
                  sector: form.sector,
                  year: form.year,
                  url: form.url,
                  tags: form.tags,
                  services: form.services,
                  cover_path: form.cover_path,
                  cover_alt: form.cover_alt,
                  cover_ratio: form.cover_ratio,
                  seo_title: form.seo_title,
                  seo_description: form.seo_description,
                }}
                onCaptured={(path) => patch({ og_image: path })}
              />
            ) : null}
          </Box>

          <Box title="Análise SEO e desempenho">
            <SeoInsights
              kind="case"
              id={form.id ?? null}
              aiDisabled={!translationConfig?.configured || Boolean(form.translation_of)}
              aiNote={form.translation_of ? 'Correções por IA só no case original em português.' : undefined}
              market={!form.translation_of}
              onKeyword={(keyword) => patch({ seo_keyword: keyword })}
              input={seoInput}
              lighthouse={form.id ? { kind: 'cases', id: form.id } : null}
              onApply={applySeoFix}
            />
          </Box>
        </aside>
      </div>
      {aiOpen ? (
        <Modal
          title="Gerar case com IA"
          eyebrow="Rascunho"
          wide
          onClose={() => { if (!aiBusy) { setAiOpen(false); } }}
          footer={(
            <>
              <button className="btn btn-secondary" type="button" disabled={aiBusy} onClick={() => setAiOpen(false)}>Cancelar</button>
              <button className="btn btn-primary" type="button" disabled={aiBusy || (!hasAiUrl && briefing.length < 20) || (briefing.length > 0 && briefing.length < 20)} onClick={() => void generateDraft()}>
                {aiBusy ? 'Gerando o case...' : 'Gerar case'}
              </button>
            </>
          )}
        >
          {aiError ? <div className="form-error">{aiError}</div> : null}
          <div className="field">
            <label>URL do projeto</label>
            <input type="url" value={aiUrl} disabled={aiBusy} placeholder="https://" onChange={(event) => setAiUrl(event.target.value)} />
            <p className="hint">A IA lê o site publicado para entender o cliente e o produto. A URL também vai para o Link do projeto.</p>
          </div>
          <div className="field">
            <label>Briefing do projeto</label>
            <textarea
              className="case-ai-prompt"
              value={aiPrompt}
              disabled={aiBusy}
              placeholder="O que o cliente fazia antes, o problema, o que foi construído, integrações, tecnologias, resultados medidos e o que não pode ser citado."
              onChange={(event) => setAiPrompt(event.target.value)}
            />
            <button className="btn btn-secondary btn-sm" type="button" disabled={aiBusy || aiPrompt === BRIEFING_GUIDE} onClick={() => setAiPrompt(BRIEFING_GUIDE)}>Restaurar roteiro</button>
            <p className="hint">Responda o que souber e deixe o resto em branco. Opcional com URL. A IA usa só o que estiver aqui, nos dados do case e no site do projeto. Número, resultado ou depoimento que não for informado não entra.</p>
          </div>
          <div className="field">
            <label>
              <input type="checkbox" checked={aiPrints && hasAiUrl} disabled={!hasAiUrl || aiBusy} onChange={(event) => setAiPrints(event.target.checked)} /> Capturar prints do site
            </label>
            <p className="hint">A IA escolhe de 2 a 4 páginas do site e captura em desktop e celular com o Chrome do estúdio de mockup. As prints entram numa galeria, já em WebP otimizado. Leva de 1 a 3 minutos a mais.</p>
          </div>
          <div className="field">
            <label>
              <input type="checkbox" checked={aiMockups && aiPrints && hasAiUrl} disabled={!hasAiUrl || !aiPrints || aiBusy} onChange={(event) => setAiMockups(event.target.checked)} /> Montar mockups com as prints
            </label>
            <p className="hint">Cada print entra numa foto de MacBook ou iPhone gerada pelo estúdio, com o mesmo acabamento de tela. Leva cerca de 1 minuto por print; se a foto falhar, a print entra pura. Sem capa no case, o mockup do desktop vira a capa.</p>
          </div>
          {filledFields ? (
            <div className="field">
              <label>Este case já tem conteúdo preenchido</label>
              <div className="status-pills">
                <button type="button" className={aiMode === 'replace' ? 'is-on' : ''} disabled={aiBusy} onClick={() => setAiMode('replace')}>Substituir</button>
                <button type="button" className={aiMode === 'append' ? 'is-on' : ''} disabled={aiBusy} onClick={() => setAiMode('append')}>Acrescentar ao final</button>
              </div>
              <p className="hint">
                {aiMode === 'replace'
                  ? 'Troca cliente, setor, serviços, ano, título, resumo, etiquetas, SEO e seções pelo rascunho. Galerias com imagens são mantidas, a menos que o rascunho traga prints novas.'
                  : 'Mantém o que existe e acrescenta as seções do rascunho; os demais campos só são preenchidos se estiverem vazios.'}
              </p>
            </div>
          ) : null}
          <p className="hint">Nada é salvo automaticamente: o rascunho entra no editor para revisão.</p>
        </Modal>
      ) : null}
      {removeSection !== null ? (
        <ConfirmDialog
          title="Remover seção?"
          message="A seção sai deste case. O conteúdo só deixa de existir depois de salvar."
          confirmLabel="Remover"
          onClose={() => setRemoveSection(null)}
          onConfirm={confirmRemoveSection}
        />
      ) : null}
      {mockupOpen ? (
        <div className="modal-backdrop">
          <div className="modal studio" role="dialog" aria-modal="true" aria-labelledby="mockup-title">
            <div className="modal-head">
              <div>
                <h2 id="mockup-title">Mockup da capa</h2>
                <p>Gere a foto do aparelho com a tela do projeto e grave como capa deste case.</p>
              </div>
              <button className="icon-btn" type="button" onClick={closeMockup} aria-label="Fechar">
                <X size={16} />
              </button>
            </div>
            <iframe title="Mockup da capa" src={mockupSrc} />
          </div>
        </div>
      ) : null}
    </AppShell>
  );
}
