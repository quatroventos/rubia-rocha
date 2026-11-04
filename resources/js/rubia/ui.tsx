import { createContext, useContext, type ImgHTMLAttributes, type ReactNode } from 'react';
import type { ImageMap, SectionData } from './types';

export const Images = createContext<ImageMap>({});

export const Whatsapp = createContext<string>('');

export function text(data: SectionData | undefined, key: string): string {
  const value = data?.[key];

  return typeof value === 'string' ? value : '';
}

export function flag(data: SectionData | undefined, key: string): boolean {
  return Boolean(data?.[key]);
}

export function list<T = SectionData>(data: SectionData | undefined, key: string): T[] {
  const value = data?.[key];

  return Array.isArray(value) ? value.filter((item) => item !== null && item !== undefined) as T[] : [];
}

export function strings(data: SectionData | undefined, key: string): string[] {
  return list<unknown>(data, key).filter((item): item is string => typeof item === 'string' && item !== '');
}

export function isExternal(href: string): boolean {
  return /^https?:\/\//i.test(href);
}

export function Rich({ html, className }: { html: string; className?: string }) {
  if (!html) {
    return null;
  }

  return <div className={className} dangerouslySetInnerHTML={{ __html: html }} />;
}

type ImgProps = Omit<ImgHTMLAttributes<HTMLImageElement>, 'src' | 'srcSet' | 'width' | 'height'> & { src: string };

export function Img({ src, sizes, ...rest }: ImgProps) {
  const meta = useContext(Images)[src];

  return (
    <img
      src={src}
      srcSet={meta?.srcset ?? undefined}
      sizes={meta?.srcset ? sizes : undefined}
      width={meta?.width}
      height={meta?.height}
      {...rest}
    />
  );
}

export function useWhatsapp(href: string): string {
  const whatsapp = useContext(Whatsapp);

  return href || whatsapp;
}

export function Kicker({ children }: { children: ReactNode }) {
  if (!children) {
    return null;
  }

  return <p className="kicker">{children}</p>;
}

export function Button({ href, tone, children }: { href: string; tone: 'line' | 'fill'; children: ReactNode }) {
  if (!href || !children) {
    return null;
  }

  const external = isExternal(href);

  return (
    <a className={`btn is-${tone}`} href={href} target={external ? '_blank' : undefined} rel={external ? 'noopener noreferrer' : undefined}>
      {children}<span aria-hidden="true">→</span>
    </a>
  );
}

export function Buttons({ data }: { data: SectionData }) {
  const primary = useWhatsapp(text(data, 'primary_href'));
  const secondary = text(data, 'secondary_href');
  const primaryLabel = text(data, 'primary_label');
  const secondaryLabel = text(data, 'secondary_label');

  if (!primaryLabel && !secondaryLabel) {
    return null;
  }

  return (
    <div className="actions">
      <Button href={primary} tone="fill">{primaryLabel}</Button>
      <Button href={secondary} tone="line">{secondaryLabel}</Button>
    </div>
  );
}

export function slugId(value: string, fallback: string): string {
  const slug = value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

  return slug || fallback;
}

export function Icon({ name }: { name: 'instagram' | 'facebook' | 'whatsapp' | 'person' | 'mail' | 'chat' | 'phone' }) {
  if (name === 'instagram') {
    return <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="4" /><circle cx="12" cy="12" r="3.5" /><circle cx="17.2" cy="6.8" r="0.8" fill="currentColor" stroke="none" /></svg>;
  }
  if (name === 'facebook') {
    return <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 8.5h2.5V5H14c-2.2 0-3.5 1.4-3.5 3.6V11H8v3.4h2.5V21H14v-6.6h2.4l.6-3.4h-3V9.2c0-.4.3-.7.7-.7z" /></svg>;
  }
  if (name === 'person') {
    return <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4" /><path d="M4.5 20.5c1.2-3.8 4-5.5 7.5-5.5s6.3 1.7 7.5 5.5" /></svg>;
  }
  if (name === 'mail') {
    return <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5.5" width="17" height="13" rx="2" /><path d="m4 7 8 6 8-6" /></svg>;
  }
  if (name === 'chat') {
    return <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4.5c4.7 0 8 3 8 6.8s-3.3 6.7-8 6.7c-1 0-2-.1-2.9-.4L5 19.5l1.2-3.6C4.8 14.7 4 13.1 4 11.3 4 7.5 7.3 4.5 12 4.5z" /></svg>;
  }
  if (name === 'phone') {
    return <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4h3l1.5 4-2 1.3a10 10 0 0 0 5.2 5.2l1.3-2 4 1.5v3a2 2 0 0 1-2 2A15 15 0 0 1 5 6a2 2 0 0 1 2-2z" /></svg>;
  }

  return <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4a8 8 0 0 0-6.9 12l-.7 3.2 3.3-.7A8 8 0 1 0 12 4zm4.4 11.3c-.2.5-1 1-1.4 1-.4.1-.8.2-2.6-.6-2.2-.9-3.6-3.1-3.7-3.2-.1-.2-.9-1.2-.9-2.3s.6-1.6.8-1.8c.2-.2.4-.3.6-.3h.4c.1 0 .3 0 .5.4.2.5.6 1.6.7 1.7.1.1.1.3 0 .4-.1.2-.2.3-.3.5l-.2.3c-.1.1-.2.3-.1.5.2.3.7 1.1 1.5 1.8.9.8 1.7 1 1.9 1.1.2.1.4.1.5-.1.1-.2.6-.7.7-.9.2-.2.3-.2.5-.1.2.1 1.4.7 1.6.8.2.1.4.2.4.3.1.2 0 .7-.2 1.1z" /></svg>;
}
