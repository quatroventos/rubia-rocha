import { useEffect, useRef, useState, type FormEvent, type ReactNode } from 'react';
import { CaseResults, CasesList, CasesPair, Gallery } from './cases';
import { Hero } from './hero';
import { LogoWall } from './logos';
import type { FormField, Section, SiteForm } from './types';
import { AdhocBlock, CaseGridBlock, CasesBrowserBlock, ChecklistBlock, ExamplesBlock, FiguresBlock, GlyphsBlock, Head, HistoryBlock, MethodBlock, PanelBlock, PricingBlock, ShowcaseBlock, StackBlock, UsesBlock, hasStackLayers, hasCases, placeholders } from './blocks';
import { Arrow, Cta, LinkTo, Marked, Rich, pad, plain, rows, strings, text, type Data } from './ui';
import { t } from './i18n';
import { PhoneInput, isPhoneComplete, phoneToString, type PhoneValue } from './phone';

type Tone = 'dark' | 'light' | 'white';

const TONES: Record<string, Tone> = {
  hero: 'dark',
  steps: 'dark',
  cta: 'dark',
  quotes: 'white',
  logos: 'white',
  showcase: 'dark',
  'case-grid': 'dark',
  adhoc: 'dark',
  method: 'dark',
};

const LISTS = ['list', 'cards', 'steps', 'portfolio', 'plans', 'faq', 'quotes', 'logos', 'uses', 'examples', 'glyphs', 'checklist', 'history', 'method', 'figures', 'gallery', 'results'];

function kindOf(section: Section): string {
  return section.type.split('.').pop() ?? section.type;
}

function toneOf(kind: string, data: Data, byType = false): Tone {
  const tone = byType ? '' : text(data, 'tone');
  return tone === 'dark' || tone === 'light' || tone === 'white' ? tone : TONES[kind] ?? 'light';
}

function isEmpty(section: Section): boolean {
  const kind = kindOf(section);
  const data = section.data ?? {};

  if (kind === 'cases') {
    return !Array.isArray(data.cases) || data.cases.length === 0;
  }
  if (kind === 'showcase') {
    return !hasCases(data, 'cases') && placeholders(data).length === 0;
  }
  if (kind === 'case-grid') {
    return !hasCases(data, 'cases', 'featured_cases');
  }
  if (kind === 'pricing') {
    return rows(data, 'categories').length === 0;
  }
  if (LISTS.includes(kind)) {
    return rows(data).length === 0 && strings(data, 'items').length === 0;
  }

  return false;
}

/**
 * Em páginas de case (`variant="case"`) o tom vem só do tipo de seção,
 * ignorando o tom escolhido no editor, para que todo case siga o mesmo ritmo.
 */
export function Sections({ sections, form, variant }: { sections: Section[]; form: SiteForm | null; variant?: 'case' }) {
  const visible = sections.filter((section) => !isEmpty(section));
  const isCase = variant === 'case';
  let previous: Tone | null = null;
  let previousKind = '';

  return (
    <>
      {visible.map((section) => {
        const kind = kindOf(section);
        const data = section.data ?? {};
        const tone = toneOf(kind, data, isCase);
        const flush = previous === tone && kind !== 'hero';
        const stacked = kind === 'prose' && previousKind === 'prose';
        previous = tone;
        previousKind = kind;

        return <SectionView key={section.id} kind={kind} data={data} tone={tone} flush={flush} stacked={stacked} form={form} isCase={isCase} />;
      })}
    </>
  );
}

type ShellProps = { tone: Tone; flush: boolean; mark: boolean; id?: string; className?: string; wrapClass?: string; children: ReactNode };

function Shell({ tone, flush, mark, id, className, wrapClass, children }: ShellProps) {
  const classes = ['section', `on-${tone}`, flush ? 'is-flush' : '', mark ? 'is-mark-draw' : '', className ?? ''].filter(Boolean).join(' ');

  return (
    <section className={classes} id={id || undefined}>
      <div className={wrapClass ? `wrap ${wrapClass}` : 'wrap'}>{children}</div>
    </section>
  );
}

