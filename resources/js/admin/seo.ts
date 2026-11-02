export type SeoDefaults = {
  seo_separator: string;
  seo_title_template: string;
  seo_description: string | null;
  seo_og_image: string | null;
  seo_og_image_alt: string | null;
  seo_robots: string;
  og_site_name: string;
  og_locale: string;
  twitter_card: string;
};

export type SeoPagePreview = {
  slug: string;
  status: string;
  seo_title: string;
  seo_description: string | null;
  canonical: string | null;
  path: string;
  resolved_title: string;
  resolved_description: string | null;
};

export const SEO_TITLE_MAX = 60;
export const SEO_DESCRIPTION_MIN = 120;
export const SEO_DESCRIPTION_MAX = 160;

export function composeSeoTitle(pageTitle: string, defaults: SeoDefaults | null | undefined): string {
  const siteName = defaults?.og_site_name?.trim() || '';
  const title = pageTitle.trim();

  if (!title) {
    return siteName || 'Título da página';
  }

  if (siteName && title.toLowerCase().includes(siteName.toLowerCase())) {
    return title;
  }

  const template = defaults?.seo_title_template || '%page% %sep% %site%';
  const separator = defaults?.seo_separator || '|';

  return template
    .replaceAll('%page%', title)
    .replaceAll('%sep%', separator)
    .replaceAll('%site%', siteName)
    .replace(/\s+/g, ' ')
    .trim();
}

export function resolveSeoDescription(pageDescription: string | null | undefined, defaults: SeoDefaults | null | undefined): string | null {
  const page = pageDescription?.trim();

  if (page) {
    return page;
  }

  return defaults?.seo_description?.trim() || null;
}
