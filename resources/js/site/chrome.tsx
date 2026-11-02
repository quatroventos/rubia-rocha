import { useEffect, useState, type CSSProperties, type FocusEvent } from 'react';
import { LOCALES, localePath, rememberLocale, t } from './i18n';
import type { MenuNode, SitePayload } from './types';
import { Arrow, Caret, Cta, LinkTo, WhatsAppIcon } from './ui';

type Alternates = Record<string, string> | undefined;

function Brand() {
  return (
    <a className="brand" href={localePath('/')} aria-label={t('brand.home')}>
      <img src="/assets/logo-quatroventos.svg" alt="" width="436" height="69" />
    </a>
  );
}

export function LangSwitch({ site, locale, alternates, variant = 'compact' }: { site: SitePayload | null; locale: string; alternates: Alternates; variant?: 'compact' | 'full' | 'menu' }) {
  const locales = (site?.locales?.length ? site.locales : LOCALES).filter((item) => alternates?.[item.code]);
  if (locales.length < 2 || !alternates) {
    return null;
  }

  if (variant === 'menu') {
    const current = locales.find((item) => item.code === locale) ?? locales[0];

    return (
      <details className="lang-menu">
        <summary aria-label={`${t('lang.label')}: ${current.name}`}>
          <span>{current.short}</span>
          <Caret />
        </summary>
        <ul>
          {locales.map((item) => (
            <li key={item.code}>
              <a
                href={alternates[item.code]}
                hrefLang={item.hreflang}
                lang={item.hreflang}
                aria-current={item.code === locale ? 'true' : undefined}
                onClick={() => rememberLocale(item.code, site?.cookie)}
              >
                <span className="mono">{item.short}</span>
                {item.name}
              </a>
            </li>
          ))}
        </ul>
      </details>
    );
  }

  const index = Math.max(0, locales.findIndex((item) => item.code === locale));
  const style = { '--lang-count': locales.length, '--lang-index': index } as CSSProperties;

  return (
    <nav className={`lang-switch is-${variant}`} aria-label={t('lang.label')} style={style}>
      <span className="lang-switch-thumb" aria-hidden="true" />
      {locales.map((item) => (
        <a
          key={item.code}
          href={alternates[item.code]}
          hrefLang={item.hreflang}
          lang={item.hreflang}
          title={item.name}
          aria-current={item.code === locale ? 'true' : undefined}
          onClick={() => rememberLocale(item.code, site?.cookie)}
        >
          {variant === 'full' ? item.name : item.short}
        </a>
      ))}
    </nav>
  );
}

function isCurrent(href: string, pathname: string): boolean {
  if (href === '/' || /^\/[a-z]{2}$/.test(href)) {
    return pathname === href;
  }

  return pathname === href || pathname.startsWith(`${href}/`);
}

function megaExtras(site: SitePayload | null, items: MenuNode[]): MenuNode[] {
  const seen = new Set(items.map((item) => item.href));
  const loose = items.filter((item) => item.children.length === 0);
  const footerLoose = (site?.menus.footer ?? [])
    .flatMap((item) => (item.children.length ? item.children : [item]))
    .filter((item) => !seen.has(item.href) && !(site?.menus.header ?? []).some((hub) => hub.children.some((child) => child.href === item.href)));

  return [...loose, ...footerLoose].filter((item, index, list) => list.findIndex((other) => other.href === item.href) === index);
}

function MegaMenu({ hubs, extras, active, whatsapp, pathname, onClose }: { hubs: MenuNode[]; extras: MenuNode[]; active: string | null; whatsapp: string; pathname: string; onClose: () => void }) {
  return (
    <div className={active ? 'mega is-open' : 'mega'} id="mega-menu" aria-hidden={!active}>
      <div className="wrap mega-grid">
        {hubs.map((hub) => (
          <div key={hub.href} className={active === hub.href ? 'mega-col is-active' : 'mega-col'}>
            <LinkTo className="mega-hub" href={hub.href} current={isCurrent(hub.href, pathname)} onClick={onClose}>
              {hub.label}
              <Arrow />
            </LinkTo>
            <ul>
              {hub.children.map((child) => (
                <li key={`${child.href}-${child.label}`}>
                  <LinkTo href={child.href} current={isCurrent(child.href, pathname)} onClick={onClose}>{child.label}</LinkTo>
                </li>
              ))}
            </ul>
          </div>
        ))}
        <div className="mega-side">
          <ul>
            {extras.map((item) => (
              <li key={`${item.href}-${item.label}`}>
                <LinkTo href={item.href} current={isCurrent(item.href, pathname)} onClick={onClose}>{item.label}</LinkTo>
              </li>
            ))}
          </ul>
          {whatsapp ? <Cta href={whatsapp} label={t('nav.cta')} variant="primary" /> : null}
        </div>
      </div>
    </div>
  );
}

