import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { App } from './App';
import { DEFAULT_LOCALE, setLocale } from './i18n';
import { OgCard } from './og-card';

const root = document.getElementById('site-root');
const boot = window.__SITE__;

if (root && boot?.og) {
  setLocale(boot.page.locale ?? DEFAULT_LOCALE);
  createRoot(root).render(
    <StrictMode>
      <OgCard page={boot.page} site={boot.site} og={boot.og} />
    </StrictMode>,
  );
} else if (root) {
  createRoot(root).render(
    <StrictMode>
      <BrowserRouter>
        <App />
      </BrowserRouter>
    </StrictMode>,
  );
}
