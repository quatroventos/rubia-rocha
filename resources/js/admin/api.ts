const admin = () => window.__ADMIN__;

function csrf(): string {
  return admin()?.csrf
    || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    || '';
}

export function apiPath(path: string): string {
  return `${admin().path}/api${path}`;
}

export async function api<T = unknown>(path: string, init: RequestInit = {}): Promise<T> {
  const headers = new Headers(init.headers);
  headers.set('Accept', 'application/json');
  headers.set('X-Requested-With', 'XMLHttpRequest');
  headers.set('X-CSRF-TOKEN', csrf());

  if (init.body && !(init.body instanceof FormData) && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json');
  }

  const response = await fetch(apiPath(path), {
    ...init,
    credentials: 'same-origin',
    headers,
  });

  if (response.status === 204) {
    return undefined as T;
  }

  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    const error = new Error(payload.message || 'Falha na requisição.') as Error & {
      status: number;
      errors?: Record<string, string[]>;
    };
    error.status = response.status;
    error.errors = payload.errors;
    throw error;
  }

  return payload as T;
}

export function firstError(err: unknown): string {
  if (err && typeof err === 'object' && 'errors' in err) {
    const errors = (err as { errors?: Record<string, string[]> }).errors;
    const first = errors ? Object.values(errors)[0]?.[0] : undefined;
    if (first) {
      return first;
    }
  }

  return err instanceof Error ? err.message : 'Algo deu errado.';
}
