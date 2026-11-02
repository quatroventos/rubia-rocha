import { useEffect, useRef, useState } from 'react';
import { Languages, Loader2 } from 'lucide-react';
import { api, firstError } from '../api';

export type TranslationState = {
  id: number | null;
  status: 'missing' | 'draft' | 'published' | string;
  stale: boolean;
  slug: string | null;
  translated_at: string | null;
};

export type TranslationConfig = {
  configured: boolean;
  default: string;
  locales: Array<{ code: string; name: string; short: string }>;
  translated: string[];
};

type JobStatus = {
  state: 'idle' | 'running' | 'done' | 'failed';
  message: string | null;
  translation: { id: number; slug: string; status: string; path: string } | null;
};

let configCache: Promise<TranslationConfig> | null = null;

export function useTranslationConfig(): TranslationConfig | null {
  const [config, setConfig] = useState<TranslationConfig | null>(null);

  useEffect(() => {
    configCache ??= api<TranslationConfig>('/translations/config');
    void configCache.then(setConfig).catch(() => {
      configCache = null;
    });
  }, []);

  return config;
}

export function translationLabel(state: TranslationState | undefined): string {
  if (!state || state.status === 'missing') {
    return 'Sem tradução';
  }
  if (state.stale) {
    return 'Desatualizada';
  }
  return state.status === 'published' ? 'Publicada' : 'Rascunho';
}

export function translationTone(state: TranslationState | undefined): string {
  if (!state || state.status === 'missing') {
    return 'is-missing';
  }
  if (state.stale) {
    return 'is-stale';
  }
  return state.status === 'published' ? 'is-published' : 'is-draft';
}

export function useTranslationJob(kind: 'page' | 'case' | 'menus', sourceId: number | null, onDone: (locale: string, translationId: number | null) => void) {
  const [running, setRunning] = useState<Record<string, boolean>>({});
  const [error, setError] = useState('');
  const timers = useRef<number[]>([]);

  useEffect(() => () => timers.current.forEach((timer) => window.clearTimeout(timer)), []);

  function poll(locale: string) {
    const query = new URLSearchParams({ kind, locale });
    if (sourceId !== null) {
      query.set('id', String(sourceId));
    }

    const timer = window.setTimeout(() => {
      void api<JobStatus>(`/translations/status?${query.toString()}`)
        .then((status) => {
          if (status.state === 'running') {
            poll(locale);
            return;
          }
          setRunning((current) => ({ ...current, [locale]: false }));
          if (status.state === 'failed') {
            setError(status.message || 'A tradução falhou.');
            return;
          }
          onDone(locale, status.translation?.id ?? null);
        })
        .catch((err) => {
          setRunning((current) => ({ ...current, [locale]: false }));
          setError(firstError(err));
        });
    }, 2500);
    timers.current.push(timer);
  }

  async function start(locale: string) {
    setError('');
    setRunning((current) => ({ ...current, [locale]: true }));
    const path = kind === 'page'
      ? `/pages/${sourceId}/translations/${locale}`
      : kind === 'case'
        ? `/cases/${sourceId}/translations/${locale}`
        : `/menu-items/translations/${locale}`;

    try {
      const status = await api<JobStatus>(path, { method: 'POST' });
      if (status.state === 'running') {
        poll(locale);
        return;
      }
      setRunning((current) => ({ ...current, [locale]: false }));
      if (status.state === 'failed') {
        setError(status.message || 'A tradução falhou.');
        return;
      }
      onDone(locale, status.translation?.id ?? null);
    } catch (err) {
      setRunning((current) => ({ ...current, [locale]: false }));
      setError(firstError(err));
    }
  }

  return { running, error, start, setError };
}

export function TranslationTabs({
  current,
  translations,
  config,
  running,
  onOpen,
  onTranslate,
}: {
  current: string;
  translations: Record<string, TranslationState>;
  config: TranslationConfig | null;
  running: Record<string, boolean>;
  onOpen: (locale: string) => void;
  onTranslate: (locale: string) => void;
}) {
  const locales = config?.locales ?? [];
  if (locales.length < 2) {
    return null;
  }

  return (
    <div className="lang-tabs" role="tablist" aria-label="Idiomas">
      {locales.map((locale) => {
        const isDefault = locale.code === config?.default;
        const state = translations[locale.code];
        const exists = isDefault || Boolean(state?.id);
        const busy = Boolean(running[locale.code]);

        if (!exists) {
          return (
            <button
              key={locale.code}
              type="button"
              className="lang-tab is-missing"
              disabled={busy || !config?.configured}
              title={config?.configured ? `Traduzir para ${locale.name} com IA` : 'Defina OPENAI_API_KEY no .env'}
              onClick={() => onTranslate(locale.code)}
            >
              <b>{locale.short}</b>
              <span>{busy ? <><Loader2 size={11} className="spin" /> Traduzindo</> : <><Languages size={11} /> Traduzir com IA</>}</span>
            </button>
          );
        }

        return (
          <button
            key={locale.code}
            type="button"
            role="tab"
            aria-selected={current === locale.code}
            className={`lang-tab ${isDefault ? 'is-published' : translationTone(state)}${current === locale.code ? ' is-on' : ''}`}
            onClick={() => onOpen(locale.code)}
          >
            <b>{locale.short}</b>
            <span>{busy ? <><Loader2 size={11} className="spin" /> Traduzindo</> : isDefault ? 'Original' : translationLabel(state)}</span>
          </button>
        );
      })}
    </div>
  );
}
