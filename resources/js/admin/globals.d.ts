export {};

declare module '*.svg' {
  const src: string;
  export default src;
}

declare global {
  interface Window {
    __ADMIN__: {
      path: string;
      csrf: string;
      branding?: {
        name: string;
        logo: string | null;
      };
    };
  }
}
