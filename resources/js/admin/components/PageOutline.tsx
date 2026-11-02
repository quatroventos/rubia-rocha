import { useEffect, useRef, useState } from 'react';
import type { Section } from './SchemaForm';
import { SortableList } from './SortableList';
import { type LibraryItem, SectionPicker } from './SectionPicker';
import { useTour } from '../tour/TourProvider';
import { GripVertical, Plus, Trash2 } from 'lucide-react';

type Layout =
  | 'hero'
  | 'cards'
  | 'video'
  | 'split'
  | 'band'
  | 'cta'
  | 'pair'
  | 'form'
  | 'map'
  | 'list'
  | 'text';

const LAYOUTS: Record<string, Layout> = {
  hero: 'hero',
  pillars: 'cards',
  cards: 'cards',
  values: 'cards',
  specialties: 'cards',
  principles: 'cards',
  process: 'cards',
  opportunity: 'split',
  solutions: 'cards',
  video: 'video',
  strip: 'band',
  management: 'split',
  excellence: 'split',
  coverage: 'split',
  culture: 'split',
  purpose: 'split',
  ethics: 'split',
  technology: 'split',
  channels: 'split',
  cta: 'cta',
  mission: 'pair',
  form: 'form',
  map: 'map',
  goals: 'list',
  documents: 'list',
  security: 'list',
  units: 'list',
  conclusion: 'text',
  prose: 'text',
  list: 'list',
  steps: 'cards',
  plans: 'cards',
  quotes: 'cards',
  faq: 'list',
  logos: 'cards',
  cases: 'pair',
  'cases-list': 'cards',
  contact: 'form',
  gallery: 'cards',
  results: 'list',
};

const HINTS: Record<Layout, string> = {
  hero: 'Banner',
  cards: 'Cards',
  video: 'Mídia',
  split: 'Texto e imagem',
  band: 'Faixa',
  cta: 'Chamada',
  pair: 'Dois blocos',
  form: 'Formulário',
  map: 'Mapa',
  list: 'Lista',
  text: 'Texto',
};

function layoutOf(preview?: string): Layout {
  return LAYOUTS[preview ?? ''] ?? 'text';
}

export function sectionHint(preview?: string): string {
  return HINTS[layoutOf(preview)];
}

function weightOf(layout: Layout): number {
  switch (layout) {
    case 'hero':
      return 5;
    case 'video':
    case 'map':
      return 4;
    case 'cards':
    case 'split':
    case 'form':
    case 'list':
      return 3;
    case 'pair':
      return 2.5;
    case 'cta':
    case 'text':
      return 2;
    case 'band':
      return 1.2;
    default:
      return 2;
  }
}

export function WireBlock({ kind, active }: { kind?: string; active: boolean }) {
  const layout = layoutOf(kind);

  return (
    <span
      className={`wf wf-${layout}${active ? ' is-on' : ''}`}
      style={{ flex: `${weightOf(layout)} 1 0` }}
    >
      {layout === 'hero' ? (
        <>
          <span className="wf-col">
            <em /><em /><i />
          </span>
          <s />
        </>
      ) : null}
      {layout === 'cards' ? (
        <>
          <em className="wf-title" />
          <span className="wf-row">
            <b /><b /><b />
          </span>
        </>
      ) : null}
      {layout === 'video' ? <s className="wf-play" /> : null}
      {layout === 'split' ? (
        <>
          <span className="wf-col">
            <em /><em />
          </span>
          <s />
        </>
      ) : null}
      {layout === 'band' ? <s /> : null}
      {layout === 'cta' ? (
        <>
          <em /><i />
        </>
      ) : null}
      {layout === 'pair' ? (
        <span className="wf-row wf-row-2">
          <b /><b />
        </span>
      ) : null}
      {layout === 'form' ? (
        <>
          <span className="wf-col">
            <em /><em /><em />
          </span>
          <span className="wf-col">
            <b /><b /><i />
          </span>
        </>
      ) : null}
      {layout === 'map' ? <s className="wf-map" /> : null}
      {layout === 'list' ? (
        <span className="wf-col">
          <em /><b /><b />
        </span>
      ) : null}
      {layout === 'text' ? (
        <span className="wf-col">
          <em /><em />
        </span>
      ) : null}
    </span>
  );
}

export type OutlineSection = Section & { id: string; type: string };

