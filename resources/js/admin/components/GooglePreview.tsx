function clip(value: string, max: number): string {
  const text = value.replace(/\s+/g, ' ').trim();
  if (text.length <= max) {
    return text;
  }

  return `${text.slice(0, max - 1).trimEnd()}…`;
}

function displayUrl(canonical: string | null, slug: string): { host: string; path: string } {
  const fallback = `${window.location.origin}${slug === 'home' ? '/' : `/${slug}`}`;
  try {
    const url = new URL(canonical || fallback);
    const crumbs = url.pathname.split('/').filter(Boolean);

    return {
      host: url.host.replace(/^www\./, ''),
      path: crumbs.length ? ` › ${crumbs.join(' › ')}` : '',
    };
  } catch {
    return { host: window.location.host, path: slug === 'home' ? '' : ` › ${slug}` };
  }
}

export function GooglePreview({
  title,
  description,
  canonical,
  slug,
  className,
}: {
  title: string;
  description: string | null;
  canonical: string | null;
  slug: string;
  className?: string;
}) {
  const url = displayUrl(canonical, slug);

  return (
    <div className={className ? `serp ${className}` : 'serp'}>
      <span className="serp-kicker">Prévia no Google</span>
      <div className="serp-url">
        <span className="serp-favicon" aria-hidden="true" />
        {url.host}
        <span>{url.path}</span>
      </div>
      <div className="serp-title">{clip(title || 'Título da página', 60)}</div>
      <p className="serp-desc">{clip(description || 'A description aparece aqui, em até duas linhas, como no resultado da busca.', 160)}</p>
    </div>
  );
}
