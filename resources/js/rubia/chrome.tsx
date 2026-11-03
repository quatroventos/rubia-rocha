import { useEffect, useLayoutEffect, useState, type ReactNode } from 'react';

export const BASE = '/estudio';

const WHATSAPP = '5541988658624';

export const WHATSAPP_URL = `https://wa.me/${WHATSAPP}`;

const FEED = [
  { group: 'ensaios', slug: 'familia', file: '01-2-fotografia-de-casal-curitiba1-960.webp', alt: 'Ensaio de família' },
  { group: 'ensaios', slug: 'casal', file: '01-ensaio-de-casal-curitiba6-960.webp', alt: 'Ensaio de casal' },
  { group: 'ensaios', slug: 'gestante', file: '01-fotografia-curitiba-ensaio-de-gestante-ata-felipe43-960.webp', alt: 'Ensaio de gestante' },
  { group: 'eventos', slug: 'festa-infantil', file: '01-7-fotografia-de-festa-infantil-curitiba22-960.webp', alt: 'Festa infantil' },
  { group: 'eventos', slug: 'mini-wedding', file: '01-mini-wedding-curitiba-capela-nossa-senhora-salette1-960.webp', alt: 'Mini wedding' },
  { group: 'ensaios', slug: 'feminino', file: '01-1-ensaio-feminino-ao-ar-livre-curitiba5-960.webp', alt: 'Ensaio feminino' },
];

const NAV = [
  { id: '', label: 'Home' },
  { id: 'quem-sou', label: 'Quem sou' },
  { id: 'ensaios', label: 'Ensaios' },
  { id: 'eventos', label: 'Eventos' },
  { id: 'blog', label: 'Blog' },
  { id: 'contato', label: 'Contato' },
];

function href(id: string): string {
  return id === '' ? BASE : `${BASE}/${id}`;
}

function current(path: string, id: string): boolean {
  if (id === 'blog' || id === 'ensaios' || id === 'eventos') {
    return path === id || path.startsWith(`${id}/`);
  }

  return path === id;
}

function prefersReducedMotion(): boolean {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

const ENTER = [
  '.logo',
  '.nav-toggle',
  '.menu a',
  '.hero-stage',
  '.dots',
  '.intro-photo',
  '.leaf',
  '.kicker',
  'h1',
  'h2',
  'h3',
  'h4',
  'h5',
  '.h6',
  '.lede',
  'p:not(.kicker)',
  '.btn',
  '.shot',
  '.trio img',
  '.fact > img',
  '.split > img',
  '.folio-photo',
  '.talk > img',
  '.post img',
  '.card',
  '.faq details',
  '.fields label',
  '.quotes-wall',
  'blockquote',
  'figcaption',
  '.ig-feed li',
  '.foot-meta',
  '.album-grid button',
  '.album-count',
  '.wa',
].join(',');

function canEnter(node: HTMLElement): boolean {
  if (node.classList.contains('visually-hidden') || node.closest('.hp, .lightbox, .quote-card')) {
    return false;
  }

  return true;
}

function mountEnter(root: ParentNode): () => void {
  if (prefersReducedMotion() || !('IntersectionObserver' in window)) {
    return () => undefined;
  }

  const seen = new Set<HTMLElement>();
  const observer = new IntersectionObserver((entries) => {
    const visible = entries
      .filter((entry) => entry.isIntersecting)
      .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top || a.boundingClientRect.left - b.boundingClientRect.left);

    visible.forEach((entry, index) => {
      const node = entry.target as HTMLElement;
      node.style.animationDelay = `${Math.min(index, 7) * 70}ms`;
      node.classList.add('is-in');
      observer.unobserve(node);
    });
  }, { threshold: 0 });

  function register(node: HTMLElement) {
    if (seen.has(node) || !node.matches(ENTER) || !canEnter(node) || node.parentElement?.closest(ENTER)) {
      return;
    }
    seen.add(node);
    node.classList.add('enter');
    observer.observe(node);
  }

  function scan(scope: ParentNode) {
    if (scope instanceof HTMLElement) {
      register(scope);
    }
    scope.querySelectorAll<HTMLElement>(ENTER).forEach(register);
  }

  scan(root);
  window.requestAnimationFrame(() => {
    seen.forEach((node) => {
      if (node.classList.contains('is-in')) {
        return;
      }
      const rect = node.getBoundingClientRect();
      if (rect.bottom > 0 && rect.top < window.innerHeight) {
        node.classList.add('is-in');
        observer.unobserve(node);
      }
    });
  });

  const changes = new MutationObserver((records) => {
    records.forEach((record) => {
      record.addedNodes.forEach((node) => {
        if (node instanceof HTMLElement) {
          scan(node);
        }
      });
    });
  });
  if (root instanceof Node) {
    changes.observe(root, { childList: true, subtree: true });
  }

  return () => {
    observer.disconnect();
    changes.disconnect();
    seen.forEach((node) => {
      node.classList.remove('enter', 'is-in');
      node.style.animationDelay = '';
    });
  };
}

