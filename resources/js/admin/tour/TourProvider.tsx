import {
  createContext,
  createElement,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from 'react';
import { useLocation } from 'react-router-dom';
import { api } from '../api';
import { useAuth } from '../auth';
import type { CmsUser } from '../lib';
import { tourIdForPath } from './registry';
import { stepsFor } from './steps';
import { TourOverlay } from './TourOverlay';
import {
  isTourDone,
  TOUR_IDS,
  type TourActionMap,
  type TourContextValue,
  type TourId,
  type TourProgress,
  type TourStatus,
  type TourStep,
} from './types';

const TourContext = createContext<TourContextValue | null>(null);

function waitForTarget(target: string, timeoutMs = 4000): Promise<boolean> {
  return new Promise((resolve) => {
    const started = Date.now();

    const tick = () => {
      if (document.querySelector(`[data-tour="${target}"]`)) {
        resolve(true);
        return;
      }
      if (Date.now() - started >= timeoutMs) {
        resolve(false);
        return;
      }
      window.requestAnimationFrame(tick);
    };

    tick();
  });
}

async function resolveSteps(
  catalog: TourStep[],
  runAction: (name?: string) => Promise<void>,
): Promise<TourStep[]> {
  const resolved: TourStep[] = [];
  let prepared: string | undefined;

  for (let index = 0; index < catalog.length; index += 1) {
    const step = catalog[index];
    const next = catalog[index + 1];

    if (step.prepare && step.prepare !== prepared) {
      if (prepared) {
        const openStep = catalog.slice(0, index).reverse().find((item) => item.prepare === prepared && item.cleanup);
        if (openStep?.cleanup) {
          await runAction(openStep.cleanup);
        }
        prepared = undefined;
      }
      await runAction(step.prepare);
      prepared = step.prepare;
      await new Promise((r) => window.setTimeout(r, 60));
    }

    const found = await waitForTarget(step.target, step.optional ? 900 : 3500);

    const keepPrepared = Boolean(step.prepare && next?.prepare === step.prepare);
    if (step.cleanup && !keepPrepared) {
      await runAction(step.cleanup);
      if (prepared === step.prepare) {
        prepared = undefined;
      }
    }

    if (!found && step.optional) {
      continue;
    }

    if (found || !step.optional) {
      resolved.push(step);
    }
  }

  if (prepared) {
    const openStep = catalog.slice().reverse().find((item) => item.prepare === prepared && item.cleanup);
    if (openStep?.cleanup) {
      await runAction(openStep.cleanup);
    }
  }

  if (resolved.length > 0) {
    return resolved;
  }

  return catalog.filter((step) => !step.prepare && !step.optional);
}

export function TourProvider({ children }: { children: ReactNode }) {
  const { user, setUser, loading } = useAuth();
  const location = useLocation();
  const actionsRef = useRef<TourActionMap>({});
  const activeRef = useRef(false);
  const sessionBootRef = useRef<number | null>(null);
  const pendingAfterShellRef = useRef(false);

  const [tourId, setTourId] = useState<TourId | null>(null);
  const [steps, setSteps] = useState<TourStep[]>([]);
  const [stepIndex, setStepIndex] = useState(0);
  const [active, setActive] = useState(false);

  activeRef.current = active;

  const runAction = useCallback(async (name?: string) => {
    if (!name) {
      return;
    }
    await actionsRef.current[name]?.();
  }, []);

  const persist = useCallback(async (id: TourId, status: TourStatus) => {
    if (!user || user.impersonating) {
      return;
    }

    try {
      const payload = await api<{ user: CmsUser }>('/tour', {
        method: 'PATCH',
        body: JSON.stringify({ tour: id, status }),
      });
      setUser(payload.user);
    } catch {
      const progress: TourProgress = { ...(user.tour_progress ?? {}) };
      if (status === 'skipped') {
        for (const tour of TOUR_IDS) {
          progress[tour] = 'skipped';
        }
      } else {
        progress[id] = status;
      }
      setUser({ ...user, tour_progress: progress });
    }
  }, [setUser, user]);

  const startTourInternal = useCallback(async (id: TourId, force: boolean) => {
    if (!user || user.impersonating || activeRef.current) {
      return;
    }
    if (!force && isTourDone(user.tour_progress, id)) {
      return;
    }

    const catalog = stepsFor(id, user.abilities);
    const resolved = await resolveSteps(catalog, runAction);
    if (resolved.length === 0) {
      return;
    }

    const first = resolved[0];
    if (first.prepare) {
      await runAction(first.prepare);
      await waitForTarget(first.target, first.optional ? 900 : 3500);
    }

    setTourId(id);
    setSteps(resolved);
    setStepIndex(0);
    setActive(true);
  }, [runAction, user]);

  const closeTour = useCallback(async (id: TourId, status: TourStatus, current: TourStep | undefined) => {
    if (current?.cleanup) {
      await runAction(current.cleanup);
    }

    setActive(false);
    setTourId(null);
    setSteps([]);
    setStepIndex(0);

    if (status === 'skipped') {
      pendingAfterShellRef.current = false;
    }

    await persist(id, status);

    if (status === 'completed' && id === 'shell' && pendingAfterShellRef.current) {
      pendingAfterShellRef.current = false;
      const screenTour = tourIdForPath(location.pathname, user?.abilities);
      if (screenTour) {
        window.setTimeout(() => {
          void startTourInternal(screenTour, false);
        }, 160);
      }
    }
  }, [location.pathname, persist, runAction, startTourInternal, user?.abilities]);

  const startTour = useCallback((id: TourId, options?: { force?: boolean }) => {
    void startTourInternal(id, options?.force ?? true);
  }, [startTourInternal]);

  const next = useCallback(() => {
    if (!tourId) {
      return;
    }

    void (async () => {
      const current = steps[stepIndex];
      if (stepIndex >= steps.length - 1) {
        await closeTour(tourId, 'completed', current);
        return;
      }

      const upcoming = steps[stepIndex + 1];
      const keepPrepared = Boolean(current?.prepare && current.prepare === upcoming?.prepare);

      if (current?.cleanup && !keepPrepared) {
        await runAction(current.cleanup);
      }

      if (upcoming?.prepare) {
        if (!keepPrepared || !document.querySelector(`[data-tour="${upcoming.target}"]`)) {
          await runAction(upcoming.prepare);
        }
        await waitForTarget(upcoming.target, upcoming.optional ? 900 : 3500);
      }

      setStepIndex((value) => value + 1);
    })();
  }, [closeTour, runAction, stepIndex, steps, tourId]);

  const prev = useCallback(() => {
    if (stepIndex <= 0) {
      return;
    }

    void (async () => {
      const current = steps[stepIndex];
      const previous = steps[stepIndex - 1];
      const keepPrepared = Boolean(current?.prepare && current.prepare === previous?.prepare);

      if (current?.cleanup && !keepPrepared) {
        await runAction(current.cleanup);
      }

      if (previous?.prepare) {
        if (!keepPrepared || !document.querySelector(`[data-tour="${previous.target}"]`)) {
          await runAction(previous.prepare);
        }
        await waitForTarget(previous.target, previous.optional ? 900 : 3500);
      }

      setStepIndex((value) => value - 1);
    })();
  }, [runAction, stepIndex, steps]);

  const skip = useCallback(() => {
    if (!tourId) {
      return;
    }
    void closeTour(tourId, 'skipped', steps[stepIndex]);
  }, [closeTour, stepIndex, steps, tourId]);

  const registerActions = useCallback((actions: TourActionMap) => {
    Object.assign(actionsRef.current, actions);
    return () => {
      for (const key of Object.keys(actions)) {
        delete actionsRef.current[key];
      }
    };
  }, []);

  useEffect(() => {
    if (loading || !user || user.impersonating) {
      return;
    }
    if (sessionBootRef.current === user.id) {
      return;
    }
    sessionBootRef.current = user.id;

    const progress = user.tour_progress ?? {};
    if (!isTourDone(progress, 'shell')) {
      pendingAfterShellRef.current = true;
      window.setTimeout(() => {
        void startTourInternal('shell', false);
      }, 300);
      return;
    }

    const screenTour = tourIdForPath(location.pathname, user.abilities);
    if (screenTour && !isTourDone(progress, screenTour)) {
      window.setTimeout(() => {
        void startTourInternal(screenTour, false);
      }, 300);
    }
  }, [loading, location.pathname, startTourInternal, user]);

  useEffect(() => {
    if (loading || !user || user.impersonating || active) {
      return;
    }
    if (!isTourDone(user.tour_progress, 'shell')) {
      return;
    }

    const screenTour = tourIdForPath(location.pathname, user.abilities);
    if (screenTour && !isTourDone(user.tour_progress, screenTour)) {
      const timer = window.setTimeout(() => {
        void startTourInternal(screenTour, false);
      }, 360);
      return () => window.clearTimeout(timer);
    }
  }, [active, loading, location.pathname, startTourInternal, user]);

  const value = useMemo<TourContextValue>(() => ({
    active,
    tourId,
    stepIndex,
    step: steps[stepIndex] ?? null,
    steps,
    startTour,
    next,
    prev,
    skip,
    registerActions,
  }), [active, next, prev, registerActions, skip, startTour, stepIndex, steps, tourId]);

  return createElement(
    TourContext.Provider,
    { value },
    children,
    active && steps[stepIndex]
      ? createElement(TourOverlay, {
        step: steps[stepIndex],
        stepIndex,
        total: steps.length,
        onNext: next,
        onPrev: prev,
        onSkip: skip,
      })
      : null,
  );
}

export function useTour(): TourContextValue {
  const context = useContext(TourContext);
  if (!context) {
    throw new Error('useTour precisa do TourProvider');
  }
  return context;
}
