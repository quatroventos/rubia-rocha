// Glifos de traço dos grids com ícone (princípios, canais). Os ids são os valores do campo glyph no CMS.
import type { CSSProperties, ReactNode } from 'react';

export const GLYPHS: Record<string, ReactNode> = {
  search: <><circle cx="17" cy="17" r="10" pathLength={1} /><path d="m24.5 24.5 9 9" pathLength={1} style={{ '--j': 1 } as CSSProperties} /></>,
  target: <><path d="M20 4v8M20 28v8M4 20h8M28 20h8" pathLength={1} /><path d="M20 13a7 7 0 1 1 0 14 7 7 0 0 1 0-14Z" pathLength={1} style={{ '--j': 1 } as CSSProperties} /></>,
  team: <><circle cx="14" cy="16" r="8" pathLength={1} /><circle cx="26" cy="16" r="8" pathLength={1} style={{ '--j': 1 } as CSSProperties} /><circle cx="20" cy="26" r="8" pathLength={1} style={{ '--j': 2 } as CSSProperties} /></>,
  shield: <><path d="M20 4 33 9v10c0 8-5.5 14-13 17C12.5 33 7 27 7 19V9l13-5Z" pathLength={1} /><path d="m14 20 4.5 4.5L27 16" pathLength={1} style={{ '--j': 1 } as CSSProperties} /></>,
  loop: <><path d="M33 20a13 13 0 1 1-4-9.4" pathLength={1} /><path d="M30 4v7h-7" pathLength={1} style={{ '--j': 1 } as CSSProperties} /></>,
  calendar: <><rect x="6" y="8" width="28" height="26" rx="4" pathLength={1} /><path d="M6 16h28M13 4v7M27 4v7" pathLength={1} style={{ '--j': 1 } as CSSProperties} /><path d="m15 25 3.5 3.5L26 21" pathLength={1} style={{ '--j': 2 } as CSSProperties} /></>,
  whatsapp: <><path d="M20 6a14 14 0 0 0-12 21.2L6 34l7-1.9A14 14 0 1 0 20 6Z" pathLength={1} /><path d="M15 15c0 6 4 10 10 10" pathLength={1} style={{ '--j': 1 } as CSSProperties} /></>,
  mail: <><rect x="5" y="9" width="30" height="22" rx="4" pathLength={1} /><path d="m6 11 14 11 14-11" pathLength={1} style={{ '--j': 1 } as CSSProperties} /></>,
  support: <><path d="M8 23v-3a12 12 0 0 1 24 0v3" pathLength={1} /><path d="M8 22h4v9H8a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2ZM32 22h-4v9h4a2 2 0 0 0 2-2v-5a2 2 0 0 0-2-2Z" pathLength={1} style={{ '--j': 1 } as CSSProperties} /></>,
};
