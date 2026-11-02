import { useEffect, useLayoutEffect, useRef, useState, type CSSProperties, type ReactNode } from 'react';
import { Sparkles, Undo2 } from 'lucide-react';
import { api, firstError } from '../api';

type Mode = 'melhorar' | 'resumir' | 'sintetizar';

export type SectionCopyRequest = {
  kind: 'page' | 'case';
  target?: 'section' | 'seo';
  page_id?: number;
  index?: number;
  seo_title?: string;
  seo_description?: string;
  title?: string;
  description?: string;
  path?: string;
  sections: { type: string; data: Record<string, unknown> }[];
};

export type FieldAiContext = {
  request: () => SectionCopyRequest;
  disabled?: boolean;
};

const MODES: { value: Mode; label: string }[] = [
  { value: 'melhorar', label: 'Melhorar' },
  { value: 'resumir', label: 'Resumir' },
  { value: 'sintetizar', label: 'Sintetizar' },
];

const SKIP_NAMES = /(^|_)(href|url|whatsapp|anchor|id|alt|author|company|name|value|prefix|price|year|icon|glyph|log)$/;

export function acceptsAi(type: string, name: string): boolean {
  return ['text', 'textarea', 'rich'].includes(type) && !SKIP_NAMES.test(name);
}

export const AI_UNAVAILABLE = 'IA não configurada. Fale com o administrador.';

const POP_GAP = 6;

function isEmpty(value: unknown): boolean {
  return typeof value !== 'string' || value.replace(/<[^>]*>/g, '').trim() === '';
}

function scrollParent(node: HTMLElement): HTMLElement | null {
  let current = node.parentElement;
  while (current) {
    const overflow = getComputedStyle(current).overflowY;
    if ((overflow === 'auto' || overflow === 'scroll') && current.scrollHeight > current.clientHeight) {
      return current;
    }
    current = current.parentElement;
  }
  return null;
}

/**
 * Posiciona o popover abaixo do campo inteiro, não do ícone, para não cobrir o texto que vai ser reescrito.
 * Abre para cima quando não cabe abaixo dentro da área rolável.
 */
function popoverPosition(root: HTMLElement, pop: HTMLElement): CSSProperties {
  const field = (root.closest('.field-ai-wrap') as HTMLElement | null) ?? root;
  const anchor = root.getBoundingClientRect();
  const box = field.getBoundingClientRect();
  const scroller = scrollParent(root);
  const bounds = scroller ? scroller.getBoundingClientRect() : { top: 0, bottom: window.innerHeight };
  const height = pop.offsetHeight;
  const below = bounds.bottom - box.bottom;
  const above = box.top - bounds.top;

  if (below < height + POP_GAP * 2 && above > below) {
    return { top: box.top - anchor.top - height - POP_GAP };
  }
  return { top: box.bottom - anchor.top + POP_GAP };
}

