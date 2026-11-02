import { type DragEvent, type KeyboardEvent, type PointerEvent, useId, useRef, useState } from 'react';
import { AlertCircle, ImagePlus, X } from 'lucide-react';
import { api, firstError } from '../api';

type Logo = { name?: string; image?: string; image_alt?: string; [key: string]: unknown };

type Pending = { key: string; file: string; error?: string };

const ACCEPT = 'image/svg+xml,image/png,image/webp,image/jpeg,image/gif,.svg';
const CONCURRENCY = 3;

function nameFromFile(file: string): string {
  return file
    .replace(/\.[a-z0-9]+$/i, '')
    .replace(/[-_]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .replace(/(^|\s)\S/g, (char) => char.toUpperCase());
}

export function LogosField({
  label,
  value,
  onChange,
}: {
  label: string;
  value: unknown;
  onChange: (value: Logo[]) => void;
}) {
  const logos: Logo[] = Array.isArray(value) ? value.filter((item): item is Logo => Boolean(item) && typeof item === 'object') : [];
  const [pending, setPending] = useState<Pending[]>([]);
  const [dropping, setDropping] = useState(false);
  const [order, setOrder] = useState<number[] | null>(null);
  const [dragged, setDragged] = useState<number | null>(null);
  const input = useRef<HTMLInputElement>(null);
  const latest = useRef(logos);
  const drag = useRef<{ index: number; x: number; y: number; active: boolean; order: number[] } | null>(null);
  const hintId = useId();
  const labelId = useId();

  latest.current = logos;

  const view = order ?? logos.map((_, index) => index);
  const uploading = pending.some((item) => !item.error);

  async function upload(files: File[]) {
    const images = files.filter((file) => file.type.startsWith('image/') || /\.svg$/i.test(file.name));
    if (images.length === 0) {
      return;
    }

    const jobs = images.map((file) => ({ file, key: `${Date.now()}-${Math.random().toString(36).slice(2)}` }));
    setPending((current) => [...current, ...jobs.map((job) => ({ key: job.key, file: job.file.name }))]);

    const results: (Logo | null)[] = new Array(jobs.length).fill(null);
    let cursor = 0;

    async function worker() {
      while (cursor < jobs.length) {
        const position = cursor;
        cursor += 1;
        const job = jobs[position];
        try {
          const body = new FormData();
          body.append('file', job.file);
          body.append('kind', 'image');
          const payload = await api<{ path: string }>('/media', { method: 'POST', body });
          results[position] = { name: nameFromFile(job.file.name), image: payload.path, image_alt: '' };
          setPending((current) => current.filter((item) => item.key !== job.key));
        } catch (error) {
          setPending((current) => current.map((item) => (item.key === job.key ? { ...item, error: firstError(error) } : item)));
        }
      }
    }

    await Promise.all(Array.from({ length: Math.min(CONCURRENCY, jobs.length) }, worker));

    const added = results.filter((item): item is Logo => item !== null);
    if (added.length > 0) {
      onChange([...latest.current, ...added]);
    }
  }

  function remove(index: number) {
    onChange(logos.filter((_, position) => position !== index));
  }

  function rename(index: number, name: string) {
    onChange(logos.map((item, position) => (position === index ? { ...item, name } : item)));
  }

  function move(index: number, to: number) {
    if (to < 0 || to >= logos.length || to === index) {
      return;
    }
    const next = [...logos];
    const [item] = next.splice(index, 1);
    next.splice(to, 0, item);
    onChange(next);
  }

  function onTilePointerDown(index: number, event: PointerEvent<HTMLLIElement>) {
    if (event.button !== 0 || (event.target as HTMLElement).closest('button, input')) {
      return;
    }
    drag.current = { index, x: event.clientX, y: event.clientY, active: false, order: logos.map((_, position) => position) };
    event.currentTarget.setPointerCapture(event.pointerId);
  }

  function onTilePointerMove(event: PointerEvent<HTMLLIElement>) {
    const current = drag.current;
    if (!current) {
      return;
    }
    if (!current.active) {
      if (Math.hypot(event.clientX - current.x, event.clientY - current.y) < 5) {
        return;
      }
      current.active = true;
      setDragged(current.index);
      setOrder(current.order);
    }

    const slot = document.elementFromPoint(event.clientX, event.clientY)?.closest<HTMLElement>('[data-logo-slot]');
    if (!slot) {
      return;
    }
    const target = Number(slot.dataset.logoSlot);
    const from = current.order.indexOf(current.index);
    if (Number.isNaN(target) || target === from) {
      return;
    }
    const next = [...current.order];
    next.splice(from, 1);
    next.splice(target, 0, current.index);
    current.order = next;
    setOrder(next);
  }

  function onTilePointerUp() {
    const current = drag.current;
    drag.current = null;
    setDragged(null);
    setOrder(null);
    if (current?.active && current.order.some((item, position) => item !== position)) {
      onChange(current.order.map((position) => logos[position]));
    }
  }

  function onTileKeyDown(index: number, event: KeyboardEvent<HTMLLIElement>) {
    if (event.target !== event.currentTarget) {
      return;
    }
    if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
      event.preventDefault();
      move(index, index - 1);
    } else if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
      event.preventDefault();
      move(index, index + 1);
    }
  }

  function onDrop(event: DragEvent<HTMLDivElement>) {
    event.preventDefault();
    setDropping(false);
    void upload(Array.from(event.dataTransfer.files));
  }

  function onDragOver(event: DragEvent<HTMLDivElement>) {
    if (Array.from(event.dataTransfer.types).includes('Files')) {
      event.preventDefault();
      setDropping(true);
    }
  }

  return (
    <div
      className={`field logos-field${dropping ? ' is-dropping' : ''}`}
      onDragOver={onDragOver}
      onDragLeave={(event) => {
        if (!event.currentTarget.contains(event.relatedTarget as Node | null)) {
          setDropping(false);
        }
      }}
      onDrop={onDrop}
    >
      <div className="logos-field-head">
        <span className="field-label" id={labelId}>{label}</span>
        {logos.length > 0 ? <span className="logos-field-count">{logos.length} {logos.length === 1 ? 'logo' : 'logos'}</span> : null}
      </div>

      <input
        ref={input}
        className="media-input"
        type="file"
        accept={ACCEPT}
        multiple
        hidden
        onChange={(event) => {
          void upload(Array.from(event.target.files ?? []));
          event.target.value = '';
        }}
      />

      {logos.length > 0 || pending.length > 0 ? (
        <ul className={`logos-grid${dragged !== null ? ' is-sorting' : ''}`} aria-labelledby={labelId} aria-describedby={hintId}>
          {view.map((index, slot) => {
            const logo = logos[index];
            const name = logo.name ?? '';

            return (
              <li
                key={`${logo.image ?? ''}-${index}`}
                data-logo-slot={slot}
                className={`logo-tile${dragged === index ? ' is-dragged' : ''}`}
                tabIndex={0}
                aria-label={`${name || 'Logo sem nome'}, posição ${slot + 1} de ${logos.length}`}
                onPointerDown={(event) => onTilePointerDown(index, event)}
                onPointerMove={onTilePointerMove}
                onPointerUp={onTilePointerUp}
                onPointerCancel={onTilePointerUp}
                onKeyDown={(event) => onTileKeyDown(index, event)}
              >
                <div className="logo-tile-art">
                  {logo.image ? <img src={logo.image} alt="" draggable={false} /> : <span className="logo-tile-missing">Sem imagem</span>}
                  <span className="logo-tile-index">{slot + 1}</span>
                  <button type="button" className="logo-tile-remove" aria-label={`Remover ${name || 'logo'}`} onClick={() => remove(index)}>
                    <X size={12} />
                  </button>
                </div>
                <input
                  className="logo-tile-name"
                  value={name}
                  placeholder="Nome"
                  aria-label="Nome do cliente"
                  onChange={(event) => rename(index, event.target.value)}
                />
              </li>
            );
          })}
          {pending.map((item) => (
            <li key={item.key} className={`logo-tile is-pending${item.error ? ' is-error' : ''}`}>
              <div className="logo-tile-art">
                {item.error ? <AlertCircle size={16} aria-hidden="true" /> : <span className="logo-tile-spinner" aria-hidden="true" />}
                {item.error ? (
                  <button
                    type="button"
                    className="logo-tile-remove"
                    aria-label={`Descartar ${item.file}`}
                    onClick={() => setPending((current) => current.filter((entry) => entry.key !== item.key))}
                  >
                    <X size={12} />
                  </button>
                ) : null}
              </div>
              <span className="logo-tile-status" title={item.error ?? item.file}>
                {item.error ? `${item.file}: ${item.error}` : item.file}
              </span>
            </li>
          ))}
        </ul>
      ) : null}

      <button
        type="button"
        className={`logos-drop${logos.length > 0 ? ' is-compact' : ''}`}
        onClick={() => input.current?.click()}
      >
        <ImagePlus size={logos.length > 0 ? 15 : 20} aria-hidden="true" />
        <span>
          <strong>{uploading ? 'Enviando...' : logos.length > 0 ? 'Adicionar mais logos' : 'Arraste os logos aqui ou clique para escolher'}</strong>
          <span>SVG, PNG, WebP ou JPG. Vários arquivos de uma vez.</span>
        </span>
      </button>

      <p className="hint" id={hintId}>
        {logos.length > 1
          ? 'Arraste para reordenar, ou foque um logo e use as setas. O nome serve de texto alternativo.'
          : 'O nome vem do arquivo e serve de texto alternativo; ajuste se quiser.'}
      </p>
    </div>
  );
}