function SectionView({ kind, data, tone, flush, stacked, form, isCase }: { kind: string; data: Data; tone: Tone; flush: boolean; stacked: boolean; form: SiteForm | null; isCase: boolean }) {
  const shell = { tone, flush, mark: Boolean(data.mark_draw), id: text(data, 'anchor') };

  switch (kind) {
    case 'hero': {
      const lead = data.lead_form
        ? <LeadForm className="hero-form" id="contato" whatsappHref={text(data, 'lead_whatsapp')} title={text(data, 'lead_title') || undefined} />
        : undefined;

      return <Hero data={data} aside={lead} />;
    }

    case 'prose':
      return (
        <Shell {...shell}>
          <div className={isCase ? 'prose-grid is-case' : stacked ? 'prose-grid is-stack' : 'prose-grid'}>
            {text(data, 'title') ? <h2 className="h2"><Marked value={text(data, 'title')} /></h2> : <span />}
            <Rich value={text(data, 'body')} />
          </div>
        </Shell>
      );

    case 'list':
    case 'cards':
    case 'portfolio':
      if (kind === 'list' && hasStackLayers(data) && (text(data, 'layout') === 'stack' || (isCase && /^stack\b/i.test(plain(text(data, 'title')))))) {
        return <Shell {...shell}><StackBlock data={data} /></Shell>;
      }

      return (
        <Shell {...shell}>
          <Head title={text(data, 'title')} intro={text(data, 'intro')} href={text(data, 'href')} />
          <ListBody items={rows(data)} feature={Boolean(data.feature)} wide={text(data, 'columns') !== '3'} />
        </Shell>
      );

    case 'steps': {
      const items = rows(data);
      const timeline = Boolean(data.timeline);
      const size = items.length === 4 || items.length === 5 ? ` is-${items.length}` : '';

      return (
        <Shell {...shell} className={timeline ? 'is-timeline-head' : undefined}>
          <Head title={text(data, 'title')} intro={timeline ? text(data, 'intro') : undefined} />
          <ol className={timeline ? `steps is-timeline${size} is-scrub` : 'steps'}>
            {items.map((item, index) => (
              <li key={`${text(item, 'title')}-${index}`}>
                <span className="mono">{pad(index)}</span>
                <h3 className="h3">{text(item, 'title')}</h3>
                <Rich value={text(item, 'body')} />
              </li>
            ))}
          </ol>
          {!timeline && text(data, 'intro') ? <Rich className="steps-note" value={text(data, 'intro')} /> : null}
        </Shell>
      );
    }

    case 'plans':
      return (
        <Shell {...shell} id={shell.id || 'planos'}>
          <Head title={text(data, 'title')} intro={text(data, 'intro')} />
          <div className="plans">
            {rows(data).map((item, index) => (
              <article className="plan" key={`${text(item, 'name')}-${index}`}>
                <h3 className="name">{text(item, 'name')}</h3>
                <p className="price">
                  {text(item, 'price')}
                  {text(item, 'period') ? <small>{text(item, 'period')}</small> : null}
                </p>
                <Rich value={text(item, 'body')} />
                <Cta href={text(item, 'href')} label={text(item, 'cta') || t('plans.cta')} variant="primary" />
              </article>
            ))}
          </div>
        </Shell>
      );

    case 'pricing':
      return (
        <Shell {...shell} id={shell.id || 'planos'}>
          <PricingBlock data={data} />
        </Shell>
      );

    case 'quotes':
      return (
        <Shell {...shell}>
          <Quotes title={text(data, 'title')} items={rows(data)} even={Boolean(data.even) || isCase} />
        </Shell>
      );

    case 'logos': {
      const clients = Boolean(data.clients);

      if (isCase && rows(data).every((item) => !text(item, 'image'))) {
        return (
          <Shell {...shell}>
            <Head title={text(data, 'title')} intro={text(data, 'intro')} />
            <ListBody items={rows(data).map((item) => ({ title: text(item, 'name') }))} />
          </Shell>
        );
      }

      const list = <LogoWall data={data} />;

      return (
        <Shell {...shell} wrapClass={clients ? 'clients' : undefined}>
          {clients ? (
            <header className="clients-head">
              <h2 className="h2"><Marked value={text(data, 'title')} /></h2>
              <Rich className="intro" value={text(data, 'intro')} />
            </header>
          ) : <Head title={text(data, 'title')} intro={text(data, 'intro')} />}
          {list}
        </Shell>
      );
    }

    case 'faq':
      return (
        <Shell {...shell}>
          <div className="faq-grid">
            <h2 className="h2"><Marked value={text(data, 'title') || t('faq.title')} /></h2>
            <div className="faq">
              {rows(data).map((item, index) => (
                <details key={`${text(item, 'question')}-${index}`}>
                  <summary>
                    {text(item, 'question')}
                    <span className="plus" aria-hidden="true" />
                  </summary>
                  <Rich value={text(item, 'answer')} />
                </details>
              ))}
            </div>
          </div>
        </Shell>
      );

    case 'cta':
      return (
        <Shell {...shell} className="cta-block">
          <h2><Marked value={text(data, 'title')} /></h2>
          <div className="cta-side">
            <Rich value={text(data, 'body')} />
            <div className="actions">
              <Cta href={text(data, 'href')} label={text(data, 'label')} variant="primary" />
              <Cta href={text(data, 'secondary_href')} label={text(data, 'secondary_label')} variant="line" />
            </div>
          </div>
        </Shell>
      );

    case 'contact':
      if (text(data, 'variant') === 'split') {
        return (
          <Shell {...shell} id={shell.id || 'contato'} wrapClass="contact-split">
            <div>
              {text(data, 'title') ? <h2 className="h2"><Marked value={text(data, 'title')} /></h2> : null}
              <Rich className="lead" value={text(data, 'intro')} />
            </div>
            <div className={tone === 'dark' ? 'form-card is-dark' : 'form-card'}>
              <ContactForm form={form} />
            </div>
          </Shell>
        );
      }

      return (
        <Shell {...shell} id={shell.id || 'contato'}>
          <div className="contact-grid">
            <div>
              {text(data, 'title') ? <h2 className="h2"><Marked value={text(data, 'title')} /></h2> : null}
              <Rich value={text(data, 'intro')} />
            </div>
            <div className="form-card">
              <ContactForm form={form} />
            </div>
          </div>
        </Shell>
      );

    case 'cases':
      return (
        <Shell {...shell}>
          <CasesPair data={data} />
        </Shell>
      );

    case 'cases-list':
      return (
        <Shell {...shell}>
          <CasesList data={data} />
        </Shell>
      );

    case 'gallery': {
      const images = rows(data)
        .filter((item) => text(item, 'image'))
        .map((item) => ({ src: text(item, 'image'), caption: text(item, 'caption'), alt: text(item, 'alt') || text(item, 'caption') }));

      return (
        <Shell {...shell}>
          <Head title={text(data, 'title')} intro={text(data, 'intro')} />
          <Gallery images={images} />
        </Shell>
      );
    }

    case 'results':
      return (
        <Shell {...shell}>
          <CaseResults title={text(data, 'title')} items={rows(data).map((item) => ({ value: text(item, 'value'), label: text(item, 'label') }))} />
        </Shell>
      );

    case 'uses':
      return <Shell {...shell}><UsesBlock data={data} /></Shell>;

    case 'examples':
      return <Shell {...shell}><ExamplesBlock data={data} /></Shell>;

    case 'glyphs':
      return <Shell {...shell}><GlyphsBlock data={data} /></Shell>;

    case 'checklist':
      return <Shell {...shell}><ChecklistBlock data={data} /></Shell>;

    case 'panel':
      return <Shell {...shell}><PanelBlock data={data} /></Shell>;

    case 'figures':
      return <Shell {...shell}><FiguresBlock data={data} /></Shell>;

    case 'showcase':
      return <Shell {...shell}><ShowcaseBlock data={data} /></Shell>;

    case 'case-grid':
      return <Shell {...shell}><CaseGridBlock data={data} /></Shell>;

    case 'cases-browser':
      return <Shell {...shell}><CasesBrowserBlock data={data} /></Shell>;

    case 'adhoc':
      return <Shell {...shell}><AdhocBlock data={data} /></Shell>;

    case 'history':
      return <Shell {...shell}><HistoryBlock data={data} /></Shell>;

    case 'method':
      return <Shell {...shell} className="is-timeline-head"><MethodBlock data={data} /></Shell>;

    default:
      return null;
  }
}