export function FieldAi({
  ai,
  path,
  value,
  onApply,
}: {
  ai: FieldAiContext;
  path: string;
  value: unknown;
  onApply: (value: string) => void;
}) {
  const [open, setOpen] = useState(false);
  const [instruction, setInstruction] = useState('');
  const [running, setRunning] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [previous, setPrevious] = useState<string | null>(null);
  const [popStyle, setPopStyle] = useState<CSSProperties>({});
  const root = useRef<HTMLDivElement>(null);
  const pop = useRef<HTMLDivElement>(null);
  const trigger = useRef<HTMLButtonElement>(null);
  const empty = isEmpty(value);

  useEffect(() => {
    if (!open) {
      return;
    }
    function onDown(event: MouseEvent) {
      if (!root.current?.contains(event.target as Node)) {
        setOpen(false);
      }
    }
    document.addEventListener('mousedown', onDown);
    return () => document.removeEventListener('mousedown', onDown);
  }, [open]);

  useLayoutEffect(() => {
    if (open && root.current && pop.current) {
      setPopStyle(popoverPosition(root.current, pop.current));
    }
  }, [open, error, notice, running]);

  function close() {
    setOpen(false);
    trigger.current?.focus();
  }

  async function proofread() {
    setRunning(true);
    setError(null);
    setNotice(null);
    try {
      const result = await api<{ value: string; changed: boolean }>('/sections/proofread', {
        method: 'POST',
        body: JSON.stringify({ text: value }),
      });
      if (!result.changed || result.value === value) {
        setNotice('Nenhum erro encontrado.');
        return;
      }
      setPrevious(typeof value === 'string' ? value : '');
      onApply(result.value);
      close();
    } catch (err) {
      setError(firstError(err));
    } finally {
      setRunning(false);
    }
  }

  async function generate(mode: Mode) {
    setRunning(true);
    setError(null);
    setNotice(null);
    try {
      const result = await api<{ value: unknown }>('/sections/copy', {
        method: 'POST',
        body: JSON.stringify({ ...ai.request(), field: path, mode, instruction }),
      });
      if (typeof result.value !== 'string' || result.value === value) {
        setError('A IA devolveu o mesmo texto. Tente outro modo ou dê uma instrução.');
        return;
      }
      setPrevious(typeof value === 'string' ? value : '');
      onApply(result.value);
      close();
    } catch (err) {
      setError(firstError(err));
    } finally {
      setRunning(false);
    }
  }

  return (
    <div className="field-ai" ref={root}>
      {previous !== null && !running ? (
        <button
          className="field-ai-btn"
          type="button"
          title="Desfazer texto gerado"
          aria-label="Desfazer texto gerado"
          onClick={() => {
            onApply(previous);
            setPrevious(null);
          }}
        >
          <Undo2 size={12} />
        </button>
      ) : null}
      <button
        ref={trigger}
        className={`field-ai-btn${running ? ' is-running' : ''}${open ? ' is-open' : ''}`}
        type="button"
        disabled={ai.disabled || running}
        title={ai.disabled ? AI_UNAVAILABLE : running ? 'Gerando...' : 'Gerar com IA'}
        aria-label="Gerar com IA"
        aria-expanded={open}
        onClick={() => setOpen(!open)}
      >
        <Sparkles size={12} />
      </button>
      {open ? (
        <div
          ref={pop}
          className="field-ai-pop"
          role="dialog"
          aria-label="Gerar com IA"
          style={popStyle}
          onKeyDown={(event) => {
            if (event.key === 'Escape') {
              event.stopPropagation();
              close();
            }
          }}
        >
          <textarea
            value={instruction}
            rows={3}
            maxLength={1000}
            placeholder="Instrução (opcional). Enter gera, Shift+Enter quebra linha."
            aria-label="Instrução para a IA (opcional)"
            onChange={(event) => setInstruction(event.target.value)}
            onKeyDown={(event) => {
              if (event.key === 'Enter' && !event.shiftKey && !running) {
                event.preventDefault();
                void generate('melhorar');
              }
            }}
            autoFocus
          />
          <div className="field-ai-modes">
            {empty ? (
              <button type="button" disabled={running} onClick={() => void generate('melhorar')}>{running ? 'Gerando...' : 'Escrever'}</button>
            ) : (
              <>
                <button type="button" disabled={running} title="Corrige gramática, ortografia e digitação sem reescrever" onClick={() => void proofread()}>
                  Corrigir
                </button>
                {MODES.map((mode) => (
                  <button key={mode.value} type="button" disabled={running} onClick={() => void generate(mode.value)}>
                    {mode.label}
                  </button>
                ))}
              </>
            )}
          </div>
          {running ? <p className="field-ai-note">Gerando...</p> : null}
          {notice && !running ? <p className="field-ai-note">{notice}</p> : null}
          {error ? <p className="field-ai-error">{error}</p> : null}
        </div>
      ) : null}
    </div>
  );
}

export function FieldAiWrap({
  ai,
  type,
  path,
  value,
  onApply,
  children,
}: {
  ai?: FieldAiContext;
  type: 'text' | 'textarea';
  path: string;
  value: unknown;
  onApply: (value: string) => void;
  children: ReactNode;
}) {
  if (!ai) {
    return <>{children}</>;
  }

  return (
    <div className={`field-ai-wrap is-${type}`}>
      {children}
      <FieldAi ai={ai} path={path} value={value} onApply={onApply} />
    </div>
  );
}
