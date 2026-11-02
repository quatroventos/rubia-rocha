// Quadro dos atendimentos avulsos: duas colunas de serviços com ícone e a tela do site com o log do reparo.
import type { ReactNode } from 'react';
import { revealOnView } from './ui';

export type AdhocItem = { icon: string; title: string; text: string };

export const ADHOC_ICONS: Record<string, ReactNode> = {
  update: <path d="M20 11a8 8 0 0 0-14.5-4.5M4 4v3h3M4 13a8 8 0 0 0 14.5 4.5M20 20v-3h-3" />,
  speed: <path d="M4 17a8 8 0 1 1 16 0M12 17l4-5M10.5 17h3" />,
  shield: <path d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6l7-3zM9 12l2 2 4-4" />,
  move: <path d="M7 8h11l-3-3M17 16H6l3 3" />,
  power: <path d="M12 3v8M7.5 6.5a7 7 0 1 0 9 0" />,
  search: <path d="M10.5 17a6.5 6.5 0 1 0 0-13 6.5 6.5 0 0 0 0 13zM15.5 15.5L20 20" />,
};

export function AdhocBoard({ columns, url, log, status }: { columns: AdhocItem[][]; url: string; log: string[]; status: string }) {
  return (
    <div className="adhoc" ref={revealOnView}>
      {columns.map((column, side) => (
        <ul className={`adhoc-list ${side === 0 ? 'is-left' : 'is-right'}`} key={side}>
          {column.map((item) => (
            <li key={item.title}>
              <span className="adhoc-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">{ADHOC_ICONS[item.icon]}</svg>
              </span>
              <div>
                <h3 className="h3">{item.title}</h3>
                <p>{item.text}</p>
              </div>
            </li>
          ))}
        </ul>
      ))}
      <div className="adhoc-screen" aria-hidden="true">
        <div className="adhoc-bar">
          <i /><i /><i />
          <span className="mono">{url}</span>
        </div>
        <ol className="adhoc-log">
          {log.map((line) => (
            <li key={line}>
              <span className="plan-tick"><svg viewBox="0 0 16 16"><path d="M3.5 8.5l3 3 6-7" pathLength={1} fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" /></svg></span>
              <span className="mono">{line}</span>
            </li>
          ))}
        </ol>
        <span className="adhoc-status"><i />{status}</span>
      </div>
    </div>
  );
}
