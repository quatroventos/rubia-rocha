import type { ReactNode } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { Shell } from './chrome';
import { Sections } from './sections';
import type { PagePayload, SitePayload } from './types';
import { Images, Whatsapp } from './ui';

export function Page({ page, site }: { page: PagePayload; site: SitePayload }): ReactNode {
  const whatsapp = site.whatsapp_url ?? '';

  return (
    <Images.Provider value={{ ...(site.images ?? {}), ...(page.images ?? {}) }}>
      <Whatsapp.Provider value={whatsapp}>
        <Shell site={site} path={page.path || '/'}>
          <Sections sections={page.sections} form={site.form ?? null} whatsapp={whatsapp} />
        </Shell>
      </Whatsapp.Provider>
    </Images.Provider>
  );
}

export function NotFound({ site }: { site: SitePayload }): ReactNode {
  const links = (site.menus.header ?? []).filter((item) => item.href !== '/' && !/^https?:\/\//i.test(item.href));

  return (
    <Whatsapp.Provider value={site.whatsapp_url ?? ''}>
      <Shell site={site} path="/404">
        <section className="lost" aria-labelledby="lost-title">
          <p className="kicker">Erro 404</p>
          <h1 id="lost-title">Essa página não existe mais.</h1>
          <p>O endereço mudou ou foi removido. Os ensaios e eventos continuam por aqui:</p>
          {links.length > 0 ? (
            <ul className="lost-links">
              {links.map((item) => (
                <li key={item.href}>
                  <a href={item.href}>{item.label}</a>
                </li>
              ))}
            </ul>
          ) : null}
          <a className="btn is-line" href="/">
            Voltar ao início <span aria-hidden="true">→</span>
          </a>
        </section>
      </Shell>
    </Whatsapp.Provider>
  );
}

export function renderSite(page: PagePayload, site: SitePayload): string {
  return renderToStaticMarkup(<Page page={page} site={site} />);
}

export function renderNotFound(site: SitePayload): string {
  return renderToStaticMarkup(<NotFound site={site} />);
}