function ListBody({ items, feature = false, wide = true }: { items: Data[]; feature?: boolean; wide?: boolean }) {
  const withBody = items.some((item) => text(item, 'body'));
  const allLinked = items.length > 0 && items.every((item) => text(item, 'href'));
  const withLinks = items.some((item) => text(item, 'href'));

  if (withBody && allLinked) {
    return (
      <ul className={['cards', wide && (items.length === 4 || items.length === 5) ? `is-${items.length}` : '', feature ? 'is-feature' : ''].filter(Boolean).join(' ')}>
        {items.map((item, index) => (
          <li key={`${text(item, 'title')}-${index}`}>
            <LinkTo className="card" href={text(item, 'href')}>
              <span className="num">
                <span className="mono">{pad(index)}</span>
                <span className="go"><Arrow /></span>
              </span>
              <div>
                <h3 className="h3">{plain(text(item, 'title'))}</h3>
                <Rich value={text(item, 'body')} />
              </div>
            </LinkTo>
          </li>
        ))}
      </ul>
    );
  }

  if (withBody) {
    return (
      <dl className="def-rows">
        {items.map((item, index) => {
          const href = text(item, 'href');

          return (
            <div key={`${text(item, 'title')}-${index}`}>
              <dt className="h3">{href ? <LinkTo href={href}>{plain(text(item, 'title'))}</LinkTo> : plain(text(item, 'title'))}</dt>
              <dd><Rich value={text(item, 'body')} /></dd>
            </div>
          );
        })}
      </dl>
    );
  }

  if (withLinks) {
    return (
      <ul className="link-rows">
        {items.map((item, index) => (
          <li key={`${text(item, 'title')}-${index}`}>
            {text(item, 'href')
              ? <LinkTo href={text(item, 'href')}>{text(item, 'title')}<span className="go"><Arrow /></span></LinkTo>
              : <span className="row">{text(item, 'title')}</span>}
          </li>
        ))}
      </ul>
    );
  }

  const short = items.length >= 6 && items.every((item) => text(item, 'title').length <= 22);

  if (short) {
    return (
      <ul className="chips">
        {items.map((item, index) => <li key={`${text(item, 'title')}-${index}`}>{text(item, 'title')}</li>)}
      </ul>
    );
  }

  return (
    <ul className="bullets">
      {items.map((item, index) => (
        <li key={`${text(item, 'title')}-${index}`}>{text(item, 'title')}</li>
      ))}
    </ul>
  );
}

