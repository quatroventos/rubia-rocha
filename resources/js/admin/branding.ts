export type AdminBranding = {
  name: string;
  logo: string | null;
};

export function branding(): AdminBranding {
  const data = window.__ADMIN__?.branding;

  return {
    name: data?.name?.trim() || 'Quatro Ventos',
    logo: data?.logo?.trim() || null,
  };
}
