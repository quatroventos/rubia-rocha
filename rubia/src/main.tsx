import { StrictMode, useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, useLocation } from 'react-router-dom';
import { Shell } from '../../resources/js/rubia/chrome';
import { pageBody } from '../../resources/js/rubia/pages';
import '../../public/rubia/rubia.css';

const TITLES: Record<string, string> = {
  '': 'Rubia Rocha Fotografia',
  'quem-sou': 'Quem sou',
  ensaios: 'Ensaios fotográficos em Curitiba',
  eventos: 'Fotógrafa mini wedding em Curitiba',
  blog: 'Blog',
  'blog/checklist-mini-wedding-curitiba': 'Checklist do mini wedding em Curitiba',
  contato: 'Contato',
};

function Site() {
  const { pathname } = useLocation();
  const path = pathname.replace(/^\//, '').replace(/\/$/, '');
  const body = pageBody(path);

  useEffect(() => {
    document.title = TITLES[path] ?? 'Rubia Rocha Fotografia';
  }, [path]);

  if (body === null) {
    return (
      <Shell path="">
        <p className="intro">Essa página não existe.</p>
      </Shell>
    );
  }

  return <Shell path={path}>{body}</Shell>;
}

const root = document.getElementById('rubia-root');

if (root) {
  createRoot(root).render(
    <StrictMode>
      <BrowserRouter basename="/estudio">
        <Site />
      </BrowserRouter>
    </StrictMode>,
  );
}
