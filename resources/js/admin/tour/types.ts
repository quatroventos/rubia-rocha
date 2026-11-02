import type { Abilities } from '../lib';

export const TOUR_IDS = [
  'shell',
  'dashboard',
  'inbox',
  'pages',
  'page-editor',
  'menu',
  'certifications',
  'users',
  'site',
  'seo',
  'forms',
  'form-editor',
  'cache',
  'images',
  'backup',
  'audit',
  'tags',
  'profile',
] as const;

export type TourId = (typeof TOUR_IDS)[number];

export type TourStatus = 'completed' | 'skipped';

export type TourProgress = Partial<Record<TourId, TourStatus>>;

export type TourPlacement = 'top' | 'bottom' | 'left' | 'right';

export type TourStep = {
  target: string;
  eyebrow?: string;
  title: string;
  body: string;
  prepare?: string;
  cleanup?: string;
  optional?: boolean;
  placement?: TourPlacement;
};

export type TourActionMap = Record<string, () => void | Promise<void>>;

export type TourContextValue = {
  active: boolean;
  tourId: TourId | null;
  stepIndex: number;
  step: TourStep | null;
  steps: TourStep[];
  startTour: (id: TourId, options?: { force?: boolean }) => void;
  next: () => void;
  prev: () => void;
  skip: () => void;
  registerActions: (actions: TourActionMap) => () => void;
};

export function isTourId(value: string): value is TourId {
  return (TOUR_IDS as readonly string[]).includes(value);
}

export function isTourDone(progress: TourProgress | undefined, id: TourId): boolean {
  const status = progress?.[id];
  return status === 'completed' || status === 'skipped';
}

export function hasAbility(abilities: Abilities | undefined, key?: keyof Abilities): boolean {
  if (!key) {
    return true;
  }

  return Boolean(abilities?.[key]);
}
