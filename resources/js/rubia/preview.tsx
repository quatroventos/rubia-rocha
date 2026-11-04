import { useEffect, useLayoutEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { mountBehaviors } from './behaviors';
import { Page } from './document';
import { OgCard, type OgBoot } from './og-card';
import type { PagePayload, SitePayload } from './types';

type Boot = {
  page: PagePayload;
  site: SitePayload;
  preview?: boolean;
  draftUrl?: string | null;
  og?: OgBoot | null;
};

declare global {
  interface Window {
    __SITE__?: Boot;
  }
}

function Preview({ boot }: { boot: Boot }) {
  const [page, setPage] = useState(boot.page);

  useEffect(() => {
    const draftUrl = boot.draftUrl;
    if (!draftUrl) {
      return;
    }

    let version = '';
    let stopped = false;
    let timer = 0;

    async function poll() {
      try {
        const response = await fetch(`${draftUrl}?since=${encodeURIComponent(version)}`, {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin',
        });
        if (response.status === 200) {
          const draft = (await response.json()) as { version: string; page: PagePayload };
          version = draft.version;
          setPage(draft.page);
        }
      } catch {
        // A próxima consulta tenta de novo.
      }
      if (!stopped) {
        timer = window.setTimeout(() => void poll(), 1000);
      }
    }

    void poll();

    return () => {
      stopped = true;
      window.clearTimeout(timer);
    };
  }, [boot.draftUrl]);

  useLayoutEffect(() => {
    if (page.seo?.title) {
      document.title = page.seo.title;
    }

    return mountBehaviors(document.body, { enter: false });
  }, [page]);

  return <Page page={page} site={boot.site} />;
}

const root = document.getElementById('site-root');
const boot = window.__SITE__;

if (root && boot?.og) {
  createRoot(root).render(<OgCard page={boot.page} site={boot.site} og={boot.og} />);
} else if (root && boot) {
  createRoot(root).render(<Preview boot={boot} />);
}
