export type ImageMeta = { width: number; height: number; srcset: string | null };

export type ImageMap = Record<string, ImageMeta>;

export type MenuNode = { label: string; href: string; children: MenuNode[] };

export type FormField = {
  name: string;
  label: string;
  type: string;
  required?: boolean;
  hint?: string;
  options?: { label: string; value: string }[];
};

export type FormSchema = {
  submit_label?: string;
  lgpd_label?: string;
  sections: { label: string; fields: FormField[] }[];
};

export type SitePayload = {
  name: string;
  locale?: string;
  home_path?: string;
  phone?: string | null;
  email?: string | null;
  whatsapp_url?: string | null;
  logo: string;
  instagram_url?: string | null;
  facebook_url?: string | null;
  menus: { header: MenuNode[]; footer: MenuNode[] };
  footer_gallery_title?: string | null;
  footer_gallery: { image: string; alt: string; href: string }[];
  form?: FormSchema | null;
  images?: ImageMap;
};

export type SectionData = Record<string, unknown>;

export type Section = { id: string; type: string; data: SectionData };

export type PagePayload = {
  slug: string;
  path: string;
  locale?: string;
  template?: string | null;
  sections: Section[];
  images?: ImageMap;
  seo?: { title?: string; description?: string };
};
