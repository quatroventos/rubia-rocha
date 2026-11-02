import en from './en.json';
import es from './es.json';
import pt from './pt-BR.json';

export type DictKey = keyof typeof pt;

export type LocaleInfo = {
  code: string;
  name: string;
  short: string;
  hreflang: string;
  prefix: string;
};

export const DEFAULT_LOCALE = 'pt-BR';

export const LOCALES: LocaleInfo[] = [
  { code: 'pt-BR', name: 'Português', short: 'PT', hreflang: 'pt-BR', prefix: '' },
  { code: 'en', name: 'English', short: 'EN', hreflang: 'en', prefix: 'en' },
  { code: 'es', name: 'Español', short: 'ES', hreflang: 'es', prefix: 'es' },
];

const DICTS: Record<string, Partial<Record<DictKey, string>>> = { 'pt-BR': pt, en, es };

let current = DEFAULT_LOCALE;

export function setLocale(locale: string) {
  current = DICTS[locale] ? locale : DEFAULT_LOCALE;
}

export function getLocale(): string {
  return current;
}

export function t(key: DictKey, vars?: Record<string, string | number>): string {
  const value = DICTS[current]?.[key] ?? pt[key];

  return vars ? value.replace(/\{(\w+)\}/g, (match, name: string) => (name in vars ? String(vars[name]) : match)) : value;
}

export function splitLocalePath(pathname: string, locales: LocaleInfo[] = LOCALES): { locale: string; path: string } {
  const segments = pathname.replace(/^\/+/, '').split('/');
  const match = locales.find((item) => item.prefix !== '' && item.prefix === segments[0]);

  if (!match) {
    return { locale: DEFAULT_LOCALE, path: pathname || '/' };
  }

  const rest = segments.slice(1).join('/');
  return { locale: match.code, path: `/${rest}` };
}

export function localePath(path: string, locale = current, locales: LocaleInfo[] = LOCALES): string {
  const prefix = locales.find((item) => item.code === locale)?.prefix ?? '';
  if (!prefix || !path.startsWith('/') || path.startsWith('//')) {
    return path;
  }

  return path === '/' ? `/${prefix}` : `/${prefix}${path}`;
}

export function rememberLocale(locale: string, cookie = 'qv_locale') {
  document.cookie = `${cookie}=${encodeURIComponent(locale)}; path=/; max-age=31536000; samesite=lax`;
}