function QuoteBy({ item }: { item: Data }) {
  return (
    <footer className="quote-by">
      <b>{text(item, 'author')}</b>
      {text(item, 'company') ? <span>{text(item, 'company')}</span> : null}
    </footer>
  );
}

function Quotes({ title, items, even = false }: { title: string; items: Data[]; even?: boolean }) {
  if (items.length === 1) {
    const item = items[0];

    return (
      <div className="quote-solo">
        <span className="q-mark" aria-hidden="true">“</span>
        <blockquote>
          <Rich value={text(item, 'quote')} />
          <QuoteBy item={item} />
        </blockquote>
      </div>
    );
  }

  return (
    <>
      <Head title={title} />
      <div className={even ? `quotes is-even${items.length === 2 ? ' is-2' : ''}` : 'quotes'}>
        {items.map((item, index) => (
          <blockquote key={`${text(item, 'author')}-${index}`}>
            <Rich value={text(item, 'quote')} />
            <QuoteBy item={item} />
          </blockquote>
        ))}
      </div>
    </>
  );
}

export function ContactForm({ form }: { form: SiteForm | null }) {
  const fields = (form?.sections ?? []).flatMap((section) => section.fields ?? []);
  const [values, setValues] = useState<Record<string, string>>({});
  const [status, setStatus] = useState<string | null>(null);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [busy, setBusy] = useState(false);
  const [chatHref, setChatHref] = useState('');

  async function submit(event: FormEvent) {
    event.preventDefault();
    setBusy(true);
    setStatus(null);
    setErrors({});
    setChatHref(document.querySelector<HTMLAnchorElement>('a.wa-float')?.href ?? '');

    try {
      const response = await fetch('/api/contato', {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ ...values, empresa_site: '' }),
      });
      const payload = await response.json() as { status?: string; errors?: Record<string, string[]> };
      if (!response.ok) {
        setErrors(payload.errors ?? {});
        setStatus(payload.status ?? 'invalido');
        return;
      }
      setStatus(payload.status ?? 'enviado');
      if (payload.status === 'enviado') {
        setValues({});
      }
    } catch {
      setStatus('erro');
    } finally {
      setBusy(false);
    }
  }

  return (
    <form className="site-form" data-endpoint="/api/contato" data-sent={t('form.sent')} data-error={t('form.error')} data-invalid={t('form.invalid')} data-wa-label={t('lead.whatsapp_now')} onSubmit={(event) => void submit(event)}>
      <input type="text" name="empresa_site" tabIndex={-1} autoComplete="off" className="hp" aria-hidden="true" />
      {fields.map((field) => (
        <Field
          key={field.name}
          field={field}
          value={values[field.name] ?? ''}
          lgpd={form?.lgpd_label}
          error={errors[field.name]?.[0]}
          onChange={(value) => setValues((current) => ({ ...current, [field.name]: value }))}
        />
      ))}
      <div className="form-status-slot" aria-live="polite">
        {status === 'enviado' ? <p className="form-status is-ok">{t('form.sent')}</p> : null}
        {status === 'erro' ? <p className="form-status is-error">{t('form.error')}{chatHref ? <> <a href={chatHref} target="_blank" rel="noreferrer">{t('lead.whatsapp_now')}</a></> : null}</p> : null}
        {status === 'invalido' ? <p className="form-status is-error">{t('form.invalid')}</p> : null}
      </div>
      <button className="pill solid" type="submit" disabled={busy}>{busy ? t('form.sending') : form?.submit_label || t('form.submit')}</button>
    </form>
  );
}