export function Shell({ path, children }: { path: string; children: ReactNode }) {
  const year = 2026;
  const [open, setOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);

  useEffect(() => {
    function onScroll() {
      setScrolled((current) => (current ? window.scrollY > 4 : window.scrollY > 32));
    }
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });

    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  useEffect(() => {
    if (!open) {
      return;
    }
    function onKey(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        setOpen(false);
      }
    }
    window.addEventListener('keydown', onKey);

    return () => window.removeEventListener('keydown', onKey);
  }, [open]);

  useLayoutEffect(() => {
    const main = document.getElementById('conteudo');
    if (!main) {
      return;
    }

    return mountEnter(main);
  }, [path]);

  useLayoutEffect(() => {
    const stops = ['.bar', '.foot', '.wa']
      .map((selector) => document.querySelector(selector))
      .filter((node): node is Element => node !== null)
      .map((node) => mountEnter(node));

    return () => stops.forEach((stop) => stop());
  }, []);

  return (
    <>
    <a className="skip" href="#conteudo">Ir para o conteúdo</a>
    <div className="rr" data-page={path === '' ? 'home' : path.split('/')[0]}>
      <header className={scrolled ? 'bar is-scrolled' : 'bar'}>
        <a className="logo" href={href('')} aria-label="Rubia Rocha Fotografia, início">
          <img src="/rubia/img/logo-rubia-foto.svg" alt="" width="324" height="71" />
        </a>
        <button
          className="nav-toggle"
          type="button"
          aria-expanded={open}
          aria-controls="menu"
          onClick={() => setOpen((value) => !value)}
        >
          <span className="nav-toggle-lines" aria-hidden="true" />
          Menu
        </button>
        <nav className={open ? 'menu is-open' : 'menu'} id="menu" aria-label="Principal">
          <ul>
            {NAV.map((item) => (
              <li key={item.id}>
                <a href={href(item.id)} aria-current={current(path, item.id) ? 'page' : undefined}>{item.label}</a>
              </li>
            ))}
          </ul>
        </nav>
      </header>
      <main id="conteudo" tabIndex={-1}>{children}</main>
    </div>
    <footer className="foot">
      <div className="foot-inner">
        <ul className="ig-feed">
          {FEED.map((photo) => (
            <li key={photo.file}>
              <a href={href(`${photo.group}/${photo.slug}`)}>
                <img src={`/rubia/img/albuns/${photo.slug}/${photo.file}`} alt={photo.alt} width="960" height="960" loading="lazy" />
              </a>
            </li>
          ))}
        </ul>
        <div className="foot-meta">
          <p>© {year} Rubia Rocha Fotografia</p>
          <ul className="social">
            <li><a href="https://www.instagram.com/rubiarochafotografia/" aria-label="Instagram" rel="noopener"><Icon name="instagram" /></a></li>
          </ul>
        </div>
      </div>
    </footer>
    <a className="wa" href={`https://wa.me/${WHATSAPP}`} target="_blank" rel="noopener noreferrer" aria-label="Chamar no WhatsApp, 41 98865-8624">
      <Icon name="whatsapp" />
    </a>
    </>
  );
}

export function Kicker({ children }: { children: ReactNode }) {
  return <p className="kicker">{children}</p>;
}

export function Button({ href: to, tone, children }: { href: string; tone: 'line' | 'fill'; children: ReactNode }) {
  const external = to.startsWith('http');

  return (
    <a className={`btn is-${tone}`} href={to} target={external ? '_blank' : undefined} rel={external ? 'noopener noreferrer' : undefined}>
      {children}<span aria-hidden="true">→</span>
    </a>
  );
}

function Icon({ name }: { name: 'instagram' | 'whatsapp' }) {
  if (name === 'instagram') {
    return <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="4" /><circle cx="12" cy="12" r="3.5" /><circle cx="17.2" cy="6.8" r="0.8" fill="currentColor" stroke="none" /></svg>;
  }

  return <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4a8 8 0 0 0-6.9 12l-.7 3.2 3.3-.7A8 8 0 1 0 12 4zm4.4 11.3c-.2.5-1 1-1.4 1-.4.1-.8.2-2.6-.6-2.2-.9-3.6-3.1-3.7-3.2-.1-.2-.9-1.2-.9-2.3s.6-1.6.8-1.8c.2-.2.4-.3.6-.3h.4c.1 0 .3 0 .5.4.2.5.6 1.6.7 1.7.1.1.1.3 0 .4-.1.2-.2.3-.3.5l-.2.3c-.1.1-.2.3-.1.5.2.3.7 1.1 1.5 1.8.9.8 1.7 1 1.9 1.1.2.1.4.1.5-.1.1-.2.6-.7.7-.9.2-.2.3-.2.5-.1.2.1 1.4.7 1.6.8.2.1.4.2.4.3.1.2 0 .7-.2 1.1z" /></svg>;
}
