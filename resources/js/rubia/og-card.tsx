import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import type { PagePayload, SitePayload } from './types';
import { list, text } from './ui';

const TITLE_MAX = 76;
const TITLE_MIN = 40;
const TITLE_BOX = 320;

export type OgBoot = { domain: string; origins?: string[] };

function cardTitle(page: PagePayload): string {
  for (const section of page.sections) {
    const title = text(section.data ?? {}, 'title');
    if (title && ['site.intro', 'site.about', 'site.folio-hero', 'site.talk', 'site.prose'].includes(section.type)) {
      return title;
    }
  }
  const seo = page.seo?.title ?? '';
  const cut = seo.lastIndexOf(' | ');

  return cut > 0 ? seo.slice(0, cut) : seo;
}

function cardPhoto(page: PagePayload): string {
  for (const section of page.sections) {
    const data = section.data ?? {};
    const image = text(data, 'image');
    if (image) {
      return image;
    }
    const first = list<{ image?: string }>(data, 'photos').find((photo) => photo.image);
    if (first?.image) {
      return first.image;
    }
  }

  return '';
}

// URLs absolutas do próprio site viram relativas: o Chrome da captura só alcança a aplicação pelo endereço interno.
function local(src: string, origins: string[]): string {
  const origin = origins.find((item) => item && src.startsWith(`${item}/`));

  return origin ? src.slice(origin.length) : src;
}

// Card 1200x630 capturado pelo Chrome do servidor. O título só é ajustado depois do evento og:fonts;
// data-ready sinaliza o fim do ajuste.
export function OgCard({ page, site, og }: { page: PagePayload; site: SitePayload | null; og: OgBoot }) {
  const title = useRef<HTMLHeadingElement>(null);
  const [armed, setArmed] = useState(false);
  const [size, setSize] = useState(TITLE_MAX);
  const [ready, setReady] = useState(false);
  const origins = og.origins ?? [];
  const heading = cardTitle(page);
  const photo = cardPhoto(page);
  const path = page.path === '/' ? '' : page.path;

  useEffect(() => {
    const arm = () => void document.fonts.ready.then(() => setArmed(true));
    if (document.documentElement.dataset.ogFonts) {
      arm();
      return;
    }
    window.addEventListener('og:fonts', arm, { once: true });

    return () => window.removeEventListener('og:fonts', arm);
  }, []);

  useLayoutEffect(() => {
    const node = title.current;
    if (!armed || !node) {
      return;
    }
    if (node.scrollHeight > TITLE_BOX && size > TITLE_MIN) {
      setSize((current) => Math.max(TITLE_MIN, current - 4));
      return;
    }
    setReady(true);
  }, [armed, size, heading]);

  return (
    <section className="og-card" data-ready={ready ? '' : undefined}>
      <div className="og-card-body">
        {site?.logo ? <img className="og-card-logo" src={local(site.logo, origins)} alt={site.name} /> : null}
        <h1 ref={title} className="og-card-title" style={{ fontSize: `${size}px` }}>{heading}</h1>
        <p className="og-card-url">
          {og.domain}
          <span>{path}</span>
        </p>
      </div>
      {photo ? <img className="og-card-photo" src={local(photo, origins)} alt="" /> : <div />}
    </section>
  );
}
