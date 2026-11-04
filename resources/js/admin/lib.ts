export type Abilities = {
  is_admin: boolean;
  is_root: boolean;
  audit: boolean;
  users: boolean;
  settings: boolean;
  cache: boolean;
  backup: boolean;
  tags: boolean;
  sitemap: boolean;
  redirects: boolean;
  seo: boolean;
  menu: boolean;
  certifications: boolean;
  form_options: boolean;
  pages: boolean;
  cases: boolean;
  messages: boolean;
  careers: boolean;
  messages_delete: boolean;
  careers_delete: boolean;
  impersonate: boolean;
};

export type TourProgress = Partial<Record<string, 'completed' | 'skipped'>>;

export type CmsUser = {
  id: number;
  name: string;
  email: string;
  role: string;
  abilities: Abilities;
  tour_progress?: TourProgress;
  recovery_codes_left?: number;
  impersonating?: boolean;
  impersonator?: {
    id: number;
    name: string;
    email: string;
  } | null;
};

export function getPath(obj: unknown, path: string): unknown {
  return path.split('.').reduce<unknown>((current, key) => {
    if (current == null || typeof current !== 'object') {
      return undefined;
    }

    return (current as Record<string, unknown>)[key];
  }, obj);
}

export function setPath(obj: Record<string, unknown>, path: string, value: unknown): Record<string, unknown> {
  const clone = structuredClone(obj ?? {});
  const keys = path.split('.');
  let current: Record<string, unknown> = clone;

  for (let i = 0; i < keys.length - 1; i += 1) {
    const key = keys[i];
    const next = current[key];
    if (next == null || typeof next !== 'object' || Array.isArray(next)) {
      current[key] = {};
    }
    current = current[key] as Record<string, unknown>;
  }

  current[keys[keys.length - 1]] = value;

  return clone;
}

export function initials(name: string): string {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('');
}

export function formatDate(value?: string | null): string {
  if (!value) {
    return '—';
  }

  return new Date(value).toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}
