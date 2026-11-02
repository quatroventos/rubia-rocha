import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { AgentLog } from './hero';
import type { CaseCard, CaseDetail, CaseImage } from './types';
import { Arrow, Cta, Rich } from './ui';
import { localePath, t } from './i18n';

function Lightbox({ images, index, onClose, onMove }: { images: CaseImage[]; index: number; onClose: () => void; onMove: (index: number) => void }) {
  const image = images[index];

  useEffect(() => {
    function onKey(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        onClose();
      }
      if (event.key === 'ArrowRight') {
        onMove((index + 1) % images.length);
      }
      if (event.key === 'ArrowLeft') {
        onMove((index - 1 + images.length) % images.length);
      }
    }

    window.addEventListener('keydown', onKey);
    document.body.style.overflow = 'hidden';

    return () => {
      window.removeEventListener('keydown', onKey);
      document.body.style.overflow = '';
    };
  }, [index, images.length, onClose, onMove]);

  if (!image) {
    return null;
  }

  return (
    <div className="case-lightbox" role="dialog" aria-modal="true" aria-label={image.alt || t('cases.print')} onClick={onClose}>
      <figure onClick={(event) => event.stopPropagation()}>
        <img src={image.src} alt={image.alt} />
        {image.caption ? <figcaption>{image.caption}</figcaption> : null}
      </figure>
      {images.length > 1 ? (
        <div className="case-lightbox-nav" onClick={(event) => event.stopPropagation()}>
          <button type="button" onClick={() => onMove((index - 1 + images.length) % images.length)}>{t('cases.prev')}</button>
          <span>{index + 1} / {images.length}</span>
          <button type="button" onClick={() => onMove((index + 1) % images.length)}>{t('cases.next')}</button>
        </div>
      ) : null}
      <button className="case-lightbox-close" type="button" onClick={onClose}>{t('cases.close')}</button>
    </div>
  );
}

export function Gallery({ images, compact = false }: { images: CaseImage[]; compact?: boolean }) {
  const [open, setOpen] = useState<number | null>(null);

  if (images.length === 0) {
    return null;
  }

  return (
    <>
      <div className={compact ? 'case-prints is-compact' : 'case-prints'}>
        {images.map((image, index) => (
          <figure key={`${image.src}-${index}`}>
            <button type="button" onClick={() => setOpen(index)} aria-label={t('cases.zoom', { label: image.alt || t('cases.print_n', { n: index + 1 }) })}>
              <img src={image.src} alt={image.alt} loading="lazy" />
            </button>
            {!compact && image.caption ? <figcaption>{image.caption}</figcaption> : null}
          </figure>
        ))}
      </div>
      {open !== null ? <Lightbox images={images} index={open} onClose={() => setOpen(null)} onMove={setOpen} /> : null}
    </>
  );
}

function Meta({ item }: { item: CaseCard }) {
  if (!item.sector && item.services.length === 0) {
    return null;
  }

  return (
    <p className="case-meta">
      {item.sector ? <span>{item.sector}</span> : null}
      {item.services.map((service) => <span key={service.slug}>{service.label}</span>)}
    </p>
  );
}

function CaseCardView({ item, prints = false }: { item: CaseCard; prints?: boolean }) {
  return (
    <article className="case-card">
      <a className="case-card-cover" href={item.path} aria-label={`${item.client}: ${item.title}`}>
        {item.cover ? <img src={item.cover.src} alt={item.cover.alt} loading="lazy" /> : <span className="case-cover-empty">{item.client}</span>}
      </a>
      <div className="case-card-body">
        <h3><a href={item.path}>{item.title}</a></h3>
        <p className="case-client">{item.client}</p>
        {item.summary ? <p>{item.summary}</p> : null}
        <Meta item={item} />
        {prints ? <Gallery images={item.gallery} compact /> : null}
        <a className="case-link" href={item.path}>{t('cases.view_full')}<Arrow /></a>
      </div>
    </article>
  );
}

function asCases(value: unknown): CaseCard[] {
  return Array.isArray(value) ? (value as CaseCard[]) : [];
}

export function CasesPair({ data }: { data: Record<string, unknown> }) {
  const cases = asCases(data.cases);
  const title = typeof data.title === 'string' && data.title ? data.title : t('cases.title');
  const layout = data.layout === 'destaque' ? 'destaque' : 'par';

  return (
    <div className={`is-${layout}`}>
      <header className="sec-head is-solo">
        <h2 className="h2">{title}</h2>
      </header>
      <div className="cases-grid">
        {cases.map((item) => <CaseCardView key={item.slug} item={item} prints={layout === 'destaque'} />)}
      </div>
    </div>
  );
}