// Envio desligado durante o ajuste de layout e animação: o card gira sem chamar /api/contato/lead.
const LEAD_DRY_RUN = true;

// Lead de click-to-WhatsApp: a frente coleta o interesse; ao enviar, o card gira e o verso agradece e abre o WhatsApp.
export function LeadForm({ whatsappHref, title, className, id }: { whatsappHref: string; title?: string; className?: string; id?: string }) {
  const [nome, setNome] = useState('');
  const [phone, setPhone] = useState<PhoneValue>({ iso: 'BR', number: '' });
  const [sent, setSent] = useState<{ nome: string; whatsapp: string } | null>(null);
  const [status, setStatus] = useState<string | null>(null);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [busy, setBusy] = useState(false);
  const backTitle = useRef<HTMLHeadingElement>(null);

  useEffect(() => {
    if (sent) {
      backTitle.current?.focus({ preventScroll: true });
    }
  }, [sent]);

  async function submit(event: FormEvent) {
    event.preventDefault();
    setBusy(true);
    setStatus(null);
    setErrors({});

    if (!isPhoneComplete(phone)) {
      setErrors({ whatsapp: [t('lead.phone_invalid')] });
      setBusy(false);
      return;
    }

    const lead = { nome: nome.trim(), whatsapp: phoneToString(phone) };
    const reset = () => {
      setSent(lead);
      setNome('');
      setPhone((current) => ({ ...current, number: '' }));
    };

    if (LEAD_DRY_RUN) {
      await new Promise((resolve) => setTimeout(resolve, 600));
      reset();
      setBusy(false);
      return;
    }

    try {
      const response = await fetch('/api/contato/lead', {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...lead, pagina: window.location.pathname, empresa_site: '' }),
      });
      const payload = await response.json() as { status?: string; errors?: Record<string, string[]> };
      if (!response.ok) {
        setErrors(payload.errors ?? {});
        setStatus(payload.status ?? 'invalido');
        return;
      }
      reset();
    } catch {
      setStatus('erro');
    } finally {
      setBusy(false);
    }
  }

  const firstName = sent?.nome.split(/\s+/)[0] ?? '';
  const chatHref = withWhatsAppText(whatsappHref, firstName ? t('lead.wa_text', { name: firstName }) : '');

  return (
    <div className={['lead-card', sent ? 'is-sent' : '', className ?? ''].filter(Boolean).join(' ')} id={id} data-whatsapp={whatsappHref}>
      <div className="lead-flip">
        <div className="lead-face lead-front" inert={sent ? true : undefined}>
          <form className="lead-form" data-phone-invalid={t('lead.phone_invalid')} data-error={t('form.error')} data-invalid={t('form.invalid')} data-wa-label={t('lead.whatsapp_now')} data-sending={t('form.sending')} onSubmit={(event) => void submit(event)}>
            <h2 className="lead-form-title">{title || t('lead.title')}</h2>
            <p className="lead-form-text">{t('lead.text')}</p>
            <input type="text" name="empresa_site" tabIndex={-1} autoComplete="off" className="hp" aria-hidden="true" />
            <label className="is-wide">
              {t('lead.name')}
              <input
                name="nome"
                type="text"
                autoComplete="name"
                autoCapitalize="words"
                required
                value={nome}
                aria-invalid={Boolean(errors.nome)}
                onChange={(event) => setNome(event.target.value)}
              />
              {errors.nome?.[0] ? <span className="field-error">{errors.nome[0]}</span> : null}
            </label>
            <div className="lead-field is-wide">
              <label htmlFor={`${id ?? 'lead'}-whatsapp`}>{t('lead.whatsapp')}</label>
              <PhoneInput
                id={`${id ?? 'lead'}-whatsapp`}
                name="whatsapp"
                value={phone}
                invalid={Boolean(errors.whatsapp)}
                countryLabel={t('lead.country')}
                onChange={(next) => {
                  setPhone(next);
                  if (errors.whatsapp) {
                    setErrors(({ whatsapp: _drop, ...rest }) => rest);
                  }
                }}
              />
              {errors.whatsapp?.[0] ? <span className="field-error">{errors.whatsapp[0]}</span> : null}
            </div>
            <div className="form-status-slot" aria-live="polite">
              {status === 'erro' ? <p className="form-status is-error">{t('form.error')}{whatsappHref ? <> <a href={whatsappHref} target="_blank" rel="noreferrer">{t('lead.whatsapp_now')}</a></> : null}</p> : null}
              {status === 'invalido' ? <p className="form-status is-error">{t('form.invalid')}</p> : null}
            </div>
            <button className="pill solid" type="submit" disabled={busy}>{busy ? t('form.sending') : t('lead.submit')}</button>
          </form>
        </div>
        <div className="lead-face lead-back" inert={sent ? undefined : true} aria-live="polite">
          <span className="lead-check" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M6 12.5l4 4 8-9" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" /></svg>
          </span>
          <h2 className="lead-form-title" data-template={t('lead.thanks_title')} data-fallback={t('lead.thanks_title_plain')} ref={backTitle} tabIndex={-1}>{firstName ? t('lead.thanks_title', { name: firstName }) : t('lead.thanks_title_plain')}</h2>
          <p className="lead-back-text" data-template={t('lead.thanks_text')} data-fallback={t('lead.thanks_text_plain')}>{sent?.whatsapp ? t('lead.thanks_text', { phone: sent.whatsapp }) : t('lead.thanks_text_plain')}</p>
          <a className="pill lime" href={chatHref} data-wa-template={t('lead.wa_text')} target="_blank" rel="noreferrer">
            {t('lead.whatsapp_now')}
            <span className="arrow"><Arrow /></span>
          </a>
        </div>
      </div>
    </div>
  );
}

