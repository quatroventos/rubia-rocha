import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { Shell } from './chrome';
import { pageBody } from './pages';

export const RUBIA_PATHS = [
  '',
  'quem-sou',
  'ensaios',
  'eventos',
  'blog',
  'blog/checklist-mini-wedding-curitiba',
  'contato',
] as const;

export function renderRubia(path: string): string {
  const body = pageBody(path);
  if (body === null) {
    return '';
  }

  return renderToStaticMarkup(createElement(Shell, { path }, body));
}

export type RubiaPath = (typeof RUBIA_PATHS)[number];

export function isRubiaPath(path: string): path is RubiaPath {
  return (RUBIA_PATHS as readonly string[]).includes(path);
}
