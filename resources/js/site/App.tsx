import { useEffect, useRef, useState, type MouseEvent } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { CmsCases } from './case-showcase';
import { CasePage } from './cases';
import { Footer, Header, LangFloat, WhatsAppFloat } from './chrome';
import { AgentLog } from './hero';
import { DEFAULT_LOCALE, LOCALES, localePath, setLocale, splitLocalePath, t } from './i18n';
import { applySeo } from './seo';
import { Sections } from './sections';
import type { PagePayload, SitePayload } from './types';
import { Cta } from './ui';

function slugFromPath(pathname: string): string {
  const path = pathname.replace(/\/+$/, '') || '/';
  return path === '/' ? 'home' : path.slice(1);
}

function sameSite(href: string): URL | null {
  try {
    return new URL(href, window.location.origin);
  } catch {
    return null;
  }
}

export function App() {
  const location = useLocation();
  const navigate = useNavigate();
  const boot = window.__SITE__;
  const [site, setSite] = useState<SitePayload | null>(boot?.site ?? null);
  const [page, setPage] = useState<PagePayload | null>(boot?.page ?? null);
  const [missing, setMissing] = useState(false);
  const locales = site?.locales?.length ? site.locales : LOCALES;
  const { locale, path } = splitLocalePath(location.pathname, locales);
  const booted = useRef(boot?.page ? `${boot.page.locale ?? DEFAULT_LOCALE}:${boot.page.slug}` : null);
  const preview = Boolean(boot?.preview);
  const previewScrolled = useRef(false);

  setLocale(preview ? page?.locale ?? DEFAULT_LOCALE : locale);

  useEffect(() => {
    if (!preview) {
      return;
    }

    const draftUrl = boot?.draftUrl;
    if (!draftUrl) {
      return;
    }

    let version = '';
    let stopped = false;
    let timer = 0;

    async function poll() {
      try {
        const response = await fetch(`${draftUrl}?since=${encodeURIComponent(version)}`, {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin',
        });
        if (response.status === 200) {
          const draft = (await response.json()) as { version: string; page: PagePayload };
          version = draft.version;
          setPage(draft.page);
        }
      } catch {
        // A próxima consulta tenta de novo.
      }
      if (!stopped) {
        timer = window.setTimeout(() => void poll(), 1000);
      }
    }

    void poll();
    return () => {
      stopped = true;
      window.clearTimeout(timer);
    };
  }, [preview, boot?.draftUrl]);

  useEffect(() => {
    if (site && (site.locale ?? DEFAULT_LOCALE) === locale) {
      return;
    }

    void fetch(`/api/site?locale=${encodeURIComponent(locale)}`, { headers: { Accept: 'application/json' } })
      .then((response) => response.json() as Promise<SitePayload>)
      .then(setSite);
  }, [site, locale]);

  useEffect(() => {
    const slug = slugFromPath(path);
    const hreflang = locales.find((item) => item.code === locale)?.hreflang ?? locale;
    if (preview) {
      return;
    }
    if (booted.current === `${locale}:${slug}` && window.__SITE__?.page) {
      booted.current = null;
      applySeo(window.__SITE__.page.seo, hreflang);
      return;
    }

    const controller = new AbortController();
    setMissing(false);

    const query = `?locale=${encodeURIComponent(locale)}`;
    const endpoint = slug.startsWith('cases/')
      ? `/api/cases/${encodeURIComponent(slug.slice('cases/'.length))}${query}`
      : `/api/pages/${encodeURIComponent(slug)}${query}`;

    void fetch(endpoint, {
      headers: { Accept: 'application/json' },
      signal: controller.signal,
    })
      .then(async (response) => {
        if (!response.ok) {
          throw new Error(String(response.status));
        }
        return response.json() as Promise<PagePayload>;
      })
      .then((next) => {
        setPage(next);
        applySeo(next.seo, hreflang);
      })
      .catch((error: unknown) => {
        if (error instanceof DOMException && error.name === 'AbortError') {
          return;
        }
        setMissing(true);
      });

    return () => controller.abort();
  }, [location.pathname, locale, path]);

  useEffect(() => {
    if (!page || (preview && previewScrolled.current)) {
      return;
    }
    previewScrolled.current = preview;

    const target = location.hash ? document.querySelector(location.hash) : null;
    if (target) {
      target.scrollIntoView();
      return;
    }

    window.scrollTo(0, 0);
  }, [page, location.pathname, location.hash]);

  function onClick(event: MouseEvent) {
    const anchor = (event.target as HTMLElement).closest('a');
    if (!anchor || anchor.target === '_blank' || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }

    const href = anchor.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) {
      return;
    }

    const url = sameSite(href);
    if (!url || url.origin !== window.location.origin) {
      return;
    }

    const admin = site?.admin_path || '/gestao';
    if (url.pathname === admin || url.pathname.startsWith(`${admin}/`) || url.pathname.startsWith('/api/') || /\.[a-z0-9]+$/i.test(url.pathname)) {
      return;
    }

    event.preventDefault();
    if (preview) {
      return;
    }
    navigate(`${url.pathname}${url.search}${url.hash}`);
  }

  const route = slugFromPath(path);
  const synced = page != null && (preview || (page.slug === route && (page.locale ?? DEFAULT_LOCALE) === locale));

  return (
    <CmsCases.Provider value={site?.cases ?? null}>
    <div onClick={onClick}>
      <Header site={site} pathname={location.pathname} locale={locale} alternates={synced ? page?.alternates : undefined} />
      <main id="conteudo" tabIndex={-1}>
        {missing ? <NotFound whatsapp={site?.whatsapp_url ?? null} /> : null}
        {!missing && synced && page?.case ? (
          <CasePage item={page.case} whatsapp={site?.whatsapp_url ?? null}>
            <Sections sections={page.sections} form={site?.form ?? null} variant="case" />
          </CasePage>
        ) : null}
        {!missing && synced && page && !page.case ? <Sections sections={page.sections} form={site?.form ?? null} /> : null}
      </main>
      <Footer site={site} locale={locale} alternates={synced ? page?.alternates : undefined} />
      <WhatsAppFloat href={site?.whatsapp_url} />
      <LangFloat site={site} locale={locale} alternates={synced ? page?.alternates : undefined} />
    </div>
    </CmsCases.Provider>
  );
}

function NotFound({ whatsapp }: { whatsapp: string | null }) {
  return (
    <section className="hero not-found">
      <AgentLog />
      <div className="wrap">
        <h1 className="display is-inner">{t('notfound.title')}</h1>
        <div className="hero-foot">
          <p className="lead">{t('notfound.lead')}</p>
        </div>
        <div className="actions">
          {whatsapp ? <Cta href={whatsapp} label={t('notfound.whatsapp')} variant="primary" /> : null}
          <a className="pill line" href={localePath('/')}>{t('notfound.back')}</a>
        </div>
      </div>
    </section>
  );
}
