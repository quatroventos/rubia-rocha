import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { AgentLog, VISUALS } from './hero';
import type { PagePayload, SitePayload } from './types';
import { Marked, strings, text } from './ui';

const TITLE_MAX = 80;
const TITLE_MIN = 44;
const TITLE_BOX = 300;

export type OgBoot = { domain: string; origins?: string[] };

function heroTitle(page: PagePayload): string {
  const hero = page.sections.find((section) => section.type === 'site.hero')?.data;
  if (hero) {
    const title = text(hero, 'title').trim();
    const rotating = strings(hero, 'rotating');
    if (title && rotating.length) {
      return `${title} *${rotating[0]}*`;
    }
    if (title) {
      return title;
    }
  }

  const seo = page.seo?.title ?? '';
  const cut = seo.lastIndexOf(' | ');
  return cut > 0 ? seo.slice(0, cut) : seo;
}

// URLs absolutas do próprio site viram relativas: o Chrome da captura só alcança a aplicação pelo endereço interno.
function local(src: string, origins: string[]): string {
  const origin = origins.find((item) => item && src.startsWith(`${item}/`));
  return origin ? src.slice(origin.length) : src;
}

// Card 1200x630 capturado pelo Chrome do servidor. O título só é ajustado à caixa depois que a captura avisa que as
// fontes carregaram (evento og:fonts); data-ready sinaliza o fim do ajuste.
export function OgCard({ page, site, og }: { page: PagePayload; site: SitePayload | null; og: OgBoot }) {
  const title = useRef<HTMLHeadingElement>(null);
  const [armed, setArmed] = useState(false);
  const [size, setSize] = useState(TITLE_MAX);
  const [ready, setReady] = useState(false);
  const origins = og.origins ?? [];

  const item = page.case;
  const hero = page.sections.find((section) => section.type === 'site.hero')?.data;
  const visualKey = hero ? text(hero, 'visual') : '';
  const visual = !item && visualKey in VISUALS ? VISUALS[visualKey] : undefined;
  const heading = item ? item.title : heroTitle(page);
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
    <section className={`hero og-card${visual ? ' has-flow' : ''}${item?.cover ? ' has-cover' : ''}`} data-ready={ready ? '' : undefined}>
      {visual ? visual() : <AgentLog />}
      {item?.cover ? <img className="og-card-cover" src={local(item.cover.src, origins)} alt="" /> : null}
      <div className="og-card-body">
        {site?.logo ? <img className="og-card-logo" src={local(site.logo, origins)} alt={site.name} /> : null}
        <div className="og-card-copy">
          {item ? <p className="og-card-eyebrow">Case · {item.client}</p> : null}
          <h1 ref={title} className="og-card-title" style={{ fontSize: `${size}px` }}>
            <Marked value={heading} />
          </h1>
        </div>
        <p className="og-card-url">
          {og.domain}
          <span>{path}</span>
        </p>
      </div>
    </section>
  );
}