export function CasesList({ data }: { data: Record<string, unknown> }) {
  const cases = asCases(data.cases);
  const [filter, setFilter] = useState('');
  const filters = useMemo(() => {
    const map = new Map<string, string>();
    cases.forEach((item) => item.services.forEach((service) => map.set(service.slug, service.label)));
    return [...map.entries()];
  }, [cases]);
  const featured = cases.filter((item) => item.featured);
  const visible = cases.filter((item) => !filter || item.services.some((service) => service.slug === filter));
  const title = typeof data.title === 'string' ? data.title : '';
  const intro = typeof data.intro === 'string' ? data.intro : '';

  return (
    <>
      {title || intro ? (
        <header className={intro ? 'sec-head' : 'sec-head is-solo'}>
          {title ? <h2 className="h2">{title}</h2> : <span />}
          <Rich className="intro" value={intro} />
        </header>
      ) : null}
      {featured.length > 0 && !filter ? (
        <div className="cases-featured">
          {featured.map((item) => <CaseCardView key={item.slug} item={item} prints />)}
        </div>
      ) : null}
      {data.filter && filters.length > 1 ? (
        <div className="cases-filter" role="group" aria-label={t('cases.filter')}>
          <button type="button" aria-pressed={!filter} onClick={() => setFilter('')}>{t('cases.all')}</button>
          {filters.map(([slug, label]) => (
            <button key={slug} type="button" aria-pressed={filter === slug} onClick={() => setFilter(slug)}>{label}</button>
          ))}
        </div>
      ) : null}
      {cases.length === 0 ? <p className="cases-empty">{t('cases.empty')}</p> : null}
      <div className="cases-grid">
        {visible.filter((item) => filter || !item.featured).map((item) => <CaseCardView key={item.slug} item={item} />)}
      </div>
    </>
  );
}

function Block({ tone = 'light', children }: { tone?: 'light' | 'dark'; children: ReactNode }) {
  return (
    <section className={`section on-${tone}`}>
      <div className="wrap">{children}</div>
    </section>
  );
}

export function CaseResults({ title, items }: { title: string; items: Array<{ value: string; label: string }> }) {
  return (
    <>
      {title ? <header className="sec-head is-solo"><h2 className="h2">{title}</h2></header> : null}
      <dl className="case-results">
        {items.map((result, index) => (
          <div key={`${result.value}-${index}`}>
            {result.value ? <dt>{result.value}</dt> : null}
            {result.label ? <dd>{result.label}</dd> : null}
          </div>
        ))}
      </dl>
    </>
  );
}

export function CasePage({ item, whatsapp, children }: { item: CaseDetail; whatsapp: string | null; children?: ReactNode }) {
  return (
    <article className="case-page">
      <header className={item.cover ? 'hero case-hero has-cover' : 'hero case-hero'}>
        {item.cover ? <img className="case-hero-cover" src={item.cover.src} alt={item.cover.alt} fetchPriority="high" /> : <AgentLog />}
        <div className="wrap">
          <nav className="breadcrumb" aria-label={t('cases.breadcrumb')}>
            <a href={localePath('/')}>{t('cases.home')}</a> <span aria-hidden="true">/</span> <a href={localePath('/cases')}>{t('cases.title')}</a> <span aria-hidden="true">/</span> <span>{item.client}</span>
          </nav>
          <h1 className="display is-inner">{item.title}</h1>
          <p className="case-client">{item.client}{item.year ? `, ${item.year}` : ''}</p>
          {item.summary ? <p className="lead">{item.summary}</p> : null}
          <p className="case-meta">
            {item.sector ? <span>{item.sector}</span> : null}
            {item.services.map((service) => <a key={service.slug} href={service.path}>{service.label}</a>)}
          </p>
          {item.url ? <a className="case-link" href={item.url} target="_blank" rel="noreferrer">{t('cases.published')}<Arrow /></a> : null}
        </div>
      </header>

      {children}

      {item.next ? (
        <Block>
          <header className="sec-head is-solo"><h2 className="h2">{t('cases.next_case')}</h2></header>
          <div className="cases-grid is-single">
            <CaseCardView item={item.next} />
          </div>
        </Block>
      ) : null}

      <section className="section on-dark cta-block">
        <div className="wrap">
          <h2>{t('cta.title')} <em className="mark">{t('cta.title_mark')}</em></h2>
          <div className="cta-side">
            {whatsapp ? <div className="actions"><Cta href={whatsapp} label={t('cta.button')} variant="primary" /></div> : null}
          </div>
        </div>
      </section>
    </article>
  );
}
