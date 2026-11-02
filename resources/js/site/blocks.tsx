// Conteúdo das seções do CMS que seguem os padrões das páginas de serviço (faixa de aplicações, grade de exemplos,
// painel, planos, cases...). Cada bloco devolve o miolo do .wrap; a <section> e o tom ficam com o Shell de sections.tsx.
import type { CSSProperties } from 'react';
import { HistoryTimeline, MethodOrbit } from './about';
import { AdhocBoard, type AdhocItem } from './adhoc';
import { CaseFeatures, type ShowcaseCase } from './case-showcase';
import { CasesBrowser } from './cases-browser';
import { GLYPHS } from './glyphs';
import { t, type DictKey } from './i18n';
import { BillingSwitch, PlanBase, PlanCard, PlanTabs, type Plan } from './plans';
import type { CaseCard } from './types';
import { Arrow, Cta, LinkTo, Marked, Rich, plain, resetTilt, revealOnView, rows, strings, text, trackTilt, type Data } from './ui';

export function Head({ title, intro, href }: { title: string; intro?: string; href?: string }) {
  if (!title && !intro) {
    return null;
  }

  return (
    <header className={intro ? 'sec-head' : 'sec-head is-solo'}>
      {title ? (
        <h2 className="h2">
          {href ? <a href={href}><Marked value={title} /><span className="head-go"><Arrow /></span></a> : <Marked value={title} />}
        </h2>
      ) : <span />}
      {intro ? <Rich className="intro" value={intro} /> : null}
    </header>
  );
}

function SectionHead({ data }: { data: Data }) {
  return <Head title={text(data, 'title')} intro={text(data, 'intro')} />;
}

function PanelCta({ data }: { data: Data }) {
  const href = text(data, 'cta_href');
  const label = text(data, 'cta_label');

  if (!href || !label) {
    return null;
  }

  return (
    <div className="actions">
      <Cta href={href} label={label} variant={data.cta_line ? 'line' : 'primary'} />
    </div>
  );
}

function cases(data: Data, key: string): CaseCard[] {
  const value = data[key];
  return Array.isArray(value) ? value.filter((item): item is CaseCard => Boolean(item) && typeof item === 'object' && 'slug' in item) : [];
}

export function toShowcase(item: CaseCard, data: Data = {}): ShowcaseCase {
  const ratio = item.cover_ratio === '16x9' || item.cover_ratio === '16x10' ? item.cover_ratio : undefined;
  const external = text(data, 'link_to') === 'url' && Boolean(item.url);

  return {
    slug: item.slug,
    name: item.client,
    cover: item.cover?.src ?? '',
    ratio,
    tags: item.tags ?? [],
    text: item.summary,
    href: external ? item.url ?? '' : item.path,
    external: external || undefined,
    cta: text(data, 'cta_label') || t('cases.view'),
  };
}

export function placeholders(data: Data): ShowcaseCase[] {
  return rows(data, 'placeholders').map((item, index) => ({
    slug: `placeholder-${index}`,
    name: text(item, 'name'),
    cover: '',
    tags: strings(item, 'tags'),
    text: text(item, 'text'),
    href: '',
    cta: '',
    placeholder: true,
  }));
}

export function hasCases(data: Data, ...keys: string[]): boolean {
  return keys.some((key) => cases(data, key).length > 0);
}

export function UsesBlock({ data }: { data: Data }) {
  return (
    <>
      <SectionHead data={data} />
      <ul className="uses-band">
        {rows(data).map((item, index) => (
          <li key={`${text(item, 'title')}-${index}`}>
            <h3 className="h3">{text(item, 'title')}</h3>
            <p>{text(item, 'text')}</p>
          </li>
        ))}
      </ul>
    </>
  );
}

export function ExamplesBlock({ data }: { data: Data }) {
  const moreTitle = text(data, 'more_title');
  const moreHref = text(data, 'more_href');
  const moreLabel = text(data, 'more_label');

  return (
    <>
      <SectionHead data={data} />
      <ul className={data.plain ? 'ex-grid is-plain' : 'ex-grid'}>
        {rows(data).map((item, index) => (
          <li key={`${text(item, 'title')}-${index}`}>
            <h3 className="h3">{text(item, 'title')}</h3>
            <p>{text(item, 'text')}</p>
          </li>
        ))}
        {moreTitle ? (
          <li className="is-more">
            <h3 className="h3">{moreTitle}</h3>
            <p>{text(data, 'more_text')}</p>
            {moreHref && moreLabel ? (
              <LinkTo className="ex-more-link" href={moreHref}>
                {moreLabel}
                <span className="arrow"><Arrow /></span>
              </LinkTo>
            ) : null}
          </li>
        ) : null}
      </ul>
    </>
  );
}

