import type { Abilities } from '../lib';
import { hasAbility, type TourId } from './types';

type RouteTour = {
  tour: TourId;
  ability?: keyof Abilities;
  match: (pathname: string) => boolean;
};

const routes: RouteTour[] = [
  { tour: 'inbox', ability: 'messages', match: (path) => path.startsWith('/mensagens') },
  { tour: 'page-editor', ability: 'pages', match: (path) => path.startsWith('/paginas/') },
  { tour: 'pages', ability: 'pages', match: (path) => path === '/paginas' },
  { tour: 'site', ability: 'settings', match: (path) => path === '/site' },
  { tour: 'seo', ability: 'seo', match: (path) => path === '/seo' },
  { tour: 'menu', ability: 'menu', match: (path) => path === '/menu' },
  { tour: 'certifications', ability: 'certifications', match: (path) => path === '/certificacoes' },
  { tour: 'form-editor', ability: 'form_options', match: (path) => path.startsWith('/formularios/') },
  { tour: 'forms', ability: 'form_options', match: (path) => path === '/formularios' },
  { tour: 'users', ability: 'users', match: (path) => path === '/usuarios' },
  { tour: 'audit', ability: 'audit', match: (path) => path === '/log' },
  { tour: 'cache', ability: 'cache', match: (path) => path === '/cache' },
  { tour: 'images', ability: 'cache', match: (path) => path === '/imagens' },
  { tour: 'backup', ability: 'backup', match: (path) => path === '/backup' },
  { tour: 'tags', ability: 'tags', match: (path) => path === '/tags' },
  { tour: 'profile', match: (path) => path === '/perfil' },
  { tour: 'dashboard', ability: 'pages', match: (path) => path === '/' },
];

export function tourIdForPath(pathname: string, abilities?: Abilities): TourId | null {
  const route = routes.find((item) => item.match(pathname));
  if (!route || !hasAbility(abilities, route.ability)) {
    return null;
  }

  return route.tour;
}
