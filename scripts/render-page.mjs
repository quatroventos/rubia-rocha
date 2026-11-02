// Renderiza uma única página com os mesmos componentes do prerender e devolve o HTML no stdout.
// Uso: node scripts/render-page.mjs < payload.json   ({"page": {...}, "site": {...}})

import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createServer } from 'vite';
import react from '@vitejs/plugin-react';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');

let input = '';
for await (const chunk of process.stdin) {
  input += chunk;
}

const vite = await createServer({
  configFile: false,
  root,
  appType: 'custom',
  server: { middlewareMode: true, hmr: false },
  plugins: [react()],
  logLevel: 'error',
});

try {
  const { renderSite } = await vite.ssrLoadModule('/resources/js/site/document.tsx');
  const { page, site } = JSON.parse(input);
  process.stdout.write(renderSite(page, site));
} finally {
  await vite.close();
}
