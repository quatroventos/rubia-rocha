import { type FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import {
  ArrowDown,
  ArrowUp,
  Check,
  ChevronDown,
  ChevronUp,
  CornerDownRight,
  CornerLeftUp,
  ExternalLink,
  Eye,
  EyeOff,
  FileText,
  GripVertical,
  Link2,
  Plus,
  Trash2,
} from 'lucide-react';
import { AppShell } from '../components/AppShell';
import { useTranslationConfig, useTranslationJob } from '../components/TranslationBar';
import { ConfirmDialog } from '../components/ConfirmDialog';
import { SortableList } from '../components/SortableList';
import { api, firstError } from '../api';
import { useTour } from '../tour/TourProvider';

type MenuGroup = 'header' | 'footer_1' | 'footer_2';

type MenuItem = {
  id: number;
  label: string;
  href: string;
  group: MenuGroup;
  sort: number;
  is_active: boolean;
  parent_id: number | null;
  page_id: number | null;
};

type PageOption = {
  path?: string;
  id: number;
  slug: string;
  seo_title: string;
  template: string;
};

type FlatRow = MenuItem & { depth: 0 | 1 };

type PendingTab = MenuGroup | null;

const NEST_INDENT_PX = 36;

const groups: { key: MenuGroup; label: string; description: string }[] = [
  { key: 'header', label: 'Header', description: 'Navegação principal, no topo de todas as páginas.' },
  { key: 'footer_1', label: 'Footer 1', description: 'Primeira coluna de links do rodapé.' },
  { key: 'footer_2', label: 'Footer 2', description: 'Segunda coluna de links do rodapé.' },
];

function isHomePage(page: PageOption): boolean {
  return page.template === 'home' || page.slug === 'home';
}

function pageHref(page: PageOption): string {
  if (page.path) {
    return page.path;
  }
  return isHomePage(page) ? '/' : `/${page.slug}`;
}

/**
 * SEO titles carry the site suffix ("Quem somos | Escritório"); the menu label
 * should only be the page name.
 */
function pageTitle(page: PageOption): string {
  if (isHomePage(page)) {
    return 'Home';
  }
  const [head] = page.seo_title.split('|');
  const title = head.trim();

  return title !== '' ? title : page.seo_title;
}

function isExternalHref(href: string): boolean {
  return /^(https?:)?\/\//i.test(href) || /^(mailto|tel):/i.test(href);
}

function normalizeMenuHref(href: string): string {
  const value = href.trim();
  if (value === '' || isExternalHref(value)) {
    return value;
  }
  if (value === '/') {
    return '/';
  }

  return `/${value.replace(/^\/+|\/+$/g, '')}`;
}

function isDraftId(id: number): boolean {
  return id < 0;
}

function flatten(items: MenuItem[], group: MenuGroup): FlatRow[] {
  const bySort = (a: MenuItem, b: MenuItem) => a.sort - b.sort || a.id - b.id;
  const roots = items
    .filter((item) => item.group === group && item.parent_id === null)
    .sort(bySort);
  const rows: FlatRow[] = [];
  for (const root of roots) {
    rows.push({ ...root, depth: 0 });
    const children = items.filter((item) => item.parent_id === root.id).sort(bySort);
    for (const child of children) {
      rows.push({ ...child, depth: 1 });
    }
  }

  return rows;
}

/**
 * Groups the flat list in blocks of "root + its children" so move operations can
 * treat a parent and its submenu as a single unit.
 */
function toBlocks(rows: FlatRow[]): FlatRow[][] {
  const blocks: FlatRow[][] = [];
  for (const row of rows) {
    if (row.depth === 0 || blocks.length === 0) {
      blocks.push([row]);
    } else {
      blocks[blocks.length - 1].push(row);
    }
  }

  return blocks;
}

function applyOrderLocally(
  allItems: MenuItem[],
  group: MenuGroup,
  ordered: Array<{ id: number; parent_id: number | null }>,
): MenuItem[] {
  let rootSort = 0;
  const childSort = new Map<number, number>();
  const byId = new Map(ordered.map((row) => {
    let sort = 0;
    if (row.parent_id === null) {
      rootSort += 1;
      sort = rootSort;
    } else {
      sort = (childSort.get(row.parent_id) ?? 0) + 1;
      childSort.set(row.parent_id, sort);
    }

    return [row.id, { parent_id: row.parent_id, sort }] as const;
  }));

  return allItems.map((item) => {
    if (item.group !== group) {
      return item;
    }
    const next = byId.get(item.id);

    return next ? { ...item, parent_id: next.parent_id, sort: next.sort } : item;
  }).filter((item) => item.group !== group || byId.has(item.id));
}

function swap<T>(list: T[], a: number, b: number): T[] {
  const next = [...list];
  [next[a], next[b]] = [next[b], next[a]];

  return next;
}

function snapshotGroup(items: MenuItem[], group: MenuGroup): string {
  return JSON.stringify(
    flatten(items, group).map((row) => ({
      id: row.id,
      label: row.label,
      href: row.href,
      page_id: row.page_id,
      is_active: row.is_active,
      parent_id: row.parent_id,
      sort: row.sort,
      depth: row.depth,
    })),
  );
}

export function MenuScreen() {
  const { registerActions } = useTour();
  const [items, setItems] = useState<MenuItem[]>([]);
  const [savedSnapshot, setSavedSnapshot] = useState('');
  const [pages, setPages] = useState<PageOption[]>([]);
  const [tab, setTab] = useState<MenuGroup>('header');
  const [selectedPages, setSelectedPages] = useState<number[]>([]);
  const [custom, setCustom] = useState({ label: '', href: '' });
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [form, setForm] = useState({ label: '', href: '', is_active: true });
  const [error, setError] = useState('');
  const [ok, setOk] = useState('');
  const [busy, setBusy] = useState(false);
  const [confirming, setConfirming] = useState<FlatRow | null>(null);
  const [pendingTab, setPendingTab] = useState<PendingTab>(null);
  const [confirmDiscard, setConfirmDiscard] = useState(false);
  const [locale, setLocale] = useState('pt-BR');
  const translationConfig = useTranslationConfig();
  const translationJob = useTranslationJob('menus', null, () => {
    void load(locale).then(() => setOk('Menus traduzidos. Revise e salve se ajustar algo.')).catch((err) => setError(firstError(err)));
  });
  const draftSeq = useRef(-1);

  function nextDraftId(): number {
    const id = draftSeq.current;
    draftSeq.current -= 1;

    return id;
  }

  function rememberSaved(nextItems: MenuItem[], group: MenuGroup = tab) {
    setSavedSnapshot(snapshotGroup(nextItems, group));
  }

  async function load(nextLocale = locale) {
    const payload = await api<{ data: MenuItem[]; pages: PageOption[] }>(`/menu-items?locale=${encodeURIComponent(nextLocale)}`);
    setItems(payload.data);
    setPages(payload.pages);
    rememberSaved(payload.data, tab);
  }

  useEffect(() => {
    void load().catch((err) => setError(firstError(err)));
  }, []);

  function switchLocale(nextLocale: string) {
    if (nextLocale === locale) {
      return;
    }
    if (dirty && !window.confirm('Há alterações não salvas neste menu. Trocar de idioma descarta essas alterações.')) {
      return;
    }
    setLocale(nextLocale);
    setExpandedId(null);
    setSelectedPages([]);
    setError('');
    setOk('');
    void load(nextLocale).catch((err) => setError(firstError(err)));
  }

  const rows = useMemo(() => flatten(items, tab), [items, tab]);
  const dirty = useMemo(
    () => savedSnapshot !== '' && snapshotGroup(items, tab) !== savedSnapshot,
    [items, tab, savedSnapshot],
  );
  const group = groups.find((item) => item.key === tab) ?? groups[0];
  const counts = useMemo(() => {
    const map = new Map<MenuGroup, number>();
    for (const item of items) {
      map.set(item.group, (map.get(item.group) ?? 0) + 1);
    }

    return map;
  }, [items]);

  // Seeded/legacy items often have href but null page_id — match both.
  const linked = useMemo(() => {
    const ids = new Set<number>();
    const hrefs = new Set<string>();

    for (const item of items) {
      if (item.group !== tab) {
        continue;
      }
      if (item.page_id) {
        ids.add(item.page_id);
      }
      hrefs.add(normalizeMenuHref(item.href));
    }

    for (const page of pages) {
      if (ids.has(page.id)) {
        continue;
      }
      if (hrefs.has(pageHref(page))) {
        ids.add(page.id);
      }
    }

    return ids;
  }, [items, tab, pages]);
  const availablePages = pages.filter((page) => !linked.has(page.id));
  const allAvailableSelected = availablePages.length > 0
    && availablePages.every((page) => selectedPages.includes(page.id));

  useEffect(() => {
    if (!dirty) {
      return undefined;
    }
    const onBeforeUnload = (event: BeforeUnloadEvent) => {
      event.preventDefault();
      event.returnValue = '';
    };
    window.addEventListener('beforeunload', onBeforeUnload);

    return () => window.removeEventListener('beforeunload', onBeforeUnload);
  }, [dirty]);

  function goToTab(next: MenuGroup) {
    setTab(next);
    setSelectedPages([]);
    setExpandedId(null);
    setError('');
    setOk('');
    setPendingTab(null);
    setConfirmDiscard(false);
    setSavedSnapshot(snapshotGroup(items, next));
  }

  function requestTab(next: MenuGroup) {
    if (next === tab) {
      return;
    }
    if (dirty) {
      setPendingTab(next);

      return;
    }
    goToTab(next);
  }

  function expand(row: MenuItem) {
    setExpandedId(row.id);
    setForm({ label: row.label, href: row.href, is_active: row.is_active });
    setError('');
    setOk('');
  }

  function collapse() {
    setExpandedId(null);
  }

  useEffect(() => registerActions({
    'menu.openEdit': () => {
      const first = rows[0];
      if (first) {
        expand(first);
      }
    },
    'menu.close': () => collapse(),
  }), [registerActions, rows]);

  function applyLocalOrder(ordered: Array<{ id: number; parent_id: number | null }>) {
    setError('');
    setOk('');
    setItems((current) => applyOrderLocally(current, tab, ordered));
  }

  function applyExpanded(event: FormEvent) {
    event.preventDefault();
    if (expandedId === null) {
      return;
    }
    setItems((current) => current.map((item) => (
      item.id === expandedId
        ? { ...item, label: form.label.trim(), href: form.href.trim(), is_active: form.is_active }
        : item
    )));
    collapse();
  }

  function toggleVisibility(row: MenuItem) {
    setItems((current) => current.map((item) => (
      item.id === row.id ? { ...item, is_active: !item.is_active } : item
    )));
    if (expandedId === row.id) {
      setForm((current) => ({ ...current, is_active: !row.is_active }));
    }
  }

  function addPages() {
    const created: MenuItem[] = [];
    let sortBase = items
      .filter((item) => item.group === tab && item.parent_id === null)
      .reduce((max, item) => Math.max(max, item.sort), 0);

    for (const pageId of selectedPages) {
      const page = pages.find((item) => item.id === pageId);
      if (!page) {
        continue;
      }
      sortBase += 1;
      created.push({
        id: nextDraftId(),
        label: pageTitle(page),
        href: pageHref(page),
        group: tab,
        sort: sortBase,
        is_active: true,
        parent_id: null,
        page_id: page.id,
      });
    }

    if (created.length === 0) {
      return;
    }
    setItems((current) => [...current, ...created]);
    setSelectedPages([]);
    setError('');
    setOk('');
  }

  function addCustom(event: FormEvent) {
    event.preventDefault();
    const sort = items
      .filter((item) => item.group === tab && item.parent_id === null)
      .reduce((max, item) => Math.max(max, item.sort), 0) + 1;

    setItems((current) => [
      ...current,
      {
        id: nextDraftId(),
        label: custom.label.trim(),
        href: custom.href.trim(),
        group: tab,
        sort,
        is_active: true,
        parent_id: null,
        page_id: null,
      },
    ]);
    setCustom({ label: '', href: '' });
    setError('');
    setOk('');
  }

  function remove(row: FlatRow) {
    setConfirming(null);
    setItems((current) => current.filter((item) => item.id !== row.id && item.parent_id !== row.id));
    if (expandedId === row.id) {
      collapse();
    }
  }

  function hasChildren(row: MenuItem): boolean {
    return rows.some((item) => item.parent_id === row.id);
  }

  function reorder(from: number, to: number, nest?: boolean) {
    const moved = rows[from];
    if (!moved) {
      return;
    }
    const next = rows.filter((_, index) => index !== from);
    let insertAt = to > from ? to - 1 : to;
    insertAt = Math.max(0, Math.min(insertAt, next.length));

    let parentId: number | null = null;
    if (nest) {
      const above = next[insertAt - 1];
      if (!above) {
        return;
      }
      parentId = above.depth === 0 ? above.id : above.parent_id;
    } else if (next[insertAt]?.depth === 1) {
      parentId = next[insertAt].parent_id;
    }

    if (parentId === moved.id) {
      return;
    }
    if (parentId !== null && hasChildren(moved)) {
      setError('Este item tem subitens. Mova os subitens para fora antes de transformá-lo em submenu.');

      return;
    }

    next.splice(insertAt, 0, { ...moved, parent_id: parentId, depth: parentId === null ? 0 : 1 });
    applyLocalOrder(next);
  }

  function moveRow(row: FlatRow, direction: -1 | 1) {
    const blocks = toBlocks(rows);
    if (row.depth === 0) {
      const index = blocks.findIndex((block) => block[0].id === row.id);
      const target = index + direction;
      if (index === -1 || target < 0 || target >= blocks.length) {
        return;
      }
      applyLocalOrder(swap(blocks, index, target).flat());

      return;
    }

    const blockIndex = blocks.findIndex((block) => block[0].id === row.parent_id);
    if (blockIndex === -1) {
      return;
    }
    const block = blocks[blockIndex];
    const index = block.findIndex((item) => item.id === row.id);
    const target = index + direction;
    if (target < 1 || target >= block.length) {
      return;
    }
    const nextBlocks = [...blocks];
    nextBlocks[blockIndex] = swap(block, index, target);
    applyLocalOrder(nextBlocks.flat());
  }

  function indent(row: FlatRow) {
    const blocks = toBlocks(rows);
    const index = blocks.findIndex((block) => block[0].id === row.id);
    if (index <= 0 || hasChildren(row)) {
      return;
    }
    const parent = blocks[index - 1][0];
    const nextBlocks = [...blocks];
    nextBlocks[index - 1] = [...blocks[index - 1], { ...row, parent_id: parent.id, depth: 1 }];
    nextBlocks.splice(index, 1);
    applyLocalOrder(nextBlocks.flat());
  }

  function outdent(row: FlatRow) {
    const blocks = toBlocks(rows);
    const index = blocks.findIndex((block) => block[0].id === row.parent_id);
    if (index === -1) {
      return;
    }
    const nextBlocks = [...blocks];
    nextBlocks[index] = blocks[index].filter((item) => item.id !== row.id);
    nextBlocks.splice(index + 1, 0, [{ ...row, parent_id: null, depth: 0 }]);
    applyLocalOrder(nextBlocks.flat());
  }

  function previousRoot(row: FlatRow): FlatRow | null {
    const blocks = toBlocks(rows);
    const index = blocks.findIndex((block) => block[0].id === row.id);

    return index > 0 ? blocks[index - 1][0] : null;
  }

  function canMove(row: FlatRow, direction: -1 | 1): boolean {
    const siblings = rows.filter((item) => item.parent_id === row.parent_id);
    const index = siblings.findIndex((item) => item.id === row.id);

    return index + direction >= 0 && index + direction < siblings.length;
  }

  async function saveMenu() {
    const draftItems = expandedId === null
      ? items
      : items.map((item) => (
        item.id === expandedId
          ? { ...item, label: form.label.trim(), href: form.href.trim(), is_active: form.is_active }
          : item
      ));

    if (expandedId !== null) {
      setItems(draftItems);
      collapse();
    }

    setError('');
    setOk('');
    setBusy(true);
    try {
      const liveRows = flatten(draftItems, tab);
      const response = await api<{ data: MenuItem[] }>('/menu-items/sync', {
        method: 'PUT',
        body: JSON.stringify({
          locale,
          group: tab,
          items: liveRows.map((row) => ({
            id: isDraftId(row.id) ? null : row.id,
            label: row.label,
            href: row.href,
            page_id: row.page_id,
            is_active: row.is_active,
            depth: row.depth,
          })),
        }),
      });

      const nextItems = [
        ...draftItems.filter((item) => item.group !== tab),
        ...response.data,
      ];
      setItems(nextItems);
      rememberSaved(nextItems, tab);
      setOk('Menu salvo.');
    } catch (err) {
      setError(firstError(err));
    } finally {
      setBusy(false);
    }
  }

  async function discardChanges() {
    const nextTab = pendingTab;
    setConfirmDiscard(false);
    setError('');
    setOk('');
    setBusy(true);
    try {
      const payload = await api<{ data: MenuItem[]; pages: PageOption[] }>(`/menu-items?locale=${encodeURIComponent(locale)}`);
      setItems(payload.data);
      setPages(payload.pages);
      setExpandedId(null);
      setSelectedPages([]);
      setPendingTab(null);
      if (nextTab) {
        setTab(nextTab);
        rememberSaved(payload.data, nextTab);
      } else {
        rememberSaved(payload.data, tab);
      }
    } catch (err) {
      setError(firstError(err));
    } finally {
      setBusy(false);
    }
  }

  function rowIcon(row: MenuItem) {
    if (row.page_id) {
      return <FileText size={14} />;
    }

    return isExternalHref(row.href) ? <ExternalLink size={14} /> : <Link2 size={14} />;
  }

  function rowKind(row: MenuItem): string {
    if (row.page_id) {
      return 'Página do site';
    }

    return isExternalHref(row.href) ? 'Link externo' : 'Link personalizado';
  }

  const confirmingChildren = confirming ? rows.filter((item) => item.parent_id === confirming.id).length : 0;

  return (
    <AppShell
      eyebrow="Configuração"
      title="Menu"
      subtitle="Monte o menu, organize a ordem e salve quando estiver pronto. Nada vai ao site antes de salvar."
      actions={(
        <div className="menu-header-actions">
          {dirty ? (
            <button
              className="btn btn-secondary"
              type="button"
              disabled={busy}
              onClick={() => setConfirmDiscard(true)}
            >
              Descartar
            </button>
          ) : null}
          <button
            className="btn btn-primary"
            type="button"
            disabled={busy || !dirty}
            data-tour="menu-save"
            onClick={() => void saveMenu()}
          >
            Salvar menu
          </button>
        </div>
      )}
    >
      {translationConfig && translationConfig.locales.length > 1 ? (
        <div className="case-langbar">
          <div className="lang-tabs" role="tablist" aria-label="Idioma do menu">
            {translationConfig.locales.map((item) => (
              <button
                key={item.code}
                type="button"
                role="tab"
                aria-selected={locale === item.code}
                className={`lang-tab${locale === item.code ? ' is-on' : ''}`}
                onClick={() => switchLocale(item.code)}
              >
                <b>{item.short}</b>
                <span>{item.name}</span>
              </button>
            ))}
          </div>
          {locale !== translationConfig.default ? (
            <button
              className="btn btn-secondary"
              type="button"
              disabled={!translationConfig.configured || Boolean(translationJob.running[locale])}
              title={translationConfig.configured ? undefined : 'Defina OPENAI_API_KEY no .env'}
              onClick={() => {
                if (window.confirm('Recriar todos os menus deste idioma a partir dos menus em português, com rótulos traduzidos por IA?')) {
                  void translationJob.start(locale);
                }
              }}
            >
              {translationJob.running[locale] ? 'Traduzindo' : 'Gerar a partir do PT com IA'}
            </button>
          ) : null}
        </div>
      ) : null}
      {translationJob.error ? <div className="form-error">{translationJob.error}</div> : null}

      <div className="resource-tabs menu-tabs" data-tour="menu-tabs">
        {groups.map((item) => (
          <button
            key={item.key}
            type="button"
            className={tab === item.key ? 'is-on' : ''}
            onClick={() => requestTab(item.key)}
          >
            {item.label}
            <span className="menu-tab-count">{counts.get(item.key) ?? 0}</span>
          </button>
        ))}
      </div>

      {error ? <div className="form-error">{error}</div> : null}
      {ok ? <div className="form-success">{ok}</div> : null}

      <div className="menu-builder">
        <div className="menu-builder-panel">
          <div className="panel" data-tour="menu-pages">
            <div className="panel-head menu-panel-head">
              <div>
                <h3>Páginas do site</h3>
                <p>Marque as páginas e adicione ao {group.label}.</p>
              </div>
              {availablePages.length > 1 ? (
                <button
                  type="button"
                  className="btn btn-text"
                  onClick={() => setSelectedPages(allAvailableSelected ? [] : availablePages.map((page) => page.id))}
                >
                  {allAvailableSelected ? 'Limpar' : 'Marcar todas'}
                </button>
              ) : null}
            </div>
            <div className="panel-body">
              {pages.length === 0 ? (
                <div className="empty empty-compact">Nenhuma página publicada.</div>
              ) : (
                <div className="menu-page-list">
                  {pages.map((page) => {
                    const inMenu = linked.has(page.id);
                    const selected = selectedPages.includes(page.id);

                    return (
                      <label
                        key={page.id}
                        className={`menu-page-option${inMenu ? ' is-linked' : ''}${selected ? ' is-selected' : ''}`}
                      >
                        <input
                          type="checkbox"
                          checked={selected}
                          disabled={inMenu || busy}
                          onChange={(event) => {
                            setSelectedPages((current) => (
                              event.target.checked
                                ? [...current, page.id]
                                : current.filter((id) => id !== page.id)
                            ));
                          }}
                        />
                        <span className="menu-page-text">
                          <strong>{pageTitle(page)}</strong>
                          <span>{pageHref(page)}</span>
                        </span>
                        {inMenu ? (
                          <span className="menu-page-state">
                            <Check size={11} />
                            No menu
                          </span>
                        ) : null}
                      </label>
                    );
                  })}
                </div>
              )}
              <button
                className="btn btn-primary menu-add-btn"
                type="button"
                disabled={selectedPages.length === 0 || busy}
                onClick={() => addPages()}
              >
                <Plus size={14} />
                {selectedPages.length > 1 ? `Adicionar ${selectedPages.length} páginas` : 'Adicionar ao menu'}
              </button>
            </div>
          </div>

          <div className="panel" data-tour="menu-custom">
            <div className="panel-head">
              <h3>Link personalizado</h3>
              <p>Para endereços externos ou que não estão na lista de páginas.</p>
            </div>
            <div className="panel-body">
              <form onSubmit={(event) => addCustom(event)}>
                <div className="field">
                  <label htmlFor="menu-custom-href">Endereço</label>
                  <input
                    id="menu-custom-href"
                    value={custom.href}
                    onChange={(event) => setCustom({ ...custom, href: event.target.value })}
                    placeholder="https://... ou /pagina"
                    required
                  />
                </div>
                <div className="field">
                  <label htmlFor="menu-custom-label">Texto do link</label>
                  <input
                    id="menu-custom-label"
                    value={custom.label}
                    onChange={(event) => setCustom({ ...custom, label: event.target.value })}
                    placeholder="Como vai aparecer no menu"
                    required
                  />
                </div>
                <button className="btn btn-secondary menu-add-btn" type="submit" disabled={busy}>
                  <Plus size={14} />
                  Adicionar link
                </button>
              </form>
            </div>
          </div>
        </div>

        <div className="panel menu-structure" data-tour="menu-structure">
          <div className="panel-head menu-panel-head">
            <div>
              <h3>Estrutura do {group.label}</h3>
              <p>{group.description}</p>
            </div>
            <span className={`menu-save-status${dirty ? ' is-visible is-dirty' : ''}`}>
              {dirty ? 'Alterações não salvas' : null}
            </span>
          </div>
          <div className="panel-body">
            {rows.length === 0 ? (
              <div className="empty menu-empty">
                <strong>Este menu ainda está vazio.</strong>
                <span>Marque páginas ao lado e clique em Adicionar ao menu, ou crie um link personalizado. Depois use Salvar menu.</span>
              </div>
            ) : (
              <>
                <SortableList
                  items={rows}
                  allowNest
                  nestIndentPx={NEST_INDENT_PX}
                  depthOf={(row) => row.depth}
                  onReorder={(from, to, nest) => reorder(from, to, nest)}
                  renderItem={(row, index, handle) => {
                    const isOpen = expandedId === row.id;
                    const parent = row.depth === 1 ? rows.find((item) => item.id === row.parent_id) : null;
                    const nestTarget = row.depth === 0 ? previousRoot(row) : null;
                    const blocked = row.depth === 0 && hasChildren(row);

                    return (
                      <div
                        className={[
                          'menu-tree-item',
                          row.depth === 1 ? 'is-child' : '',
                          isOpen ? 'is-open' : '',
                          row.is_active ? '' : 'is-hidden',
                        ].filter(Boolean).join(' ')}
                      >
                        <div className="menu-tree-row">
                          <button
                            type="button"
                            className="section-map-drag menu-tree-drag"
                            aria-label={`Arrastar ${row.label}`}
                            title="Arrastar para reordenar"
                            {...handle}
                          >
                            <GripVertical size={14} />
                          </button>
                          <span className="menu-tree-icon" title={rowKind(row)}>{rowIcon(row)}</span>
                          <button
                            type="button"
                            className="menu-tree-label"
                            onClick={() => (isOpen ? collapse() : expand(row))}
                            aria-expanded={isOpen}
                          >
                            <strong>{row.label}</strong>
                            <span>{row.href}</span>
                          </button>
                          {!row.is_active ? <span className="badge badge-neutral">Oculto</span> : null}
                          <div className="menu-tree-actions">
                            <button
                              className="icon-btn"
                              type="button"
                              disabled={busy}
                              onClick={() => toggleVisibility(row)}
                              aria-label={row.is_active ? `Ocultar ${row.label} no site` : `Mostrar ${row.label} no site`}
                              title={row.is_active ? 'Ocultar no site' : 'Mostrar no site'}
                            >
                              {row.is_active ? <Eye size={14} /> : <EyeOff size={14} />}
                            </button>
                            <button
                              className={`icon-btn${isOpen ? ' is-on' : ''}`}
                              type="button"
                              data-tour={index === 0 ? 'menu-edit' : undefined}
                              onClick={() => (isOpen ? collapse() : expand(row))}
                              aria-label={isOpen ? `Fechar ${row.label}` : `Editar ${row.label}`}
                              title={isOpen ? 'Fechar' : 'Editar'}
                            >
                              {isOpen ? <ChevronUp size={14} /> : <ChevronDown size={14} />}
                            </button>
                            <button
                              className="icon-btn danger"
                              type="button"
                              disabled={busy}
                              onClick={() => setConfirming(row)}
                              aria-label={`Remover ${row.label}`}
                              title="Remover do menu"
                            >
                              <Trash2 size={14} />
                            </button>
                          </div>
                        </div>

                        {isOpen ? (
                          <form
                            className="menu-tree-editor"
                            data-tour="menu-modal"
                            onSubmit={applyExpanded}
                          >
                            <div className="menu-tree-editor-grid">
                              <div className="field">
                                <label htmlFor={`menu-label-${row.id}`}>Texto do link</label>
                                <input
                                  id={`menu-label-${row.id}`}
                                  value={form.label}
                                  onChange={(event) => setForm({ ...form, label: event.target.value })}
                                  required
                                />
                              </div>
                              <div className="field">
                                <label htmlFor={`menu-href-${row.id}`}>Destino</label>
                                <input
                                  id={`menu-href-${row.id}`}
                                  value={form.href}
                                  onChange={(event) => setForm({ ...form, href: event.target.value })}
                                  required
                                />
                                <p className="hint">
                                  {row.page_id
                                    ? 'Ligado a uma página do site. Altere só se quiser apontar para outro endereço.'
                                    : 'Use /slug para páginas internas ou https:// para links externos.'}
                                </p>
                              </div>
                            </div>
                            <div className="field">
                              <label className="check">
                                <input
                                  type="checkbox"
                                  checked={form.is_active}
                                  onChange={(event) => setForm({ ...form, is_active: event.target.checked })}
                                />
                                Visível no site
                              </label>
                            </div>

                            <div className="menu-tree-move">
                              <span className="menu-tree-move-label">Mover</span>
                              <button
                                type="button"
                                className="btn btn-secondary btn-sm"
                                disabled={busy || !canMove(row, -1)}
                                onClick={() => moveRow(row, -1)}
                              >
                                <ArrowUp size={13} />
                                Subir
                              </button>
                              <button
                                type="button"
                                className="btn btn-secondary btn-sm"
                                disabled={busy || !canMove(row, 1)}
                                onClick={() => moveRow(row, 1)}
                              >
                                <ArrowDown size={13} />
                                Descer
                              </button>
                              {row.depth === 0 ? (
                                <button
                                  type="button"
                                  className="btn btn-secondary btn-sm"
                                  disabled={busy || !nestTarget || blocked}
                                  title={blocked ? 'Itens com subitens não podem virar submenu.' : undefined}
                                  onClick={() => indent(row)}
                                >
                                  <CornerDownRight size={13} />
                                  {nestTarget ? `Virar submenu de "${nestTarget.label}"` : 'Virar submenu'}
                                </button>
                              ) : (
                                <button
                                  type="button"
                                  className="btn btn-secondary btn-sm"
                                  disabled={busy}
                                  title={parent ? `Sair de "${parent.label}"` : undefined}
                                  onClick={() => outdent(row)}
                                >
                                  <CornerLeftUp size={13} />
                                  Voltar ao nível principal
                                </button>
                              )}
                            </div>

                            <div className="menu-tree-editor-foot">
                              <span className="menu-tree-kind">{rowKind(row)}</span>
                              <div className="menu-tree-editor-actions">
                                <button className="btn btn-secondary btn-sm" type="button" onClick={collapse}>
                                  Cancelar
                                </button>
                                <button className="btn btn-primary btn-sm" type="submit">
                                  Aplicar
                                </button>
                              </div>
                            </div>
                          </form>
                        ) : null}
                      </div>
                    );
                  }}
                />
                <p className="menu-tree-hint">
                  Arraste pela alça para reordenar. Puxe um item um pouco para a direita para virar submenu.
                  As mudanças só entram no site depois de Salvar menu.
                </p>
              </>
            )}
          </div>
        </div>
      </div>

      {confirming ? (
        <ConfirmDialog
          title={`Remover "${confirming.label}"?`}
          message={confirmingChildren > 0
            ? `O item sai do rascunho do ${group.label} junto com ${confirmingChildren === 1 ? 'seu subitem' : `seus ${confirmingChildren} subitens`}. Só some do site depois de Salvar menu.`
            : `O item sai do rascunho do ${group.label}. Só some do site depois de Salvar menu.`}
          confirmLabel="Remover"
          busy={busy}
          onConfirm={() => remove(confirming)}
          onClose={() => setConfirming(null)}
        />
      ) : null}

      {pendingTab ? (
        <ConfirmDialog
          title="Alterações não salvas"
          message={`Você mudou o ${group.label} e ainda não salvou. Descartar e trocar de aba?`}
          confirmLabel="Descartar e trocar"
          busy={busy}
          onConfirm={() => void discardChanges()}
          onClose={() => setPendingTab(null)}
        />
      ) : null}

      {confirmDiscard ? (
        <ConfirmDialog
          title="Descartar alterações?"
          message={`As mudanças do ${group.label} voltam para a última versão salva.`}
          confirmLabel="Descartar"
          busy={busy}
          onConfirm={() => void discardChanges()}
          onClose={() => setConfirmDiscard(false)}
        />
      ) : null}
    </AppShell>
  );
}
