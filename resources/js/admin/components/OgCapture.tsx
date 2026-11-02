import { useState } from 'react';
import { ImagePlus } from 'lucide-react';
import { api, firstError } from '../api';

/**
 * Gera o card de OG (1200x630: logo, fundo do hero, título e URL) com o conteúdo atual do editor, sem salvar.
 */
export function OgCaptureButton({
  endpoint,
  body,
  onCaptured,
}: {
  endpoint: string;
  body: unknown;
  onCaptured: (path: string) => void;
}) {
  const [running, setRunning] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function capture() {
    setRunning(true);
    setError(null);
    try {
      const result = await api<{ path: string }>(endpoint, { method: 'POST', body: JSON.stringify(body) });
      onCaptured(result.path);
    } catch (err) {
      setError(firstError(err));
    } finally {
      setRunning(false);
    }
  }

  return (
    <div className="og-capture">
      <button className="btn btn-secondary" type="button" disabled={running} onClick={() => void capture()}>
        <ImagePlus size={14} /> {running ? 'Gerando card...' : 'Gerar card de OG'}
      </button>
      <p className="hint">
        {running
          ? 'Montando o card com logo, fundo do hero, título e endereço em 1200×630.'
          : 'Card com logo, fundo do hero, título (com destaque) e endereço da página. Cards gerados são refeitos sozinhos quando o título muda.'}
      </p>
      {error ? <p className="field-ai-error">{error}</p> : null}
    </div>
  );
}
