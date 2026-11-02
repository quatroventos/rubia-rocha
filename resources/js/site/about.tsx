// Blocos interativos de /sobre: linha do tempo com o ano preso no centro da tela e a órbita do Método.
import { Fragment, useEffect, useRef, type CSSProperties } from 'react';
import { revealOnView, Rich } from './ui';

export type HistoryItem = { year: string; title: string; text: string };

export type MethodStep = { title: string; text: string };

const ORBIT_TILT = 62;
const ORBIT_BASE_SPEED = 30;
const ORBIT_NODE_ANGLES = [315, 45, 135, 225];

const GALAXY_NODES = ORBIT_NODE_ANGLES.map((deg) => {
  const rad = (deg * Math.PI) / 180;
  return { x: 200 + Math.sin(rad) * 150, y: 200 - Math.cos(rad) * 150 };
});

// O ano grande fica preso no centro da tela e mostra o ano do marco cujo centro está alinhado com ele; entre dois marcos, interpola com easing para assentar em cada ano.
export function HistoryTimeline({ items }: { items: HistoryItem[] }) {
  const yearRef = useRef<HTMLDivElement>(null);
  const listRef = useRef<HTMLOListElement>(null);

  useEffect(() => {
    const year = yearRef.current;
    const list = listRef.current;
    if (!year || !list) return;
    const nodes = Array.from(list.children) as HTMLElement[];
    const years = items.map((item) => Number(item.year));
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    let frame = 0;

    const update = () => {
      frame = 0;
      const rect = year.getBoundingClientRect();
      const anchor = rect.top + rect.height / 2;
      const centers = nodes.map((node) => {
        const box = node.getBoundingClientRect();
        return box.top + box.height / 2;
      });
      let value = years[0];
      if (anchor >= centers[centers.length - 1]) {
        value = years[years.length - 1];
      } else if (anchor > centers[0]) {
        const k = centers.findIndex((center, i) => anchor >= center && anchor < centers[i + 1]);
        const t = (anchor - centers[k]) / (centers[k + 1] - centers[k]);
        const eased = reduced.matches ? Math.round(t) : t * t * (3 - 2 * t);
        value = Math.round(years[k] + (years[k + 1] - years[k]) * eased);
      }
      year.textContent = String(value);
    };

    const schedule = () => {
      if (!frame) frame = requestAnimationFrame(update);
    };

    update();
    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule);
    return () => {
      window.removeEventListener('scroll', schedule);
      window.removeEventListener('resize', schedule);
      cancelAnimationFrame(frame);
    };
  }, [items]);

  return (
    <div className="yc">
      <div className="yc-year" ref={yearRef} aria-hidden="true">{items[0]?.year}</div>
      <ol className="yc-list" ref={listRef}>
        {items.map((item) => (
          <li key={item.year}>
            <span className="mono">{item.year}</span>
            <h3 className="yc-title">{item.title}</h3>
            <Rich value={item.text} />
          </li>
        ))}
      </ol>
    </div>
  );
}

