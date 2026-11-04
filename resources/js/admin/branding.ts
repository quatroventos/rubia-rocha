export type AdminBranding = {
  name: string;
  logo: string | null;
};

export function branding(): AdminBranding {
  const data = window.__ADMIN__?.branding;

  return {
    name: data?.name?.trim() || 'Rubia Rocha Fotografia',
    logo: data?.logo?.trim() || null,
  };
}
