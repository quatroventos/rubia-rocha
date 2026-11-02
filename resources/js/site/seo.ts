import type { SeoPayload } from './types';

function upsertMeta(selector: string, create: () => HTMLElement, content: string | null): void {
  const current = document.head.querySelector(selector);
  if (!content) {
    current?.remove();
    return;
  }

  const node = current ?? create();
  if (node instanceof HTMLMetaElement) {
    node.content = content;
  }
  if (node instanceof HTMLLinkElement) {
    node.href = content;
  }
  if (!current) {
    document.head.appendChild(node);
  }
}

export function applySeo(seo: SeoPayload, lang?: string): void {
  document.title = seo.title;

  if (lang) {
    document.documentElement.lang = lang;
  }

  document.head.querySelectorAll('link[rel="alternate"][hreflang]').forEach((node) => node.remove());
  (seo.hreflang ?? []).forEach((item) => {
    const link = document.createElement('link');
    link.rel = 'alternate';
    link.hreflang = item.hreflang;
    link.href = item.href;
    document.head.appendChild(link);
  });

  upsertMeta('meta[property="og:locale"]', () => {
    const meta = document.createElement('meta');
    meta.setAttribute('property', 'og:locale');
    return meta;
  }, seo.og_locale);

  upsertMeta('meta[name="description"]', () => {
    const meta = document.createElement('meta');
    meta.name = 'description';
    return meta;
  }, seo.description);

  upsertMeta('meta[name="robots"]', () => {
    const meta = document.createElement('meta');
    meta.name = 'robots';
    return meta;
  }, seo.robots);

  upsertMeta('link[rel="canonical"]', () => {
    const link = document.createElement('link');
    link.rel = 'canonical';
    return link;
  }, seo.canonical);

  upsertMeta('meta[property="og:title"]', () => {
    const meta = document.createElement('meta');
    meta.setAttribute('property', 'og:title');
    return meta;
  }, seo.title);

  upsertMeta('meta[property="og:description"]', () => {
    const meta = document.createElement('meta');
    meta.setAttribute('property', 'og:description');
    return meta;
  }, seo.description);

  upsertMeta('meta[property="og:url"]', () => {
    const meta = document.createElement('meta');
    meta.setAttribute('property', 'og:url');
    return meta;
  }, seo.canonical);

  upsertMeta('meta[property="og:image"]', () => {
    const meta = document.createElement('meta');
    meta.setAttribute('property', 'og:image');
    return meta;
  }, seo.og_image);

  upsertMeta('meta[property="og:site_name"]', () => {
    const meta = document.createElement('meta');
    meta.setAttribute('property', 'og:site_name');
    return meta;
  }, seo.og_site_name);
}
