// Carrossel de logos: páginas de duas linhas que deslizam sozinhas, com tamanho óptico
// (mesma área visual para qualquer proporção) e entrada em cascata a cada troca.
import { type CSSProperties, type PointerEvent, useEffect, useRef, useState } from 'react';
import { t } from './i18n';
import { type Data, rows, text } from './ui';

const ROWS = 2;
const INTERVAL = 3200;
const SWIPE = 40;

function pad(value: number): string {
  return String(value).padStart(2, '0');
}

function PlayPause({ playing }: { playing: boolean }) {
  return (
    <svg viewBox="0 0 16 16" aria-hidden="true">
      {playing
        ? <path d="M5.5 3.5v9M10.5 3.5v9" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
        : <path d="M5 3.5v9l7-4.5z" fill="currentColor" stroke="currentColor" strokeWidth="1.2" strokeLinejoin="round" />}
    </svg>
  );
}

function chunk<T>(items: T[], size: number): T[][] {
  const pages: T[][] = [];
  for (let i = 0; i < items.length; i += size) {
    pages.push(items.slice(i, i + size));
  }
  return pages.length ? pages : [[]];
}

export function LogoWall({ data }: { data: Data }) {
  const mono = Boolean(data.mono);
  const items = rows(data);
  const rootRef = useRef<HTMLDivElement>(null);
  const firstPageRef = useRef<HTMLUListElement>(null);
  const swipeRef = useRef<{ x: number; y: number } | null>(null);
  const [perPage, setPerPage] = useState((mono ? 3 : 5) * ROWS);
  const [page, setPage] = useState(0);
  const [tick, setTick] = useState(0);
  const [paused, setPaused] = useState(false);
  const [held, setHeld] = useState(false);
  const [visible, setVisible] = useState(false);
  const [reduced, setReduced] = useState(false);

  const pages = chunk(items, perPage);
  const count = pages.length;
  const current = Math.min(page, count - 1);
  const playing = count > 1 && !paused && !held && visible && !reduced;

  useEffect(() => {
    const list = firstPageRef.current;
    if (!list) {
      return;
    }
    const measure = () => {
      const columns = getComputedStyle(list).gridTemplateColumns.split(' ').filter(Boolean).length;
      setPerPage(Math.max(1, columns) * ROWS);
    };
    measure();
    const observer = new ResizeObserver(measure);
    observer.observe(list);
    return () => observer.disconnect();
  }, []);

  useEffect(() => {
    const root = rootRef.current;
    if (!root) {
      return;
    }
    const observer = new IntersectionObserver(([entry]) => setVisible(entry.isIntersecting && document.visibilityState === 'visible'), { threshold: 0.35 });
    observer.observe(root);
    const onVisibility = () => setVisible(document.visibilityState === 'visible' && root.getBoundingClientRect().top < window.innerHeight);
    document.addEventListener('visibilitychange', onVisibility);
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const onMotion = () => setReduced(motion.matches);
    onMotion();
    motion.addEventListener('change', onMotion);
    return () => {
      observer.disconnect();
      document.removeEventListener('visibilitychange', onVisibility);
      motion.removeEventListener('change', onMotion);
    };
  }, []);

  useEffect(() => {
    if (page > count - 1) {
      setPage(count - 1);
    }
  }, [page, count]);

  function go(next: number) {
    setPage(((next % count) + count) % count);
    setTick((value) => value + 1);
  }

  useEffect(() => {
    if (!playing) {
      return;
    }
    const timer = window.setTimeout(() => go(current + 1), INTERVAL);
    return () => window.clearTimeout(timer);
  }, [playing, current, tick]);

  function onPointerDown(event: PointerEvent<HTMLDivElement>) {
    if (event.pointerType !== 'mouse') {
      swipeRef.current = { x: event.clientX, y: event.clientY };
    }
  }

  function onPointerUp(event: PointerEvent<HTMLDivElement>) {
    const start = swipeRef.current;
    swipeRef.current = null;
    if (!start || count < 2) {
      return;
    }
    const dx = event.clientX - start.x;
    if (Math.abs(dx) > SWIPE && Math.abs(dx) > Math.abs(event.clientY - start.y)) {
      go(current + (dx < 0 ? 1 : -1));
    }
  }

  return (
    <div
      ref={rootRef}
      className={`logo-wall${count > 1 ? ' is-carousel' : ''}`}
      role={count > 1 ? 'region' : undefined}
      aria-roledescription={count > 1 ? t('logos.carousel') : undefined}
      aria-label={count > 1 ? t('logos.pager') : undefined}
      data-interval={INTERVAL}
      onKeyDown={(event) => {
        if (count < 2 || (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight')) {
          return;
        }
        event.preventDefault();
        go(current + (event.key === 'ArrowRight' ? 1 : -1));
      }}
      onMouseEnter={() => setHeld(true)}
      onMouseLeave={() => setHeld(false)}
      onFocus={() => setHeld(true)}
      onBlur={(event) => {
        if (!event.currentTarget.contains(event.relatedTarget as Node | null)) {
          setHeld(false);
        }
      }}
    >
      <div className="logo-viewport" onPointerDown={onPointerDown} onPointerUp={onPointerUp} onPointerCancel={() => { swipeRef.current = null; }}>
        <div className="logo-track" style={{ transform: `translateX(${-current * 100}%)` }}>
          {pages.map((group, pageIndex) => {
            const active = pageIndex === current;

            return (
              <ul
                key={pageIndex}
                ref={pageIndex === 0 ? firstPageRef : undefined}
                className={`${mono ? 'logos is-mono' : 'logos'} logo-page${active && tick > 0 ? ' is-entering' : ''}`}
                aria-hidden={active ? undefined : true}
                inert={!active}
                aria-label={count > 1 ? `${pad(pageIndex + 1)} / ${pad(count)}` : undefined}
              >
                {group.map((item, index) => {
                  const ratio = typeof item.ratio === 'number' && item.ratio > 0 ? item.ratio : null;

                  return (
                    <li key={`${text(item, 'name')}-${index}`} style={{ '--i': index } as CSSProperties}>
                      {text(item, 'image')
                        ? (
                          <img
                            src={text(item, 'image')}
                            alt={text(item, 'image_alt') || text(item, 'name') || 'Logo de cliente'}
                            loading="lazy"
                            draggable={false}
                            style={ratio ? ({ '--r': ratio } as CSSProperties) : undefined}
                          />
                        )
                        : <span className="logo-name">{text(item, 'name')}</span>}
                    </li>
                  );
                })}
              </ul>
            );
          })}
        </div>
      </div>

      {count > 1 ? (
        <div className="logo-pager">
          <div className="logo-dots" data-of={t('logos.of')}>
            {pages.map((_, index) => (
              <button
                key={index}
                type="button"
                className={`logo-dot${index === current ? ' is-current' : ''}`}
                aria-label={`${pad(index + 1)} ${t('logos.of')} ${pad(count)}`}
                aria-current={index === current ? 'true' : undefined}
                onClick={() => go(index)}
              >
                <span>
                  {index === current ? (
                    <span key={tick} className={playing ? 'is-running' : 'is-full'} style={{ animationDuration: `${INTERVAL}ms` }} />
                  ) : null}
                </span>
              </button>
            ))}
          </div>
          {!reduced ? (
            <button type="button" className="logo-pause" onClick={() => setPaused((value) => !value)} aria-label={paused ? t('logos.play') : t('logos.pause')} aria-pressed={paused} data-label-play={t('logos.play')} data-label-pause={t('logos.pause')}>
              <PlayPause playing={!paused} />
            </button>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
