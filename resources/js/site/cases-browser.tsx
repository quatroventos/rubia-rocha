// Lista única de thumbs de cases com busca por texto (sem acento e sem caixa) e filtro por categoria; filtrar anima com View Transitions.
import { useId, useMemo, useState, type ReactNode } from 'react';
import { flushSync } from 'react-dom';
import { t } from './i18n';
import { Arrow, resetTilt, trackTilt } from './ui';

export type BrowserCase = { name: string; cover: string; href: string; meta: string[]; category: string };

export type BrowserCategory = { id: string; label: string };

const normalize = (value: string) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();

const transitionName = (href: string) => `cv-${href.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '')}`;

export function CasesBrowser({ head, items, categories }: { head: ReactNode; items: BrowserCase[]; categories: BrowserCategory[] }) {
  const categoryLabel = (id: string) => categories.find((item) => item.id === id)?.label ?? '';
  const searchId = useId();
  const [query, setQueryState] = useState('');
  const [category, setCategoryState] = useState('');
  const term = normalize(query);

  const morph = (update: () => void) => {
    const doc = document as Document & { startViewTransition?: (callback: () => void) => unknown };
    if (!doc.startViewTransition || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      update();
      return;
    }
    doc.startViewTransition(() => flushSync(update));
  };
  const setQuery = (value: string) => morph(() => setQueryState(value));
  const setCategory = (value: string) => morph(() => setCategoryState(value));

  const matching = useMemo(
    () => items.filter((item) => !term || normalize([item.name, ...item.meta, categoryLabel(item.category)].join(' ')).includes(term)),
    [term, items, categories],
  );
  const visible = category ? matching.filter((item) => item.category === category) : matching;
  const countOf = (id: string) => (id ? matching.filter((item) => item.category === id).length : matching.length);

  const clear = () => {
    setQuery('');
    setCategory('');
  };

  return (
    <>
      <div className="cases-band">
        {head}
        <div className="cases-band-tools">
          <div className="cases-filter" role="group" aria-label={t('cases.category')}>
            {[{ id: '', label: t('cases.all') }, ...categories].map((item) => (
              <button key={item.id || 'all'} type="button" data-category={item.id} aria-pressed={category === item.id} onClick={() => setCategory(item.id)}>
                {item.label}
                <span className="cases-filter-n">{countOf(item.id)}</span>
              </button>
            ))}
          </div>
          <div className="cases-search">
            <label className="sr-only" htmlFor={searchId}>{t('cases.search')}</label>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.5 17a6.5 6.5 0 1 0 0-13 6.5 6.5 0 0 0 0 13zM15.5 15.5L20 20" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" /></svg>
            <input id={searchId} type="search" value={query} onChange={(event) => setQuery(event.target.value)} placeholder={t('cases.search_placeholder')} autoComplete="off" />
          </div>
        </div>
      </div>
      <p className="sr-only" aria-live="polite">{visible.length === 1 ? t('cases.count_one') : t('cases.count', { n: visible.length })}</p>
      <div className="cases-grid is-3 is-open is-cover-16x10">
        {items.map((item) => (
          <CaseThumb key={item.href} item={item} label={categoryLabel(item.category)} hidden={!visible.some((match) => match.href === item.href)} />
        ))}
      </div>
      <div className="cases-empty" hidden={visible.length > 0}>
        <p>{query.trim() ? t('cases.none_for', { query: query.trim() }) : t('cases.none')}.</p>
        <button type="button" className="pill line" data-cases-clear onClick={clear}>{t('cases.clear')}</button>
      </div>
    </>
  );
}

function CaseThumb({ item, label, hidden = false }: { item: BrowserCase; label: string; hidden?: boolean }) {
  return (
    <article className="case-card is-tilt" hidden={hidden} data-category={item.category} data-search={normalize([item.name, ...item.meta, label].join(' '))} style={{ viewTransitionName: transitionName(item.href) }} onPointerMove={trackTilt} onPointerLeave={resetTilt}>
      <a className="case-card-cover" href={item.href} tabIndex={-1} aria-hidden="true">
        <img src={item.cover} alt="" loading="lazy" />
      </a>
      <div className="case-card-body">
        <h3>{item.name}</h3>
        <p className="case-meta">{item.meta.map((tag) => <span key={tag}>{tag}</span>)}</p>
        <a className="case-go" href={item.href} aria-label={t('cases.view_named', { name: item.name })}>{t('cases.view')}<Arrow /></a>
      </div>
    </article>
  );
}