export function GlyphsBlock({ data }: { data: Data }) {
  const items = rows(data);
  const linked = items.some((item) => text(item, 'href'));

  return (
    <>
      <SectionHead data={data} />
      <ul className={linked ? 'ex-grid is-glyphs is-links' : 'ex-grid is-glyphs'} ref={revealOnView}>
        {items.map((item, index) => (
          <li key={`${text(item, 'title')}-${index}`} style={{ '--i': index } as CSSProperties}>
            <svg className="pr-glyph" viewBox="0 0 40 40" aria-hidden="true">{GLYPHS[text(item, 'glyph')] ?? GLYPHS.search}</svg>
            <h3 className="h3">{text(item, 'href') ? <LinkTo href={text(item, 'href')}>{text(item, 'title')}</LinkTo> : text(item, 'title')}</h3>
            <p>{text(item, 'text')}</p>
          </li>
        ))}
      </ul>
    </>
  );
}

export function ChecklistBlock({ data }: { data: Data }) {
  return (
    <>
      <SectionHead data={data} />
      <ul className="checklist">
        {strings(data, 'items').map((item) => (
          <li key={item}>
            <span className="tick"><svg viewBox="0 0 16 16" aria-hidden="true"><path d="M3.5 8.5l3 3 6-7" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" /></svg></span>
            <span>{item}</span>
          </li>
        ))}
      </ul>
    </>
  );
}

const STACK_INSIDE = ['interface', 'aplicacao', 'dados', 'infra'] as const;

const STACK_LABELS: Record<string, [DictKey, DictKey]> = {
  interface: ['stack.interface', 'stack.interface_tech'],
  aplicacao: ['stack.aplicacao', 'stack.aplicacao_tech'],
  dados: ['stack.dados', 'stack.dados_tech'],
  infra: ['stack.infra', 'stack.infra_tech'],
  integracoes: ['stack.integracoes', 'stack.integracoes_tech'],
  outros: ['stack.outros', 'stack.outros_tech'],
};

type StackItem = { title: string; body: string; layer: string };

export function hasStackLayers(data: Data): boolean {
  return rows(data).some((item) => text(item, 'layer') in STACK_LABELS);
}

/**
 * Stack por camadas: uma linha por camada com as tecnologias lado a lado; integrações
 * ficam numa linha tracejada, fora do sistema. Classifica por função, sem afirmar quem chama quem.
 */
export function StackBlock({ data }: { data: Data }) {
  const items: StackItem[] = rows(data).map((item) => ({ title: plain(text(item, 'title')), body: inlineHtml(text(item, 'body')), layer: text(item, 'layer') }));
  const groups = [...STACK_INSIDE, 'integracoes']
    .map((key) => ({ key, items: items.filter((item) => item.layer === key) }))
    .filter((group) => group.items.length > 0);
  const other = items.filter((item) => !(item.layer in STACK_LABELS) || item.layer === 'outros');

  if (other.length) {
    groups.push({ key: 'outros', items: other });
  }

  return (
    <>
      <SectionHead data={data} />
      <dl className="stack-rows">
        {groups.map((group) => (
          <div className={group.key === 'integracoes' ? 'stack-row is-external' : 'stack-row'} key={group.key}>
            <dt>
              <b>{t(STACK_LABELS[group.key][0])}</b>
              <span>{t(STACK_LABELS[group.key][1])}</span>
            </dt>
            <dd>
              <ul className="stack-chips">
                {group.items.map((item, index) => (
                  <li key={`${item.title}-${index}`}>
                    <b>{item.title}</b>
                    {item.body ? <span dangerouslySetInnerHTML={{ __html: item.body }} /> : null}
                  </li>
                ))}
              </ul>
            </dd>
          </div>
        ))}
      </dl>
    </>
  );
}

