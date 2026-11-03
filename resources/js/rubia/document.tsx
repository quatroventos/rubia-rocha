import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { Shell } from './chrome';
import { pageBody } from './pages';

export const RUBIA_PATHS = [
  '',
  'quem-sou',
  'ensaios',
  'ensaios/gestante',
  'ensaios/bebes',
  'ensaios/familia',
  'ensaios/casal',
  'ensaios/feminino',
  'ensaios/formatura',
  'eventos',
  'eventos/festa-infantil',
  'eventos/mini-wedding',
  'eventos/corporativo',
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
