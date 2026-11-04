import type { ReactNode } from 'react';
import type { MenuNode, SitePayload } from './types';
import { Icon, Img, isExternal } from './ui';

function pathOf(href: string): string | null {
  if (isExternal(href) || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) {
    return null;
  }

  return `/${href.split(/[?#]/)[0].replace(/^\/+|\/+$/g, '')}`;
}

function isCurrent(path: string, item: MenuNode): boolean {
  const target = pathOf(item.href);
  if (target === null) {
    return false;
  }
  if (target === '/') {
    return path === '/';
  }

  return path === target || path.startsWith(`${target}/`);
}

function section(path: string): string {
  const first = path.replace(/^\/+/, '').split('/')[0];

  return first === '' ? 'home' : first;
}

function phoneLabel(site: SitePayload): string {
  return site.phone ? `Chamar no WhatsApp, ${site.phone}` : 'Chamar no WhatsApp';
}

export function Shell({ site, path, children }: { site: SitePayload; path: string; children: ReactNode }) {
  const header = site.menus.header ?? [];
  const gallery = site.footer_gallery ?? [];

  return (
    <>
      <a className="skip" href="#conteudo">Ir para o conteúdo</a>
      <div className="rr" data-page={section(path)}>
        <header className="bar">
          <a className="logo" href="/" aria-label={`${site.name}, início`}>
            <img src={site.logo} alt="" width="324" height="71" />
          </a>
          {header.length > 0 ? (
            <>
              <button className="nav-toggle" type="button" aria-expanded="false" aria-controls="menu">
                <span className="nav-toggle-lines" aria-hidden="true" />
                Menu
              </button>
              <nav className="menu" id="menu" aria-label="Principal">
                <ul>
                  {header.map((item) => (
                    <li key={`${item.label}-${item.href}`}>
                      <a
                        href={item.href}
                        aria-current={isCurrent(path, item) ? 'page' : undefined}
                        target={isExternal(item.href) ? '_blank' : undefined}
                        rel={isExternal(item.href) ? 'noopener' : undefined}
                      >
                        {item.label}
                      </a>
                    </li>
                  ))}
                </ul>
              </nav>
            </>
          ) : null}
        </header>
        <main id="conteudo" tabIndex={-1}>{children}</main>
      </div>
      <footer className="foot">
        <div className="foot-inner">
          {gallery.length > 0 ? (
            <>
              <p className="foot-label">{site.footer_gallery_title || 'Galerias'}</p>
              <ul className="ig-feed">
                {gallery.map((photo) => (
                  <li key={photo.image}>
                    {photo.href ? (
                      <a href={photo.href}>
                        <Img src={photo.image} alt={photo.alt} sizes="(max-width: 560px) 50vw, 200px" loading="lazy" />
                      </a>
                    ) : (
                      <Img src={photo.image} alt={photo.alt} sizes="(max-width: 560px) 50vw, 200px" loading="lazy" />
                    )}
                  </li>
                ))}
              </ul>
            </>
          ) : null}
          <div className="foot-meta">
            <p>© {new Date().getFullYear()} {site.name}</p>
            {site.instagram_url || site.facebook_url ? (
              <ul className="social">
                {site.instagram_url ? <li><a href={site.instagram_url} aria-label="Instagram" rel="noopener"><Icon name="instagram" /></a></li> : null}
                {site.facebook_url ? <li><a href={site.facebook_url} aria-label="Facebook" rel="noopener"><Icon name="facebook" /></a></li> : null}
              </ul>
            ) : null}
          </div>
          <a className="dev-credit" href="https://quatroventos.com.br" target="_blank" rel="noopener">
            <span>desenvolvido por</span>
            <img src="/rubia/img/logo-quatroventos.svg" alt="Quatro Ventos" width="95" height="15" loading="lazy" />
          </a>
        </div>
      </footer>
      {site.whatsapp_url ? (
        <a className="wa" href={site.whatsapp_url} target="_blank" rel="noopener noreferrer" aria-label={phoneLabel(site)}>
          <Icon name="whatsapp" />
        </a>
      ) : null}
    </>
  );
}