export function PanelBlock({ data }: { data: Data }) {
  const logo = text(data, 'logo');
  const brand = Boolean(logo) && text(data, 'logo_place') === 'brand';
  const caption = text(data, 'logo_caption');
  const classes = ['prose-panel', text(data, 'variant') === 'light' ? 'is-light' : '', brand ? 'firefly-panel' : '', 'is-reveal'].filter(Boolean).join(' ');

  return (
    <div className={classes} ref={revealOnView}>
      {brand ? (
        <div className="firefly-brand">
          <img src={logo} alt={text(data, 'logo_alt')} loading="lazy" />
          {caption ? <p>{caption}</p> : null}
        </div>
      ) : (
        <h2 className="h2"><Marked value={text(data, 'title')} /></h2>
      )}
      <div className="prose-panel-side">
        {logo && !brand ? <img className="prose-logo" src={logo} alt={text(data, 'logo_alt')} loading="lazy" /> : null}
        <Rich value={text(data, 'body')} />
        {text(data, 'price_value') ? (
          <div className="prose-price">
            {text(data, 'price_from') ? <p className="prose-price-from">{text(data, 'price_from')}</p> : null}
            <p className="prose-price-value">{text(data, 'price_value')}</p>
            {text(data, 'price_term') ? <p className="prose-price-term">{text(data, 'price_term')}</p> : null}
            {text(data, 'price_note') ? <p className="prose-price-note">{text(data, 'price_note')}</p> : null}
          </div>
        ) : null}
        <PanelCta data={data} />
      </div>
    </div>
  );
}

export function FiguresBlock({ data }: { data: Data }) {
  const mini = Boolean(data.mini);

  return (
    <div className="prose-panel is-figures is-reveal" ref={revealOnView}>
      <h2 className={mini ? 'figs has-mini' : 'figs'}>
        {rows(data).map((item, index) => (
          <span className="fig" key={`${text(item, 'value')}-${index}`}>
            <b>{text(item, 'prefix') ? <span className="fig-cur">{text(item, 'prefix')}</span> : null}{text(item, 'value')}</b>
            <small>{text(item, 'caption')}</small>
          </span>
        ))}
        {mini ? (
          <span className="figs-mini" aria-hidden="true">
            <span className="figs-mini-bar"><i /><i /><i /></span>
            <span className="figs-mini-page"><i /><i /><i /><i /><b>Quero</b></span>
            <span className="figs-mini-live">no ar</span>
          </span>
        ) : null}
      </h2>
      <div className="figs-foot">
        {text(data, 'body') ? <p>{text(data, 'body')}</p> : null}
        <PanelCta data={data} />
      </div>
    </div>
  );
}

export function ShowcaseBlock({ data }: { data: Data }) {
  const items = [...cases(data, 'cases').map((item) => toShowcase(item, data)), ...placeholders(data)];

  return (
    <>
      <SectionHead data={data} />
      {items.length ? <CaseFeatures items={items} /> : null}
    </>
  );
}

export function CaseGridBlock({ data }: { data: Data }) {
  const featured = cases(data, 'featured_cases').map((item) => toShowcase(item));
  const grid = cases(data, 'cases');

  return (
    <>
      <SectionHead data={data} />
      {featured.length ? <CaseFeatures items={featured} /> : null}
      {grid.length ? (
        <div className="cases-grid is-3 is-open">
          {grid.map((item) => (
            <article className="case-card is-tilt" key={item.slug} onPointerMove={trackTilt} onPointerLeave={resetTilt}>
              <a className="case-card-cover" href={item.path} tabIndex={-1} aria-hidden="true">
                <img src={item.cover?.src ?? ''} alt="" loading="lazy" />
              </a>
              <div className="case-card-body">
                <h3>{item.client}</h3>
                <p className="case-meta">{(item.tags ?? []).map((tag) => <span key={tag}>{tag}</span>)}</p>
                <a className="case-go" href={item.path} aria-label={t('cases.view_named', { name: item.client })}>{t('cases.view')}<Arrow /></a>
              </div>
            </article>
          ))}
        </div>
      ) : null}
    </>
  );
}

export function CasesBrowserBlock({ data }: { data: Data }) {
  const items = cases(data, 'cases').map((item) => ({
    name: item.client,
    cover: item.cover?.src ?? '',
    href: item.path,
    meta: item.tags ?? [],
    category: item.category ?? '',
  }));
  const categories = rows(data, 'categories').map((item) => ({ id: text(item, 'id'), label: text(item, 'label') })).filter((item) => item.id);

  return <CasesBrowser head={<SectionHead data={data} />} items={items} categories={categories} />;
}

