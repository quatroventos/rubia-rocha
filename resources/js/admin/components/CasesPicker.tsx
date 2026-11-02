import { type KeyboardEvent, type PointerEvent, useEffect, useId, useMemo, useRef, useState } from 'react';
import { GripVertical, Search, X } from 'lucide-react';
import { api } from '../api';
import { SortableList } from './SortableList';

type CaseOption = {
  id: number;
  slug: string;
  client: string;
  title: string;
  status: string;
  services: string[];
  cover_path?: string | null;
};

type Picked = { id: string; slug: string; option: CaseOption | null };

let caseOptions: Promise<CaseOption[]> | null = null;

function loadCaseOptions(): Promise<CaseOption[]> {
  caseOptions ??= api<{ data: CaseOption[] }>('/cases')
    .then((payload) => payload.data)
    .catch(() => {
      caseOptions = null;
      return [];
    });
  return caseOptions;
}

function normalize(value: string): string {
  return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
}

function CaseThumb({ option }: { option: CaseOption | null }) {
  return option?.cover_path
    ? <img className="case-pick-thumb" src={option.cover_path} alt="" loading="lazy" />
    : <span className="case-pick-thumb is-empty" />;
}

function DraftBadge({ option }: { option: CaseOption }) {
  if (option.status === 'published') {
    return null;
  }

  return <span className="case-pick-draft" title="Não aparece no site até ser publicado">Rascunho</span>;
}

