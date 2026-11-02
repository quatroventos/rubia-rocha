import { createContext, useContext } from 'react';
import type { CaseCard } from './types';
import { Arrow, resetTilt, trackTilt } from './ui';

/** Cases publicados no CMS, por slug do original. Null enquanto o payload do site não chegou. */
export const CmsCases = createContext<Record<string, CaseCard> | null>(null);

/**
 * Aplica os dados do CMS sobre o case fixo da página estática.
 * Case com link interno que não está publicado sai da lista, porque a página dele daria 404.
 */
export function useCmsShowcase(items: ShowcaseCase[]): ShowcaseCase[] {
  const cms = useContext(CmsCases);

  if (cms === null) {
    return items;
  }

  return items.flatMap((item) => {
    if (item.placeholder) {
      return [item];
    }

    const card = cms[item.slug];
    if (!card) {
      return item.external ? [item] : [];
    }

    const ratio = card.cover_ratio === '16x9' || card.cover_ratio === '16x10' ? card.cover_ratio : undefined;

    return [{
      ...item,
      name: card.client || item.name,
      cover: card.cover?.src || item.cover,
      ratio: card.cover?.src ? ratio : item.ratio,
      tags: card.tags?.length ? card.tags : item.tags,
      text: card.summary || item.text,
      href: item.external ? item.href : card.path,
    }];
  });
}

export type ShowcaseCase = {
  slug: string;
  name: string;
  cover: string;
  ratio?: 'og' | '16x9' | '16x10';
  tags: string[];
  text: string;
  href: string;
  external?: boolean;
  cta: string;
  placeholder?: boolean;
};

function linkProps(item: ShowcaseCase) {
  return item.external ? { href: item.href, target: '_blank', rel: 'noreferrer' } : { href: item.href };
}

function CasePlaceholder({ item }: { item: ShowcaseCase }) {
  return (
    <article className="case-card is-wide is-placeholder">
      <div className="case-card-cover" aria-hidden="true">
        <span>{item.name}</span>
      </div>
      <div className="case-card-body">
        <h3>{item.name}</h3>
        {item.tags.length ? <p className="case-meta">{item.tags.map((tag) => <span key={tag}>{tag}</span>)}</p> : null}
        <p className="case-desc">{item.text}</p>
      </div>
    </article>
  );
}

export function CaseFeature({ item }: { item: ShowcaseCase }) {
  if (item.placeholder) {
    return <CasePlaceholder item={item} />;
  }

  const ratio = item.ratio && item.ratio !== 'og' ? ` is-cover-${item.ratio}` : '';

  return (
    <article className={`case-card is-wide is-tilt${ratio}`} onPointerMove={trackTilt} onPointerLeave={resetTilt}>
      <a className="case-card-cover" {...linkProps(item)} tabIndex={-1} aria-hidden="true">
        <img src={item.cover} alt="" loading="lazy" />
      </a>
      <div className="case-card-body">
        <h3>{item.name}</h3>
        {item.tags.length ? <p className="case-meta">{item.tags.map((tag) => <span key={tag}>{tag}</span>)}</p> : null}
        <p className="case-desc">{item.text}</p>
        <a className="case-go" {...linkProps(item)} aria-label={`${item.cta}: ${item.name}`}>{item.cta}<Arrow /></a>
      </div>
    </article>
  );
}

export function CaseFeatures({ items }: { items: ShowcaseCase[] }) {
  const resolved = useCmsShowcase(items);

  return (
    <div className="cases-grid is-single is-open">
      {resolved.map((item) => <CaseFeature key={item.slug} item={item} />)}
    </div>
  );
}