export function Header({ site, pathname, locale, alternates }: { site: SitePayload | null; pathname: string; locale: string; alternates: Alternates }) {
  const [open, setOpen] = useState(false);
  const [solid, setSolid] = useState(false);
  const [expanded, setExpanded] = useState<string | null>(null);
  const [mega, setMega] = useState<string | null>(null);
  const items = site?.menus.header ?? [];
  const hubs = items.filter((item) => item.children.length > 0);
  const extras = megaExtras(site, items);
  const whatsapp = site?.whatsapp_url ?? '';

  useEffect(() => {
    setMega(null);
  }, [pathname]);

  useEffect(() => {
    if (!mega) {
      return;
    }

    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setMega(null);
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [mega]);

  function onHeaderBlur(event: FocusEvent<HTMLElement>) {
    if (!event.currentTarget.contains(event.relatedTarget as Node | null)) {
      setMega(null);
    }
  }

  useEffect(() => {
    const onScroll = () => setSolid(window.scrollY > 24);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  useEffect(() => {
    setOpen(false);
    setExpanded(null);
  }, [pathname]);

  useEffect(() => {
    document.body.style.overflow = open ? 'hidden' : '';
    if (!open) {
      return;
    }

    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setOpen(false);
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open]);

  return (
    <>
      <a className="skip" href="#conteudo">{t('nav.skip')}</a>
      <header
        className={solid || open || mega ? 'site-header is-solid' : 'site-header'}
        onMouseLeave={() => setMega(null)}
        onBlur={onHeaderBlur}
      >
        <div className="wrap header-bar">
          <Brand />
          <nav className="main-nav" aria-label={t('nav.main')}>
            <ul>
              {items.map((item) => {
                const hasMega = item.children.length > 0;

                return (
                  <li
                    key={`${item.href}-${item.label}`}
                    className={hasMega && mega === item.href ? 'is-open' : undefined}
                    onMouseEnter={() => setMega(hasMega ? item.href : null)}
                    onFocus={() => setMega(hasMega ? item.href : null)}
                  >
                    <LinkTo href={item.href} current={isCurrent(item.href, pathname)}>
                      <span>{item.label}</span>
                      {hasMega ? <Caret /> : null}
                    </LinkTo>
                  </li>
                );
              })}
            </ul>
          </nav>
          {whatsapp ? <LinkTo className="pill solid small header-cta" href={whatsapp}>{t('nav.cta')}</LinkTo> : null}
          <button className="nav-toggle" type="button" aria-expanded={open} aria-controls="menu-mobile" aria-label={open ? t('nav.close') : t('nav.open')} onClick={() => setOpen((value) => !value)}>
            <span />
          </button>
        </div>
        {hubs.length ? (
          <MegaMenu hubs={hubs} extras={extras} active={mega} whatsapp={whatsapp} pathname={pathname} onClose={() => setMega(null)} />
        ) : null}
      </header>
      <div className={open ? 'mobile-nav is-open' : 'mobile-nav'} id="menu-mobile" aria-hidden={!open}>
        <ul>
          {[...items, ...extras.filter((extra) => !items.some((item) => item.href === extra.href))].map((item) => (
            <MobileItem
              key={`${item.href}-${item.label}`}
              item={item}
              expanded={expanded === item.href}
              onToggle={() => setExpanded((value) => (value === item.href ? null : item.href))}
              tabbable={open}
            />
          ))}
        </ul>
        {whatsapp ? <LinkTo className="pill solid" href={whatsapp}>{t('nav.cta')}</LinkTo> : null}
      </div>
    </>
  );
}

function MobileItem({ item, expanded, onToggle, tabbable }: { item: MenuNode; expanded: boolean; onToggle: () => void; tabbable: boolean }) {
  const id = `sub-${item.href.replace(/[^a-z0-9]+/gi, '-')}`;

  return (
    <li>
      <div className="row-link">
        <a href={item.href} tabIndex={tabbable ? undefined : -1}>{item.label}</a>
        {item.children.length ? (
          <button type="button" aria-expanded={expanded} aria-controls={id} aria-label={t('nav.services_of', { label: item.label })} onClick={onToggle} tabIndex={tabbable ? undefined : -1}>
            <svg viewBox="0 0 10 10" aria-hidden="true"><path d="M1 3l4 4 4-4" fill="none" stroke="currentColor" strokeWidth="1.5" /></svg>
          </button>
        ) : null}
      </div>
      {item.children.length ? (
        <ul className="sub" id={id} hidden={!expanded}>
          {item.children.map((child) => (
            <li key={`${child.href}-${child.label}`}><LinkTo href={child.href}>{child.label}</LinkTo></li>
          ))}
        </ul>
      ) : null}
    </li>
  );
}

export function Footer({ site, locale, alternates }: { site: SitePayload | null; locale: string; alternates: Alternates }) {
  const year = new Date().getFullYear();
  const columns = (site?.menus.footer ?? []).filter((item) => item.children.length > 0);
  const loose = (site?.menus.footer ?? []).filter((item) => item.children.length === 0);
  const social = [
    { label: 'Instagram', href: site?.instagram_url },
    { label: 'LinkedIn', href: site?.linkedin_url },
    { label: 'Facebook', href: site?.facebook_url },
  ].filter((item): item is { label: string; href: string } => Boolean(item.href));

  return (
    <footer className="site-footer">
      <div className="wrap">
        <div className="footer-top">
          <div className="footer-brand">
            <Brand />
            <div className="footer-contact">
              {site?.whatsapp_url ? <LinkTo href={site.whatsapp_url}>WhatsApp {site.phone ?? ''}</LinkTo> : null}
              {site?.email ? <a href={`mailto:${site.email}`}>{site.email}</a> : null}
              {site?.address ? <span>{site.address.split('\n')[0]}</span> : null}
            </div>
          </div>
          <div className="footer-cols">
            {columns.map((column) => (
              <div key={`${column.href}-${column.label}`}>
                <h2><LinkTo href={column.href}>{column.label}</LinkTo></h2>
                <ul>
                  {column.children.map((child) => (
                    <li key={`${child.href}-${child.label}`}><LinkTo href={child.href}>{child.label}</LinkTo></li>
                  ))}
                </ul>
              </div>
            ))}
            {loose.length ? (
              <div>
                <ul>
                  {loose.map((item) => <li key={`${item.href}-${item.label}`}><LinkTo href={item.href}>{item.label}</LinkTo></li>)}
                </ul>
              </div>
            ) : null}
          </div>
        </div>
        <div className="footer-bottom">
          <span>© {year} {site?.name || 'Quatro Ventos'}. {t('footer.since')}</span>
          <span className="footer-motto">Design, Code, Test, Repeat.</span>
          {site?.legal?.privacy || site?.legal?.cookies ? (
            <nav aria-label={t('footer.legal')}>
              {site.legal.privacy ? <a href={site.legal.privacy}>{t('footer.privacy')}</a> : null}
              {site.legal.cookies ? <a href={site.legal.cookies}>{t('footer.cookies')}</a> : null}
              <button type="button" className="footer-link" data-cookie-prefs>{t('cookies.prefs')}</button>
            </nav>
          ) : null}
          {social.length ? (
            <nav aria-label={t('footer.social')}>
              {social.map((item) => <LinkTo key={item.label} href={item.href}>{item.label}</LinkTo>)}
            </nav>
          ) : null}
        </div>
      </div>
    </footer>
  );
}

export function WhatsAppFloat({ href }: { href: string | null | undefined }) {
  if (!href) {
    return null;
  }

  return (
    <LinkTo className="wa-float" href={href} label={t('whatsapp.float')}>
      <WhatsAppIcon />
    </LinkTo>
  );
}

export function LangFloat({ site, locale, alternates }: { site: SitePayload | null; locale: string; alternates: Alternates }) {
  const hasWhatsApp = Boolean(site?.whatsapp_url);

  return (
    <div className={hasWhatsApp ? 'lang-float is-beside-wa' : 'lang-float'}>
      <LangSwitch site={site} locale={locale} alternates={alternates} variant="menu" />
    </div>
  );
}

// Fica oculto no HTML; behaviors.js mostra enquanto não houver escolha gravada e libera as métricas após o aceite.
export function CookieNotice({ site }: { site: SitePayload | null }) {
  const policy = site?.legal?.cookies ?? site?.legal?.privacy;

  return (
    <section className="cookie-notice" role="region" aria-label={t('cookies.label')} hidden data-cookie-notice>
      <p>
        {t('cookies.text')}
        {policy ? <> <a href={policy}>{t('cookies.policy')}</a>.</> : null}
      </p>
      <div className="cookie-notice-actions">
        <button type="button" className="pill line small" data-cookie-choice="essential">{t('cookies.reject')}</button>
        <button type="button" className="pill solid small" data-cookie-choice="all">{t('cookies.accept')}</button>
      </div>
    </section>
  );
}