export function CasesPicker({
  label,
  value,
  max = 2,
  all = false,
  pageSlug,
  hint,
  onChange,
}: {
  label: string;
  value: unknown;
  max?: number;
  all?: boolean;
  pageSlug?: string;
  hint?: string;
  onChange: (value: string[]) => void;
}) {
  const [cases, setCases] = useState<CaseOption[] | null>(null);
  const [query, setQuery] = useState('');
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(0);
  const inputRef = useRef<HTMLInputElement>(null);
  const listId = useId();
  const labelId = useId();

  const selected = useMemo(
    () => (Array.isArray(value) ? value.filter((item): item is string => typeof item === 'string') : []),
    [value],
  );
  const single = max === 1;
  const full = !single && selected.length >= max;

  useEffect(() => {
    void loadCaseOptions().then(setCases);
  }, []);

  const linked = useMemo(
    () => (cases ?? []).filter((item) => all || !pageSlug || item.services.includes(pageSlug)),
    [cases, all, pageSlug],
  );

  const picked: Picked[] = selected.map((slug) => ({
    id: slug,
    slug,
    option: (cases ?? []).find((item) => item.slug === slug) ?? null,
  }));

  const options = useMemo(() => {
    const term = normalize(query.trim());
    return linked
      .filter((item) => !selected.includes(item.slug))
      .filter((item) => term === '' || normalize(`${item.client} ${item.title}`).includes(term))
      .sort((a, b) => Number(a.status !== 'published') - Number(b.status !== 'published'));
  }, [linked, selected, query]);

  useEffect(() => {
    setActive(0);
  }, [query, open]);

  function add(slug: string) {
    if (single) {
      onChange([slug]);
      setOpen(false);
      setQuery('');
      inputRef.current?.blur();
      return;
    }
    if (full) {
      return;
    }
    onChange([...selected, slug]);
    setQuery('');
    if (selected.length + 1 >= max) {
      setOpen(false);
    }
  }

  function remove(slug: string) {
    onChange(selected.filter((item) => item !== slug));
  }

  function move(from: number, to: number) {
    if (to < 0 || to >= selected.length || to === from) {
      return;
    }
    const next = [...selected];
    const [item] = next.splice(from, 1);
    next.splice(to, 0, item);
    onChange(next);
  }

  function onInputKeyDown(event: KeyboardEvent<HTMLInputElement>) {
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      setOpen(true);
      setActive((current) => Math.min(current + 1, Math.max(options.length - 1, 0)));
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      setActive((current) => Math.max(current - 1, 0));
    } else if (event.key === 'Enter') {
      if (open && options[active]) {
        event.preventDefault();
        add(options[active].slug);
      }
    } else if (event.key === 'Escape') {
      if (open) {
        event.preventDefault();
        setOpen(false);
      }
    }
  }

  function row(item: Picked, index: number, handle?: { onPointerDown: (event: PointerEvent) => void }) {
    const name = item.option ? item.option.client : item.slug;

    return (
      <div className={`case-pick${item.option ? '' : ' is-missing'}`}>
        {handle ? (
          <button
            type="button"
            className="case-pick-handle"
            aria-label={`Reordenar ${name}. Use as setas para mover.`}
            onPointerDown={handle.onPointerDown}
            onKeyDown={(event) => {
              if (event.key === 'ArrowUp') {
                event.preventDefault();
                move(index, index - 1);
              } else if (event.key === 'ArrowDown') {
                event.preventDefault();
                move(index, index + 1);
              }
            }}
          >
            <GripVertical size={14} />
          </button>
        ) : null}
        {handle ? <span className="case-pick-index">{index + 1}</span> : null}
        <CaseThumb option={item.option} />
        <span className="case-pick-text">
          {item.option ? (
            <>
              <strong>{item.option.client}</strong>
              <span>{item.option.title}</span>
            </>
          ) : (
            <>
              <strong>Case não encontrado</strong>
              <span>{item.slug} foi excluído ou mudou de endereço.</span>
            </>
          )}
        </span>
        {item.option ? <DraftBadge option={item.option} /> : null}
        <button type="button" className="case-pick-remove" aria-label={`Remover ${name}`} onClick={() => remove(item.slug)}>
          <X size={14} />
        </button>
      </div>
    );
  }

  const placeholder = full
    ? `Limite de ${max} cases atingido`
    : single && selected.length > 0
      ? 'Trocar case...'
      : 'Buscar case por cliente ou título...';

  return (
    <div className="field case-picker">
      <div className="case-picker-head">
        <span className="field-label" id={labelId}>{label}</span>
        {!single ? <span className={`case-picker-count${full ? ' is-full' : ''}`}>{selected.length} de {max}</span> : null}
      </div>

      {cases === null ? (
        <div className="case-picker-loading" aria-hidden="true"><span /><span /></div>
      ) : null}

      {cases !== null && picked.length > 0 ? (
        single ? row(picked[0], 0) : (
          <SortableList
            items={picked}
            onReorder={(from, to) => move(from, to)}
            renderItem={(item, index, handle) => row(item, index, handle)}
          />
        )
      ) : null}

      {cases !== null && linked.length === 0 ? (
        <p className="case-picker-empty">Nenhum case vinculado a esta página. Vincule em Cases, no quadro Serviços.</p>
      ) : null}

      {cases !== null && linked.length > 0 ? (
        <div className={`case-picker-combo${open ? ' is-open' : ''}`}>
          <div className="case-picker-search">
            <Search size={14} aria-hidden="true" />
            <input
              ref={inputRef}
              role="combobox"
              aria-labelledby={labelId}
              aria-expanded={open}
              aria-controls={listId}
              aria-autocomplete="list"
              aria-activedescendant={open && options[active] ? `${listId}-${options[active].slug}` : undefined}
              value={query}
              disabled={full}
              placeholder={placeholder}
              onFocus={() => setOpen(true)}
              onBlur={() => setOpen(false)}
              onChange={(event) => {
                setQuery(event.target.value);
                setOpen(true);
              }}
              onKeyDown={onInputKeyDown}
            />
          </div>
          {open ? (
            <ul className="case-picker-options" role="listbox" id={listId} aria-labelledby={labelId}>
              {options.length === 0 ? (
                <li className="case-picker-none" role="presentation">
                  {query.trim() ? `Nenhum case encontrado para "${query.trim()}".` : 'Todos os cases já estão selecionados.'}
                </li>
              ) : options.map((item, index) => (
                <li
                  key={item.slug}
                  id={`${listId}-${item.slug}`}
                  role="option"
                  aria-selected={index === active}
                  className={index === active ? 'is-active' : undefined}
                  onMouseDown={(event) => event.preventDefault()}
                  onMouseEnter={() => setActive(index)}
                  onClick={() => add(item.slug)}
                >
                  <CaseThumb option={item} />
                  <span className="case-pick-text">
                    <strong>{item.client}</strong>
                    <span>{item.title}</span>
                  </span>
                  <DraftBadge option={item} />
                </li>
              ))}
            </ul>
          ) : null}
        </div>
      ) : null}

      <p className="hint">{hint ?? 'Sem seleção, entram automaticamente os cases publicados vinculados a esta página.'}</p>
    </div>
  );
}
