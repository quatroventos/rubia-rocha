import { createElement, type ReactNode } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { CmsCases } from './case-showcase';
import { CasePage } from './cases';
import { CookieNotice, Footer, Header, LangFloat, WhatsAppFloat } from './chrome';
import { DEFAULT_LOCALE, setLocale } from './i18n';
import { Sections } from './sections';
import type { PagePayload, SitePayload } from './types';

function Shell({ page, site, body }: { page: PagePayload; site: SitePayload; body: ReactNode }) {
  const locale = page.locale ?? DEFAULT_LOCALE;

  return createElement(
    'div',
    null,
    createElement(Header, { site, pathname: page.path || '/', locale, alternates: page.alternates }),
    createElement('main', { id: 'conteudo', tabIndex: -1 }, body),
    createElement(Footer, { site, locale, alternates: page.alternates }),
    createElement(WhatsAppFloat, { href: site.whatsapp_url }),
    createElement(LangFloat, { site, locale, alternates: page.alternates }),
    createElement(CookieNotice, { site }),
  );
}

export function renderSite(page: PagePayload, site: SitePayload): string {
  setLocale(page.locale ?? DEFAULT_LOCALE);

  const body = page.case
    ? createElement(CasePage, { item: page.case, whatsapp: site.whatsapp_url ?? null }, createElement(Sections, { sections: page.sections, form: site.form ?? null, variant: 'case' }))
    : createElement(Sections, { sections: page.sections, form: site.form ?? null });

  return renderToStaticMarkup(createElement(CmsCases.Provider, { value: site.cases ?? null }, createElement(Shell, { page, site, body })));
}
