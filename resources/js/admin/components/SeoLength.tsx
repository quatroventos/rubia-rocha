import { SEO_DESCRIPTION_MAX, SEO_DESCRIPTION_MIN, SEO_TITLE_MAX } from '../seo';

/**
 * Medidor do tamanho visível no Google. O título é medido já composto com o template do site.
 * `fallback` indica que o campo está vazio e o valor medido é o padrão que vai ao ar.
 * `id` permite ligar o medidor ao campo via aria-describedby, sem anunciar a cada tecla.
 */
export function SeoLength({
  kind,
  text,
  fallback = false,
  id,
}: {
  kind: 'title' | 'description';
  text: string | null | undefined;
  fallback?: boolean;
  id?: string;
}) {
  const length = (text ?? '').trim().length;
  const max = kind === 'title' ? SEO_TITLE_MAX : SEO_DESCRIPTION_MAX;
  const min = kind === 'title' ? 30 : SEO_DESCRIPTION_MIN;
  const state = length === 0 ? 'empty' : length > max ? 'over' : length < min ? 'short' : 'ok';
  const label = {
    empty: 'Vazio',
    short: 'Curto',
    ok: 'Bom',
    over: 'O Google corta',
  }[state];

  return (
    <div className={`seo-length is-${state}`} id={id}>
      <div className="seo-length-bar" aria-hidden="true">
        <span style={{ width: `${Math.min(100, (length / max) * 100)}%` }} />
        <i style={{ left: `${(min / max) * 100}%` }} />
      </div>
      <p>
        <strong>{length}/{max}</strong> {label}
        {kind === 'title' ? ' · com o nome do site' : ''}
        {fallback ? ' · usando o padrão' : ''}
      </p>
    </div>
  );
}
