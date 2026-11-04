import { type FormEvent, useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Languages, Pencil, Plus, Trash2 } from 'lucide-react';
import { AppShell } from '../components/AppShell';
import { BatchRunner } from '../components/BatchRunner';
import { Modal } from '../components/Modal';
import { api, firstError } from '../api';
import { formatDate } from '../lib';
import { useTour } from '../tour/TourProvider';
import { LangDots } from '../components/LangDots';
import { useTranslationConfig, type TranslationState } from '../components/TranslationBar';

type PageRow = {
  id: number;
  slug: string;
  template: string;
  seo_title: string;
  section: string;
  updated_at: string;
  translations?: Record<string, TranslationState>;
};

function normalize(value: string): string {
  return value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

function pageSlugFromTitle(value: string): string {
  return value
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '');
}

export function PagesScreen() {
  const navigate = useNavigate();
  const { registerActions } = useTour();
  const [pages, setPages] = useState<PageRow[]>([]);
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({ seo_title: '', slug: '' });
  const [slugManual, setSlugManual] = useState(false);
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);
  const [deleteSlug, setDeleteSlug] = useState<string | null>(null);
  const [query, setQuery] = useState('');
  const [translateOpen, setTranslateOpen] = useState(false);
  const [retranslate, setRetranslate] = useState(false);
  const translation = useTranslationConfig();

  function openCreate() {
    setError('');
    setSlugManual(false);
    setForm({ seo_title: '', slug: '' });
    setOpen(true);
  }

  async function load() {
    const payload = await api<{ data: PageRow[] }>('/pages');
    setPages(payload.data);
  }

  useEffect(() => {
    void load();
  }, []);

  useEffect(() => registerActions({
    'pages.openCreate': () => openCreate(),
    'pages.closeCreate': () => setOpen(false),
  }), [registerActions]);

  async function create(event: FormEvent) {
    event.preventDefault();
    setError('');
    setSaving(true);
    try {
      const payload = await api<{ data: PageRow }>('/pages', {
        method: 'POST',
        body: JSON.stringify(form),
      });
      setOpen(false);
      setSlugManual(false);
      setForm({ seo_title: '', slug: '' });
      navigate(`/paginas/${payload.data.id}`);
    } catch (err) {
      setError(firstError(err));
    } finally {
      setSaving(false);
    }
  }

  async function remove() {
    if (!deleteSlug) {
      return;
    }
    setError('');
    try {
      await api(`/pages/${deleteSlug}`, { method: 'DELETE' });
      setDeleteSlug(null);
      await load();
    } catch (err) {
      setError(firstError(err));
    }
  }

  const terms = normalize(query).split(/\s+/).filter(Boolean);
  const filtered = terms.length
    ? pages.filter((page) => {
      const haystack = normalize(`${page.seo_title} /${page.slug}`);
      return terms.every((term) => haystack.includes(term));
    })
    : pages;
  const firstDeletable = filtered.findIndex((page) => page.template !== 'home' && page.slug !== 'home');
  const sections = filtered.reduce<{ name: string; pages: { page: PageRow; index: number }[] }[]>((groups, page, index) => {
    const group = groups.find((item) => item.name === page.section);
    if (group) {
      group.pages.push({ page, index });
    } else {
      groups.push({ name: page.section, pages: [{ page, index }] });
    }
    return groups;
  }, []);

  return (
    <AppShell
      eyebrow="Conteúdo"
      title="Páginas"
      actions={(
        <>
          {translation?.translated.length ? (
            <button className="btn btn-secondary" type="button" onClick={() => setTranslateOpen(true)}>
              <Languages size={14} />
              Traduzir site
            </button>
          ) : null}
          <button
            className="btn btn-primary"
            type="button"
            data-tour="pages-add"
            onClick={() => openCreate()}
          >
            <Plus size={14} />
            Nova página
          </button>
        </>
      )}
    >
      <div className="toolbar">
        <input
          className="search"
          type="search"
          placeholder="Buscar por título ou endereço"
          value={query}
          onChange={(event) => setQuery(event.target.value)}
        />
      </div>
      {filtered.length === 0 ? (
        <div className="panel table-panel" data-tour="pages-table">
          <div className="empty">{pages.length ? 'Nenhuma página encontrada.' : 'Nenhuma página cadastrada.'}</div>
        </div>
      ) : (
      <div className="page-groups" data-tour="pages-table">
        {sections.map((section) => (
        <section className="panel table-panel" key={section.name}>
          <div className="panel-head">
            <h3>{section.name}</h3>
            <p>{section.pages.length === 1 ? '1 página' : `${section.pages.length} páginas`}</p>
          </div>
        <table>
          <thead>
            <tr>
              <th>Página</th>
              <th>Idiomas</th>
              <th>Atualizada</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {section.pages.map(({ page, index }) => (
              <tr key={page.id}>
                <td className="person">
                  <strong>{page.seo_title}</strong>
                  <span>/{page.slug === 'home' ? '' : page.slug}</span>
                </td>
                <td><LangDots translations={page.translations} linkTo={(id) => `/paginas/${id}`} /></td>
                <td>{formatDate(page.updated_at)}</td>
                <td>
                  <div className="row-actions">
                    <Link
                      className="icon-btn"
                      to={`/paginas/${page.id}`}
                      data-tour={index === 0 ? 'pages-edit' : undefined}
                    >
                      <Pencil size={14} />
                    </Link>
                    {page.template !== 'home' && page.slug !== 'home' ? (
                      <button
                        className="icon-btn"
                        type="button"
                        aria-label={`Excluir ${page.seo_title}`}
                        data-tour={index === firstDeletable ? 'pages-delete' : undefined}
                        onClick={() => setDeleteSlug(page.slug)}
                      >
                        <Trash2 size={14} />
                      </button>
                    ) : null}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        </section>
        ))}
      </div>
      )}

      {open ? (
        <Modal
          title="Nova página"
          eyebrow="Páginas"
          onClose={() => setOpen(false)}
          footer={(
            <>
              <button className="btn btn-secondary" type="button" onClick={() => setOpen(false)}>Cancelar</button>
              <button className="btn btn-primary" type="submit" form="page-create-form" disabled={saving}>Criar</button>
            </>
          )}
        >
          <form id="page-create-form" onSubmit={(event) => void create(event)} data-tour="pages-modal">
            {error ? <div className="form-error">{error}</div> : null}
            <div className="field">
              <label>Título</label>
              <input
                value={form.seo_title}
                onChange={(event) => {
                  const seo_title = event.target.value;
                  setForm({
                    seo_title,
                    slug: slugManual ? form.slug : pageSlugFromTitle(seo_title),
                  });
                }}
                required
              />
            </div>
            <div className="field">
              <label>Slug</label>
              <div className="slug-field">
                <span>/</span>
                <input
                  value={form.slug}
                  onChange={(event) => {
                    const slug = event.target.value.trim().toLowerCase().replace(/[^a-z0-9/-]/g, '');
                    setSlugManual(slug !== '');
                    setForm({ ...form, slug });
                  }}
                  required
                />
              </div>
            </div>
          </form>
        </Modal>
      ) : null}

      {translateOpen ? (
        <Modal
          title="Traduzir site inteiro"
          eyebrow="Tradução"
          onClose={() => setTranslateOpen(false)}
          footer={<button className="btn btn-secondary" type="button" onClick={() => setTranslateOpen(false)}>Fechar</button>}
        >
          {translation && !translation.configured ? (
            <div className="form-error">Defina a chave da OpenAI em SEO &gt; Integrações para traduzir com IA.</div>
          ) : (
            <BatchRunner
              endpoint="/translations/all"
              startLabel="Traduzir e publicar"
              unit="itens"
              body={() => ({ force: retranslate })}
              onDone={() => void load()}
            >
              <p className="desc">
                Traduz com IA todas as páginas e cases publicados, menus e textos do site para{' '}
                {translation?.locales.filter((locale) => translation.translated.includes(locale.code)).map((locale) => locale.name).join(' e ')}
                , e publica as traduções. Cada página traduzida ganha a própria OG image no idioma.
              </p>
              <label className="check">
                <input type="checkbox" checked={retranslate} onChange={(event) => setRetranslate(event.target.checked)} />
                Retraduzir também o que já está traduzido e em dia
              </label>
            </BatchRunner>
          )}
        </Modal>
      ) : null}

      {deleteSlug ? (
        <Modal
          title="Excluir página"
          eyebrow="Páginas"
          onClose={() => setDeleteSlug(null)}
          footer={(
            <>
              <button className="btn btn-secondary" type="button" onClick={() => setDeleteSlug(null)}>Cancelar</button>
              <button className="btn btn-primary" type="button" onClick={() => void remove()}>Excluir</button>
            </>
          )}
        >
          {error ? <div className="form-error">{error}</div> : null}
          <p className="desc">A página /{deleteSlug} será excluída, junto com as traduções dela. Links do menu que apontam para ela precisam ser ajustados à parte.</p>
        </Modal>
      ) : null}
    </AppShell>
  );
}
