import { useCallback, useEffect, useRef, useState } from 'react';
import { Eye } from 'lucide-react';
import { api } from '../api';

const DEBOUNCE_MS = 400;

/**
 * Abre o preview do site numa aba e envia o conteúdo ainda não salvo a cada alteração.
 * O servidor guarda o rascunho e a aba de preview consulta; funciona em qualquer aba ou navegador logado.
 * `previewPath` é relativo ao painel (preview/12); `endpoint` monta o payload no servidor sem gravar.
 */
export function LivePreviewButton({
  previewPath,
  endpoint,
  body,
  className = 'btn btn-secondary',
}: {
  previewPath: string;
  endpoint: string;
  body: unknown;
  className?: string;
}) {
  const json = JSON.stringify(body);
  const jsonRef = useRef(json);
  const [active, setActive] = useState(false);
  const [failed, setFailed] = useState(false);
  jsonRef.current = json;

  const push = useCallback(async () => {
    try {
      await api(endpoint, { method: 'POST', body: jsonRef.current });
      setFailed(false);
    } catch {
      setFailed(true);
    }
  }, [endpoint]);

  useEffect(() => {
    if (!active) {
      return;
    }
    const timer = window.setTimeout(() => void push(), DEBOUNCE_MS);
    return () => window.clearTimeout(timer);
  }, [json, active, push]);

  async function openPreview() {
    setActive(true);
    await push();
    window.open(`${window.__ADMIN__.path}/${previewPath}`, `cms-preview-${previewPath}`);
  }

  return (
    <button
      className={className}
      type="button"
      onClick={() => void openPreview()}
      title={failed ? 'Não foi possível atualizar o preview. Confira os campos.' : 'Abre o site numa aba que acompanha as alterações sem salvar'}
    >
      <Eye size={14} /> {active ? (failed ? 'Preview com erro' : 'Preview ao vivo') : 'Pré-visualizar'}
    </button>
  );
}