const STACK_WINDOW = 9;

function stackWindow(total: number, active: number): [number, number] {
  if (total <= STACK_WINDOW) {
    return [0, total];
  }
  let start = Math.max(0, active - Math.floor(STACK_WINDOW / 2));
  const end = Math.min(total, start + STACK_WINDOW);
  start = Math.max(0, end - STACK_WINDOW);
  return [start, end];
}

export function PageOutline({
  sections,
  active,
  leavingId = null,
  onSelect,
  onReorder,
  onRemove,
  library,
  onAdd,
}: {
  sections: OutlineSection[];
  active: number;
  leavingId?: string | null;
  onSelect: (index: number) => void;
  onReorder: (from: number, to: number) => void;
  onRemove: (index: number) => void;
  library: LibraryItem[];
  onAdd: (item: LibraryItem) => void;
}) {
  const { registerActions } = useTour();
  const addRef = useRef<HTMLButtonElement>(null);
  const wrapRef = useRef<HTMLDivElement>(null);
  const knownIds = useRef<Set<string> | null>(null);
  const [pickerOpen, setPickerOpen] = useState(false);
  const [enteringId, setEnteringId] = useState<string | null>(null);

  useEffect(() => registerActions({
    'page-editor.openLibrary': () => setPickerOpen(true),
    'page-editor.closeLibrary': () => setPickerOpen(false),
  }), [registerActions]);

  useEffect(() => {
    const ids = new Set(sections.map((item) => item.id));
    const previous = knownIds.current;
    knownIds.current = ids;

    if (!previous || ids.size !== previous.size + 1) {
      return;
    }

    const fresh = sections.filter((item) => !previous.has(item.id));
    if (fresh.length !== 1) {
      return;
    }

    const id = fresh[0].id;
    setEnteringId(id);

    const frame = window.requestAnimationFrame(() => {
      wrapRef.current
        ?.querySelector(`[data-section-id="${id}"]`)
        ?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
    const timer = window.setTimeout(() => setEnteringId(null), 1400);

    return () => {
      window.cancelAnimationFrame(frame);
      window.clearTimeout(timer);
    };
  }, [sections]);

  return (
    <div className="section-map-wrap" ref={wrapRef}>
      <SortableList
        items={sections}
        onReorder={(from, to) => onReorder(from, to)}
        renderItem={(section, index, handle) => {
          const [start, end] = stackWindow(sections.length, index);

          return (
          <div
            className={[
              'section-map-item',
              index === active ? 'is-active' : '',
              section.id === enteringId ? 'is-entering' : '',
              section.id === leavingId ? 'is-leaving' : '',
            ].filter(Boolean).join(' ')}
            data-section-id={section.id}
          >
            <button type="button" className="section-map-drag" aria-label="Arrastar" {...handle}>
              <GripVertical size={14} />
            </button>
            <button
              type="button"
              className="section-map-main"
              onClick={() => onSelect(index)}
            >
              <span className="section-map-page" aria-hidden="true">
                <b className="section-map-chrome" />
                <span className="section-map-stack">
                  {sections.slice(start, end).map((item, offset) => (
                    <WireBlock
                      key={item.id}
                      kind={item.preview}
                      active={start + offset === index}
                    />
                  ))}
                </span>
              </span>
              <span className="section-map-copy">
                <strong>{section.label}</strong>
                <em>{sectionHint(section.preview)}</em>
              </span>
            </button>
            <button
              type="button"
              className="icon-btn section-map-remove"
              aria-label={`Remover ${section.label}`}
              onClick={() => onRemove(index)}
            >
              <Trash2 size={13} />
            </button>
          </div>
          );
        }}
      />
      <button
        ref={addRef}
        type="button"
        className={`btn btn-secondary section-map-add${pickerOpen ? ' is-open' : ''}`}
        data-tour="editor-add-section"
        aria-expanded={pickerOpen}
        aria-haspopup="dialog"
        onClick={() => setPickerOpen((open) => !open)}
      >
        <Plus size={14} />
        Adicionar seção
      </button>
      {pickerOpen ? (
        <SectionPicker
          items={library}
          anchorRef={addRef}
          onClose={() => setPickerOpen(false)}
          onPick={(item) => {
            onAdd(item);
            setPickerOpen(false);
          }}
        />
      ) : null}
    </div>
  );
}