// Órbita do Método com física: a seta gira sozinha a 30°/s, pode ser agarrada, arrastada e lançada (inércia com atrito que volta à velocidade base) e o plano inclina com mola seguindo o ponteiro.
export function MethodOrbit({ steps, center }: { steps: MethodStep[]; center: string }) {
  const rootRef = useRef<HTMLDivElement>(null);
  const ringRef = useRef<HTMLDivElement>(null);
  const gxRef = useRef<HTMLDivElement>(null);
  const orbitRef = useRef<SVGGElement>(null);
  const tailRef = useRef<SVGCircleElement>(null);
  const faceRefs = useRef<(HTMLSpanElement | null)[]>([]);
  const stepRefs = useRef<(HTMLLIElement | null)[]>([]);

  useEffect(() => {
    const root = rootRef.current;
    const ring = ringRef.current;
    const gx = gxRef.current;
    const orbit = orbitRef.current;
    const tail = tailRef.current;
    if (!root || !ring || !gx || !orbit || !tail) return;
    const cleanupReveal = revealOnView(root);
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return cleanupReveal;

    const state = {
      angle: -45,
      speed: 0,
      dragging: false,
      grab: 0,
      lastPointer: 0,
      lastTime: 0,
      rx: ORBIT_TILT,
      ry: 0,
      vrx: 0,
      vry: 0,
      targetRx: ORBIT_TILT,
      targetRy: 0,
      active: -1,
      startAt: 0,
      visible: false,
    };
    let frame = 0;
    let prev = 0;

    const pointerAngle = (event: PointerEvent) => {
      const box = ring.getBoundingClientRect();
      const scale = 400 / box.width;
      const u = (event.clientX - (box.left + box.width / 2)) * scale;
      const v = ((event.clientY - (box.top + box.height / 2)) * scale) / Math.cos((state.rx * Math.PI) / 180);
      return (Math.atan2(u, -v) * 180) / Math.PI;
    };

    const unwrap = (next: number, ref: number) => next + 360 * Math.round((ref - next) / 360);

    const setActive = (index: number) => {
      if (index === state.active) return;
      state.active = index;
      stepRefs.current.forEach((step, i) => step?.classList.toggle('is-dim', i !== index));
      faceRefs.current[index]?.firstElementChild?.animate(
        [{ scale: 1 }, { scale: 1.8 }, { scale: 1 }],
        { duration: 520, easing: 'cubic-bezier(0.2, 0.7, 0.2, 1)' },
      );
    };

    const tick = (now: number) => {
      frame = requestAnimationFrame(tick);
      const dt = Math.min((now - (prev || now)) / 1000, 0.05);
      prev = now;
      if (!root.classList.contains('is-in')) return;
      if (!state.startAt) state.startAt = now + 1400;
      const running = now >= state.startAt;

      if (!state.dragging) {
        const base = running ? ORBIT_BASE_SPEED : 0;
        state.speed += (base - state.speed) * (1 - Math.exp(-dt * 0.9));
        state.angle += state.speed * dt;
      }

      const k = 140;
      const c = 13;
      state.vrx += (k * (state.targetRx - state.rx) - c * state.vrx) * dt;
      state.vry += (k * (state.targetRy - state.ry) - c * state.vry) * dt;
      state.rx += state.vrx * dt;
      state.ry += state.vry * dt;

      orbit.setAttribute('transform', `rotate(${state.angle.toFixed(2)} 200 200)`);
      gx.style.setProperty('--rx', `${state.rx.toFixed(2)}deg`);
      gx.style.setProperty('--ry', `${state.ry.toFixed(2)}deg`);

      const len = Math.min(0.06 + Math.abs(state.speed) / 2400, 0.5);
      tail.style.strokeDasharray = `${len} ${1 - len}`;
      tail.style.strokeDashoffset = state.speed >= 0 ? `${-(0.75 - len)}` : '-0.75';
      tail.style.opacity = String(Math.min(0.35 + Math.abs(state.speed) / 900, 0.85));

      if (running || state.dragging) {
        const pos = (((state.angle - 315) % 360) + 360 + 2) % 360;
        setActive(Math.floor(pos / 90) % 4);
      }
    };

    const onDown = (event: PointerEvent) => {
      state.dragging = true;
      ring.setPointerCapture(event.pointerId);
      ring.classList.add('is-grabbing');
      const at = pointerAngle(event);
      state.grab = state.angle - at;
      state.lastPointer = at;
      state.lastTime = performance.now();
      state.speed = 0;
    };

    const onMove = (event: PointerEvent) => {
      const box = ring.getBoundingClientRect();
      const nx = ((event.clientX - box.left) / box.width - 0.5) * 2;
      const ny = ((event.clientY - box.top) / box.height - 0.5) * 2;
      state.targetRx = ORBIT_TILT - Math.max(-1, Math.min(1, ny)) * 12;
      state.targetRy = Math.max(-1, Math.min(1, nx)) * 16;
      if (!state.dragging) return;
      const now = performance.now();
      const at = unwrap(pointerAngle(event), state.lastPointer);
      const dt = Math.max((now - state.lastTime) / 1000, 0.001);
      const instant = (at - state.lastPointer) / dt;
      state.speed = state.speed * 0.6 + instant * 0.4;
      state.angle = at + state.grab;
      state.lastPointer = at;
      state.lastTime = now;
    };

    const onUp = (event: PointerEvent) => {
      if (!state.dragging) return;
      state.dragging = false;
      ring.classList.remove('is-grabbing');
      if (ring.hasPointerCapture(event.pointerId)) ring.releasePointerCapture(event.pointerId);
      if (performance.now() - state.lastTime > 80) state.speed *= 0.2;
      state.speed = Math.max(-2200, Math.min(2200, state.speed));
      state.vry += state.speed * 0.02;
    };

    const onLeave = () => {
      state.targetRx = ORBIT_TILT;
      state.targetRy = 0;
    };

    const observer = new IntersectionObserver(([entry]) => {
      state.visible = entry.isIntersecting;
      if (state.visible && !frame) {
        prev = 0;
        frame = requestAnimationFrame(tick);
      } else if (!state.visible && frame) {
        cancelAnimationFrame(frame);
        frame = 0;
      }
    });
    observer.observe(root);

    ring.addEventListener('pointerdown', onDown);
    ring.addEventListener('pointermove', onMove);
    ring.addEventListener('pointerup', onUp);
    ring.addEventListener('pointercancel', onUp);
    ring.addEventListener('pointerleave', onLeave);

    return () => {
      cleanupReveal?.();
      observer.disconnect();
      cancelAnimationFrame(frame);
      ring.removeEventListener('pointerdown', onDown);
      ring.removeEventListener('pointermove', onMove);
      ring.removeEventListener('pointerup', onUp);
      ring.removeEventListener('pointercancel', onUp);
      ring.removeEventListener('pointerleave', onLeave);
    };
  }, []);

  return (
    <div className="loop" ref={rootRef}>
      <div className="loop-ring" ref={ringRef} aria-hidden="true">
        <div className="gx" ref={gxRef}>
          <div className="gx-disc">
            <svg viewBox="0 0 400 400">
              <circle className="gx-orbit-ring is-outer" cx="200" cy="200" r="192" />
              <circle className="gx-orbit-ring is-inner" cx="200" cy="200" r="96" />
              <circle className="loop-track" cx="200" cy="200" r="150" />
              <g className="gx-orbit" ref={orbitRef} transform="rotate(-45 200 200)">
                <circle className="gx-tail" ref={tailRef} cx="200" cy="200" r="150" pathLength={1} />
                <path className="gx-arrow" d="M-6 -8 L5 0 L-6 8" transform="translate(200 50)" />
              </g>
            </svg>
            {GALAXY_NODES.map((node, index) => (
              <span key={index} className="gx-node" style={{ '--i': index, left: `${node.x / 4}%`, top: `${node.y / 4}%` } as CSSProperties}>
                <span className="gx-face" ref={(el) => { faceRefs.current[index] = el; }}>
                  <span className="loop-node" />
                  <span className="gx-num">{String(index + 1).padStart(2, '0')}</span>
                </span>
              </span>
            ))}
          </div>
        </div>
        <p className="loop-center">{center.split('\n').map((line, index) => (index ? <Fragment key={index}><br />{line}</Fragment> : line))}</p>
      </div>
      <ol className="loop-steps">
        {steps.map((step, index) => (
          <li key={step.title} ref={(el) => { stepRefs.current[index] = el; }} style={{ '--i': index } as CSSProperties}>
            <h3 className="h3"><span className="mono">{String(index + 1).padStart(2, '0')}</span>{step.title}</h3>
            <div className="rich"><p>{step.text}</p></div>
          </li>
        ))}
      </ol>
    </div>
  );
}