function withWhatsAppText(href: string, message: string): string {
  if (!message) {
    return href;
  }

  try {
    const url = new URL(href);
    url.searchParams.set('text', message);
    return url.toString();
  } catch {
    return href;
  }
}

function Field({
  field,
  value,
  lgpd,
  error,
  onChange,
}: {
  field: FormField;
  value: string;
  lgpd?: string;
  error?: string;
  onChange: (value: string) => void;
}): ReactNode {
  const label = field.name === 'lgpd' && lgpd ? lgpd : field.label;
  const errorNode = error ? <span className="field-error">{error}</span> : null;

  if (field.type === 'textarea') {
    return (
      <label className="is-wide">
        {label}
        <textarea name={field.name} required={field.required} value={value} aria-invalid={Boolean(error)} onChange={(event) => onChange(event.target.value)} />
        {errorNode}
      </label>
    );
  }

  if (field.type === 'select') {
    return (
      <label className="is-wide">
        {label}
        <select name={field.name} required={field.required} value={value} aria-invalid={Boolean(error)} onChange={(event) => onChange(event.target.value)}>
          <option value="">{t('form.select')}</option>
          {(field.options ?? []).map((option) => (
            <option key={option.value} value={option.value}>{option.label}</option>
          ))}
        </select>
        {errorNode}
      </label>
    );
  }

  if (field.type === 'checkbox') {
    return (
      <label className="check">
        <input type="checkbox" name={field.name} value="1" required={field.required} checked={value === '1'} onChange={(event) => onChange(event.target.checked ? '1' : '')} />
        <span>{label}</span>
        {errorNode}
      </label>
    );
  }

  const type = field.type === 'email' || field.type === 'tel' ? field.type : 'text';
  const autoComplete = field.type === 'email' ? 'email' : field.type === 'tel' ? 'tel' : field.name === 'nome' ? 'name' : undefined;

  return (
    <label>
      {label}
      <input name={field.name} type={type} autoComplete={autoComplete} autoCapitalize={field.name === 'nome' ? 'words' : undefined} required={field.required} value={value} aria-invalid={Boolean(error)} onChange={(event) => onChange(event.target.value)} />
      {errorNode}
    </label>
  );
}
