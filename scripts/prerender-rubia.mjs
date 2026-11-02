// Gera o HTML estático das páginas da Rubia Rocha Fotografia.
// Uso: node scripts/prerender-rubia.mjs

import { mkdir, writeFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createServer } from 'vite';
import react from '@vitejs/plugin-react';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const outRoot = join(root, 'storage/app/prerender/rubia');

const vite = await createServer({
  configFile: false,
  root,
  appType: 'custom',
  server: { middlewareMode: true, hmr: false },
  plugins: [react()],
  logLevel: 'error',
});

try {
  const { renderRubia, RUBIA_PATHS } = await vite.ssrLoadModule('/resources/js/rubia/document.tsx');

  for (const path of RUBIA_PATHS) {
    const body = renderRubia(path);
    const name = path === '' ? 'index.html' : `${path}.html`;
    const file = join(outRoot, name);
    await mkdir(dirname(file), { recursive: true });
    await writeFile(file, body);
    process.stdout.write(`/${path}\n`);
  }

  process.stdout.write(`${RUBIA_PATHS.length} páginas\n`);
} finally {
  await vite.close();
}
