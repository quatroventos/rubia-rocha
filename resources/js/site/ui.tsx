import { Fragment, type PointerEvent, type ReactNode } from 'react';

export type Data = Record<string, unknown>;

export function text(data: Data, key: string): string {
  const value = data[key];
  return typeof value === 'string' ? value : '';
}

export function strings(data: Data, key: string): string[] {
  const value = data[key];
  if (!Array.isArray(value)) {
    return [];
  }

  return value
    .map((item) => (typeof item === 'string' ? item : typeof item === 'object' && item && 'value' in item ? String((item as { value: unknown }).value ?? '') : ''))
    .map((item) => item.trim())
    .filter(Boolean);
}

export function rows(data: Data, key = 'items'): Data[] {
  const value = data[key];
  if (!Array.isArray(value)) {
    return [];
  }

  return value.filter((item): item is Data => Boolean(item) && typeof item === 'object');
}

export function Rich({ value, className }: { value: string; className?: string }) {
  if (!value) {
    return null;
  }

  const classes = className ? `rich ${className}` : 'rich';

  if (value.includes('<')) {
    return <div className={classes} dangerouslySetInnerHTML={{ __html: value }} />;
  }

  return (
    <div className={classes}>
      <p>{value}</p>
    </div>
  );
}

// Trechos entre *asteriscos* no título viram destaque em lima.
export function Marked({ value }: { value: string }) {
  const parts = value.split(/\*([^*]+)\*/g);

  return (
    <>
      {parts.map((part, index) => (index % 2 === 1
        ? <em key={index} className="mark">{part}</em>
        : <Fragment key={index}>{part}</Fragment>))}
    </>
  );
}

export function plain(value: string): string {
  return value.replace(/\*([^*]+)\*/g, '$1');
}

export function isExternal(href: string): boolean {
  if (!/^https?:\/\//i.test(href)) {
    return false;
  }

  if (typeof window === 'undefined') {
    return true;
  }

  try {
    return new URL(href).origin !== window.location.origin;
  } catch {
    return true;
  }
}

export function LinkTo({ href, className, children, label, current, onClick }: { href: string; className?: string; children: ReactNode; label?: string; current?: boolean; onClick?: () => void }) {
  const external = isExternal(href);

  return (
    <a
      className={className}
      href={href}
      aria-label={label}
      aria-current={current ? 'page' : undefined}
      onClick={onClick}
      target={external ? '_blank' : undefined}
      rel={external ? 'noreferrer' : undefined}
    >
      {children}
    </a>
  );
}

export function isWhatsApp(href: string): boolean {
  return /(^|\/\/)(wa\.me|api\.whatsapp\.com)\b/i.test(href);
}

// O limão fica reservado ao CTA de WhatsApp; qualquer outro CTA principal sai em pílula sólida.
export function Cta({ href, label, variant }: { href: string; label: string; variant: 'primary' | 'line' }) {
  if (!href || !label) {
    return null;
  }

  const tone = variant === 'line' ? 'line' : isWhatsApp(href) ? 'lime' : 'solid';

  return (
    <LinkTo className={`pill ${tone}`} href={href}>
      {label}
      {tone === 'lime' ? <span className="arrow"><Arrow /></span> : null}
    </LinkTo>
  );
}

export function Arrow() {
  return (
    <svg className="icon-arrow" viewBox="0 0 16 16" aria-hidden="true">
      <path d="M4.5 11.5l7-7M5.5 4.5h6v6" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function revealOnView(el: HTMLElement | null) {
  if (!el) {
    return;
  }

  const observer = new IntersectionObserver(
    ([entry]) => {
      if (entry.isIntersecting) {
        el.classList.add('is-in');
        observer.disconnect();
      }
    },
    { threshold: 0.4 },
  );

  observer.observe(el);

  return () => observer.disconnect();
}

export function trackTilt(event: PointerEvent<HTMLElement>) {
  if (event.pointerType !== 'mouse') {
    return;
  }

  const el = event.currentTarget;
  const box = el.getBoundingClientRect();
  el.style.setProperty('--tilt-x', ((event.clientX - box.left) / box.width - 0.5).toFixed(3));
  el.style.setProperty('--tilt-y', ((event.clientY - box.top) / box.height - 0.5).toFixed(3));
}

export function resetTilt(event: PointerEvent<HTMLElement>) {
  event.currentTarget.style.removeProperty('--tilt-x');
  event.currentTarget.style.removeProperty('--tilt-y');
}

export function pad(index: number): string {
  return String(index + 1).padStart(2, '0');
}

export function WhatsAppIcon() {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2s-1 .2-3.4-.7a11.6 11.6 0 0 1-4.6-4.1c-.4-.6-1.2-1.6-1.2-3s.8-2.1 1-2.4.6-.4.8-.4h.6c.2 0 .4 0 .6.5l.9 2.1c.1.2.1.4 0 .6l-.4.6-.4.5c-.2.2-.3.3-.1.7a8 8 0 0 0 3.9 3.4c.4.2.6.1.8-.1l.8-1c.2-.3.4-.2.7-.1l2 1c.3.1.5.2.6.3s0 .7-.1 1.3Z" />
    </svg>
  );
}

export function Caret() {
  return (
    <svg className="caret" viewBox="0 0 10 10" aria-hidden="true">
      <path d="M1 3l4 4 4-4" fill="none" stroke="currentColor" strokeWidth="1.5" />
    </svg>
  );
}