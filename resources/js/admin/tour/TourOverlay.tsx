import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import type { TourPlacement, TourStep } from './types';

type Spot = {
  top: number;
  left: number;
  width: number;
  height: number;
};

function measure(target: string): Spot | null {
  const node = document.querySelector<HTMLElement>(`[data-tour="${target}"]`);
  if (!node) {
    return null;
  }

  const rect = node.getBoundingClientRect();
  if (rect.width < 1 && rect.height < 1) {
    return null;
  }

  return {
    top: rect.top,
    left: rect.left,
    width: rect.width,
    height: rect.height,
  };
}

function cardPosition(spot: Spot, placement: TourPlacement | undefined, card: DOMRect | null): { top: number; left: number } {
  const gap = 14;
  const width = card?.width ?? 360;
  const height = card?.height ?? 220;
  const pad = 16;

  let top = spot.top + spot.height + gap;
  let left = spot.left;

  if (placement === 'right') {
    top = spot.top;
    left = spot.left + spot.width + gap;
  } else if (placement === 'left') {
    top = spot.top;
    left = spot.left - width - gap;
  } else if (placement === 'top') {
    top = spot.top - height - gap;
    left = spot.left;
  } else if (spot.top + spot.height + gap + height > window.innerHeight - pad) {
    top = spot.top - height - gap;
  }

  if (top < pad) {
    top = pad;
  }
  if (top + height > window.innerHeight - pad) {
    top = Math.max(pad, window.innerHeight - height - pad);
  }
  if (left < pad) {
    left = pad;
  }
  if (left + width > window.innerWidth - pad) {
    left = Math.max(pad, window.innerWidth - width - pad);
  }

  return { top, left };
}

export function TourOverlay({
  step,
  stepIndex,
  total,
  onNext,
  onPrev,
  onSkip,
}: {
  step: TourStep;
  stepIndex: number;
  total: number;
  onNext: () => void;
  onPrev: () => void;
  onSkip: () => void;
}) {
  const cardRef = useRef<HTMLDivElement>(null);
  const [spot, setSpot] = useState<Spot | null>(null);
  const [cardBox, setCardBox] = useState<DOMRect | null>(null);

  useLayoutEffect(() => {
    const update = () => {
      const next = measure(step.target);
      setSpot(next);
      if (cardRef.current) {
        setCardBox(cardRef.current.getBoundingClientRect());
      }
    };

    update();
    const node = document.querySelector(`[data-tour="${step.target}"]`);
    node?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    const raf = window.requestAnimationFrame(update);

    window.addEventListener('resize', update);
    window.addEventListener('scroll', update, true);
    return () => {
      window.cancelAnimationFrame(raf);
      window.removeEventListener('resize', update);
      window.removeEventListener('scroll', update, true);
    };
  }, [step.target]);

  useEffect(() => {
    const card = cardRef.current;
    const previous = document.activeElement as HTMLElement | null;
    card?.focus();

    function onKey(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        event.preventDefault();
        onSkip();
        return;
      }
      if (event.key === 'ArrowRight' || event.key === 'Enter') {
        event.preventDefault();
        onNext();
        return;
      }
      if (event.key === 'ArrowLeft') {
        event.preventDefault();
        onPrev();
      }
    }

    function trap(event: KeyboardEvent) {
      if (event.key !== 'Tab' || !card) {
        return;
      }
      const focusable = card.querySelectorAll<HTMLElement>('button');
      if (focusable.length === 0) {
        return;
      }
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    }

    window.addEventListener('keydown', onKey);
    window.addEventListener('keydown', trap);
    return () => {
      window.removeEventListener('keydown', onKey);
      window.removeEventListener('keydown', trap);
      previous?.focus?.();
    };
  }, [onNext, onPrev, onSkip, step.target]);

  const last = stepIndex === total - 1;
  const position = spot ? cardPosition(spot, step.placement, cardBox) : { top: 96, left: 96 };

  return (
    <div className="tour-root" role="dialog" aria-modal="true" aria-labelledby="tour-title">
      {spot ? (
        <div
          className="tour-spot"
          style={{
            top: spot.top - 8,
            left: spot.left - 8,
            width: spot.width + 16,
            height: spot.height + 16,
          }}
        />
      ) : (
        <div className="tour-mask" />
      )}
      <div
        className="tour-card"
        ref={cardRef}
        tabIndex={-1}
        style={{ top: position.top, left: position.left }}
      >
        <span className="tour-eyebrow">{step.eyebrow ?? 'Como operar'}</span>
        <h2 id="tour-title">{step.title}</h2>
        <p>{step.body}</p>
        <div className="tour-foot">
          <span className="tour-progress">{stepIndex + 1} de {total}</span>
          <div className="tour-actions">
            <button className="btn btn-text" type="button" onClick={onSkip}>Pular tour</button>
            <button className="btn btn-secondary" type="button" disabled={stepIndex === 0} onClick={onPrev}>Anterior</button>
            <button className="btn btn-primary" type="button" onClick={onNext}>
              {last ? 'Concluir' : 'Próximo'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