function toPlan(item: Data): Plan & { href: string; altHref: string } {
  return {
    name: text(item, 'name'),
    monthly: Number(text(item, 'monthly')) || 0,
    annual: Number(text(item, 'annual')) || 0,
    featured: Boolean(item.featured),
    from: text(item, 'from') || undefined,
    adds: strings(item, 'adds'),
    href: text(item, 'href'),
    altHref: text(item, 'alt_href'),
  };
}

function inlineHtml(value: string): string {
  const single = value.trim().match(/^<p>([\s\S]*)<\/p>$/);
  return single && !single[1].includes('<p') ? single[1] : value;
}

function PlanSet({ data, category, heading }: { data: Data; category: Data; heading: 'h3' | 'h4' }) {
  const base = strings(category, 'base_items');

  return (
    <>
      <div className="plan-tiers">
        {rows(category, 'plans').map(toPlan).map((plan) => (
          <PlanCard key={plan.name} plan={plan} href={plan.href} altHref={plan.altHref || undefined} heading={heading} />
        ))}
      </div>
      {base.length ? <PlanBase title={text(data, 'base_title')} text={text(data, 'base_text')} items={base} heading={heading} /> : null}
    </>
  );
}

export function PricingBlock({ data }: { data: Data }) {
  const categories = rows(data, 'categories');
  const tabs = categories.length > 1;
  const panelId = text(data, 'panel_id') || 'planos-categoria';
  const billing = Boolean(data.billing);
  const note = text(data, 'note');

  return (
    <>
      <header className="sec-head plan-head">
        <h2 className="h2"><Marked value={text(data, 'title')} /></h2>
        {tabs ? (
          <div className="plan-switches">
            <PlanTabs items={categories.map((item) => ({ id: text(item, 'id'), tab: text(item, 'tab') }))} label={text(data, 'tabs_label')} controls={panelId} />
            {billing ? <BillingSwitch /> : null}
          </div>
        ) : billing ? <BillingSwitch /> : null}
      </header>
      {tabs ? (
        <div id={panelId}>
          {categories.map((category, index) => (
            <div className="plan-category" data-plan-panel={text(category, 'id')} hidden={index > 0} key={text(category, 'id') || index}>
              <div className="plan-category-head">
                <h3>{text(category, 'title')}</h3>
                <p>{text(category, 'text')}</p>
              </div>
              <PlanSet data={data} category={category} heading="h4" />
            </div>
          ))}
        </div>
      ) : categories[0] ? <PlanSet data={data} category={categories[0]} heading="h3" /> : null}
      {note ? <p className="plan-note" dangerouslySetInnerHTML={{ __html: inlineHtml(note) }} /> : null}
    </>
  );
}

function adhocItems(data: Data, key: string): AdhocItem[] {
  return rows(data, key).map((item) => ({ icon: text(item, 'icon'), title: text(item, 'title'), text: text(item, 'text') }));
}

export function AdhocBlock({ data }: { data: Data }) {
  return (
    <>
      <header className="adhoc-head">
        <h2 className="h2"><Marked value={text(data, 'title')} /></h2>
        <Rich className="intro" value={text(data, 'intro')} />
      </header>
      <AdhocBoard columns={[adhocItems(data, 'left'), adhocItems(data, 'right')]} url={text(data, 'screen_url')} log={strings(data, 'log')} status={text(data, 'status')} />
      {text(data, 'cta_href') && text(data, 'cta_label') ? (
        <div className="adhoc-cta">
          <Cta href={text(data, 'cta_href')} label={text(data, 'cta_label')} variant="primary" />
        </div>
      ) : null}
    </>
  );
}

export function HistoryBlock({ data }: { data: Data }) {
  const items = rows(data).map((item) => ({ year: text(item, 'year'), title: text(item, 'title'), text: text(item, 'text') }));

  return (
    <>
      <Head title={text(data, 'title')} />
      {items.length ? <HistoryTimeline items={items} /> : null}
    </>
  );
}

export function MethodBlock({ data }: { data: Data }) {
  const steps = rows(data).map((item) => ({ title: text(item, 'title'), text: text(item, 'text') }));

  return (
    <>
      <SectionHead data={data} />
      <MethodOrbit steps={steps} center={text(data, 'center')} />
    </>
  );
}
