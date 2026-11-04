import { useId, useRef, useState } from 'react';
import { Upload } from 'lucide-react';
import { api, firstError } from '../api';

const PICK_LABELS = {
  image: 'Escolher imagem',
  video: 'Escolher vídeo',
  pdf: 'Escolher PDF',
};

export function MediaUploadMany({ onUploaded }: { onUploaded: (paths: string[]) => void }) {
  const input = useRef<HTMLInputElement>(null);
  const [progress, setProgress] = useState<{ done: number; total: number } | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function upload(files: File[]) {
    setError(null);
    setProgress({ done: 0, total: files.length });
    const paths: string[] = [];
    try {
      for (const file of files) {
        const body = new FormData();
        body.append('file', file);
        body.append('kind', 'image');
        const payload = await api<{ path: string }>('/media', { method: 'POST', body });
        paths.push(payload.path);
        setProgress({ done: paths.length, total: files.length });
      }
    } catch (err) {
      setError(firstError(err));
    } finally {
      if (paths.length > 0) {
        onUploaded(paths);
      }
      setProgress(null);
      if (input.current) {
        input.current.value = '';
      }
    }
  }

  return (
    <>
      <input
        ref={input}
        className="media-input"
        type="file"
        accept="image/*"
        multiple
        onChange={(event) => {
          const files = Array.from(event.target.files ?? []);
          if (files.length > 0) {
            void upload(files);
          }
        }}
      />
      <button className="btn btn-secondary" type="button" disabled={progress !== null} onClick={() => input.current?.click()}>
        <Upload size={14} /> {progress ? `Enviando ${progress.done + 1} de ${progress.total}...` : 'Enviar várias fotos'}
      </button>
      {error ? <p className="field-ai-error">{error}</p> : null}
    </>
  );
}

export function MediaField({
  label,
  value,
  kind = 'image',
  hint,
  onChange,
}: {
  label: string;
  value?: string | null;
  kind?: 'image' | 'video' | 'pdf';
  hint?: string;
  onChange: (path: string | null) => void;
}) {
  const id = useId();
  const input = useRef<HTMLInputElement>(null);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function upload(file: File) {
    setUploading(true);
    setError(null);
    try {
      const body = new FormData();
      body.append('file', file);
      body.append('kind', kind);
      const payload = await api<{ path: string }>('/media', { method: 'POST', body });
      onChange(payload.path);
    } catch (err) {
      setError(firstError(err));
    } finally {
      setUploading(false);
      if (input.current) {
        input.current.value = '';
      }
    }
  }

  return (
    <div className="field">
      {label ? <label htmlFor={id}>{label}</label> : null}
      <input
        ref={input}
        className="media-input"
        type="file"
        accept={kind === 'video' ? 'video/mp4,video/webm' : kind === 'pdf' ? 'application/pdf' : 'image/*'}
        onChange={(event) => {
          const file = event.target.files?.[0];
          if (file) {
            void upload(file);
          }
        }}
      />
      <button id={id} className="btn btn-secondary media-pick" type="button" disabled={uploading} onClick={() => input.current?.click()}>
        <Upload size={13} /> {uploading ? 'Enviando...' : PICK_LABELS[kind]}
      </button>
      {error ? <p className="field-ai-error">{error}</p> : null}
      {value ? (
        <div className="media-preview-wrap">
          {kind === 'image' ? (
            <img className="media-preview" src={value} alt="" />
          ) : (
            <p className="hint">{value}</p>
          )}
          <button className="btn btn-text" type="button" onClick={() => onChange(null)}>
            Remover
          </button>
        </div>
      ) : null}
      {hint ? <p className="hint">{hint}</p> : null}
    </div>
  );
}
