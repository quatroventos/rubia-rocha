import { type KeyboardEvent, type RefObject, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import {
  AlignLeft,
  ClipboardList,
  Columns2,
  LayoutGrid,
  List,
  type LucideIcon,
  MapPin,
  Megaphone,
  PanelTop,
  Play,
  Plus,
  RectangleHorizontal,
  Search,
  SquareSplitHorizontal,
} from 'lucide-react';
import type { Field } from './SchemaForm';

export type LibraryItem = {
  type: string;
  label: string;
  preview: string;
  origin: string;
  fields: Field[];
};

type Family = 'hero' | 'cards' | 'split' | 'list' | 'media' | 'cta' | 'text';

type FamilyMeta = {
  label: string;
  order: number;
};

const FAMILIES: Record<Family, FamilyMeta> = {
  hero: { label: 'Abertura', order: 0 },
  cta: { label: 'Chamadas e faixas', order: 1 },
  split: { label: 'Texto e imagem', order: 2 },
  cards: { label: 'Cards', order: 3 },
  list: { label: 'Listas', order: 4 },
  media: { label: 'Mídia e mapa', order: 5 },
  text: { label: 'Texto', order: 6 },
};

type PreviewMeta = {
  family: Family;
  icon: LucideIcon;
  description: string;
};

const SPLIT: PreviewMeta = { family: 'split', icon: Columns2, description: 'Texto de um lado, imagem do outro' };
const CARDS: PreviewMeta = { family: 'cards', icon: LayoutGrid, description: 'Grade de cards com título e texto' };
const LIST: PreviewMeta = { family: 'list', icon: List, description: 'Título com lista de itens' };

const META: Record<string, PreviewMeta> = {
  hero: { family: 'hero', icon: PanelTop, description: 'Topo da página com título, texto e imagem' },
  strip: { family: 'cta', icon: RectangleHorizontal, description: 'Faixa estreita com frase de destaque' },
  cta: { family: 'cta', icon: Megaphone, description: 'Convite final com botão de ação' },
  pillars: { ...CARDS, description: 'Três cards com ícone, título e texto' },
  solutions: { ...CARDS, description: 'Grade de cards de soluções' },
  cards: { ...CARDS, description: 'Cards de áreas de atuação' },
  values: { ...CARDS, description: 'Cards com os valores da empresa' },
  specialties: { ...CARDS, description: 'Cards dos núcleos especializados' },
  principles: { ...CARDS, description: 'Cards com princípios em destaque' },
  process: { ...CARDS, description: 'Etapas em cards numerados' },
  management: SPLIT,
  excellence: SPLIT,
  coverage: SPLIT,
  culture: SPLIT,
  purpose: SPLIT,
  technology: { ...SPLIT, description: 'Texto e imagem com lista de recursos' },
  ethics: { ...SPLIT, description: 'Texto, imagem e canais de contato' },
  opportunity: { ...SPLIT, description: 'Texto e imagem com chamada para vagas' },
  channels: { family: 'split', icon: Columns2, description: 'Canais de atendimento com ícones' },
  mission: { family: 'split', icon: SquareSplitHorizontal, description: 'Dois blocos lado a lado' },
  goals: { ...LIST, description: 'Lista de objetivos com destaque' },
  documents: { ...LIST, description: 'Lista de documentos para download' },
  security: { ...LIST, description: 'Lista de práticas de segurança' },
  units: { ...LIST, description: 'Lista de unidades a partir das filiais' },
  video: { family: 'media', icon: Play, description: 'Vídeo em destaque com título' },
  map: { family: 'media', icon: MapPin, description: 'Mapa com endereço da unidade' },
  form: { family: 'cta', icon: ClipboardList, description: 'Formulário de contato' },
  conclusion: { family: 'text', icon: AlignLeft, description: 'Bloco de texto de encerramento' },
  prose: { family: 'text', icon: AlignLeft, description: 'Título e texto em duas colunas' },
  list: { ...LIST, description: 'Itens com título, texto e link (cards, linhas ou etiquetas)' },
  steps: { ...CARDS, description: 'Etapas numeradas' },
  plans: { ...CARDS, description: 'Planos com preço e botão' },
  quotes: { ...CARDS, description: 'Depoimentos de clientes' },
  faq: { ...LIST, description: 'Perguntas frequentes em acordeão' },
  logos: { family: 'media', icon: LayoutGrid, description: 'Logos de clientes' },
  cases: { ...CARDS, description: 'Cases vinculados ao serviço' },
  'cases-list': { ...CARDS, description: 'Todos os cases, com filtro por serviço' },
  contact: { family: 'cta', icon: ClipboardList, description: 'Formulário de contato' },
  gallery: { family: 'media', icon: LayoutGrid, description: 'Prints com legenda e ampliação ao clicar' },
  results: { ...LIST, description: 'Resultados com valor e descrição' },
};

const FALLBACK: PreviewMeta = { family: 'text', icon: AlignLeft, description: 'Bloco de conteúdo' };

function metaOf(preview: string): PreviewMeta {
  return META[preview] ?? FALLBACK;
}

function normalize(value: string): string {
  return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
}

const WIDTH = 340;
const GUTTER = 12;

export function SectionPicker({
  items,
  anchorRef,
  onPick,
  onClose,
}: {
  items: LibraryItem[];
  anchorRef: RefObject<HTMLElement | null>;
  onPick: (item: LibraryItem) => void;
  onClose: () => void;
}) {
  const panelRef = useRef<HTMLDivElement>(null);
  const searchRef = useRef<HTMLInputElement>(null);
  const [query, setQuery] = useState('');
  const [position, setPosition] = useState<{ top: number; left: number } | null>(null);

  const groups = useMemo(() => {
    const needle = normalize(query.trim());
    const buckets = new Map<Family, LibraryItem[]>();

    for (const item of items) {
      const meta = metaOf(item.preview);
      const haystack = normalize(`${item.label} ${meta.description} ${item.origin} ${FAMILIES[meta.family].label}`);
      if (needle && !haystack.includes(needle)) {
        continue;
      }
      const bucket = buckets.get(meta.family) ?? [];
      bucket.push(item);
      buckets.set(meta.family, bucket);
    }

    return [...buckets.entries()]
      .sort((a, b) => FAMILIES[a[0]].order - FAMILIES[b[0]].order)
      .map(([family, list]) => ({ family, label: FAMILIES[family].label, items: list }));
  }, [items, query]);

  useLayoutEffect(() => {
    const anchor = anchorRef.current;
    const panel = panelRef.current;
    if (!anchor || !panel) {
      return;
    }
    const rect = anchor.getBoundingClientRect();
    const height = panel.offsetHeight;
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;

    let left = rect.right + 10;
    if (left + WIDTH + GUTTER > viewportWidth) {
      left = Math.max(GUTTER, viewportWidth - WIDTH - GUTTER);
    }

    const top = Math.max(GUTTER, Math.min(rect.bottom - height, viewportHeight - height - GUTTER));
    setPosition({ top, left });
  }, [anchorRef, groups.length]);

  useEffect(() => {
    searchRef.current?.focus();

    function onPointerDown(event: PointerEvent) {
      const target = event.target as Node;
      if (
        panelRef.current?.contains(target)
        || anchorRef.current?.contains(target)
        || (target instanceof Element && target.closest('.tour-root'))
      ) {
        return;
      }
      onClose();
    }

    function onKeyDown(event: globalThis.KeyboardEvent) {
      if (event.key === 'Escape') {
        event.preventDefault();
        onClose();
        anchorRef.current?.focus();
      }
    }

    document.addEventListener('pointerdown', onPointerDown);
    document.addEventListener('keydown', onKeyDown);
    window.addEventListener('resize', onClose);

    return () => {
      document.removeEventListener('pointerdown', onPointerDown);
      document.removeEventListener('keydown', onKeyDown);
      window.removeEventListener('resize', onClose);
    };
  }, [anchorRef, onClose]);

  function moveFocus(event: KeyboardEvent<HTMLDivElement>) {
    if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') {
      return;
    }
    const panel = panelRef.current;
    if (!panel) {
      return;
    }
    const focusables = [searchRef.current, ...panel.querySelectorAll<HTMLButtonElement>('.section-picker-item')]
      .filter((node): node is HTMLInputElement | HTMLButtonElement => node !== null);
    const index = focusables.indexOf(document.activeElement as HTMLInputElement | HTMLButtonElement);
    const step = event.key === 'ArrowDown' ? 1 : -1;
    const next = focusables[Math.min(focusables.length - 1, Math.max(0, index + step))];
    if (next) {
      event.preventDefault();
      next.focus();
    }
  }

  const total = groups.reduce((sum, group) => sum + group.items.length, 0);

  return createPortal(
    <div
      ref={panelRef}
      className="section-picker"
      role="dialog"
      aria-label="Adicionar seção"
      data-tour="editor-library"
      style={position ? { top: position.top, left: position.left, width: WIDTH } : { top: -9999, left: -9999, width: WIDTH }}
      onKeyDown={moveFocus}
    >
      <div className="section-picker-search">
        <Search size={14} />
        <input
          ref={searchRef}
          type="search"
          placeholder="Buscar seção"
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          aria-label="Buscar seção"
        />
      </div>
      <div className="section-picker-body">
        {total === 0 ? (
          <p className="section-picker-empty">Nenhuma seção encontrada.</p>
        ) : (
          groups.map((group) => (
            <div key={group.family} className="section-picker-group">
              <span className="section-picker-group-label">{group.label}</span>
              {group.items.map((item) => {
                const meta = metaOf(item.preview);
                const Icon = meta.icon;

                return (
                  <button
                    key={item.type}
                    type="button"
                    className="section-picker-item"
                    onClick={() => onPick(item)}
                  >
                    <span className="section-picker-icon" aria-hidden="true">
                      <Icon size={16} strokeWidth={1.75} />
                    </span>
                    <span className="section-picker-copy">
                      <strong>
                        {item.label}
                        <small>{item.origin}</small>
                      </strong>
                      <em>{meta.description}</em>
                    </span>
                    <span className="section-picker-plus" aria-hidden="true">
                      <Plus size={14} />
                    </span>
                  </button>
                );
              })}
            </div>
          ))
        )}
      </div>
    </div>,
    document.body,
  );
}
