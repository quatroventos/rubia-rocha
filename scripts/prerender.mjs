// Gera o HTML público de cada URL a partir dos componentes React.
// Uso: node scripts/prerender.mjs storage/app/prerender-payloads.json

import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createServer } from 'vite';
import react from '@vitejs/plugin-react';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const input = resolve(root, process.argv[2] ?? 'storage/app/prerender-payloads.json');
const outRoot = join(root, 'storage/app/prerender');

function decode(value) {
  return value
    .replace(/&#x([0-9a-f]+);/gi, (_, hex) => String.fromCodePoint(parseInt(hex, 16)))
    .replace(/&#(\d+);/g, (_, dec) => String.fromCodePoint(Number(dec)))
    .replace(/&nbsp;/g, ' ')
    .replace(/&quot;/g, '"')
    .replace(/&apos;/g, "'")
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&amp;/g, '&');
}

function textOf(html) {
  return decode(html.replace(/<[^>]+>/g, ' ')).replace(/\s+/g, ' ').trim();
}

function jsonLd(html) {
  const graph = [];
  const questions = [];

  for (const match of html.matchAll(/<details>([\s\S]*?)<\/details>/g)) {
    const block = match[1];
    const summary = block.match(/<summary>([\s\S]*?)<\/summary>/);
    const answer = block.match(/<div class="rich">([\s\S]*?)<\/div>/);
    const name = summary ? textOf(summary[1]) : '';
    const text = answer ? textOf(answer[1]) : '';
    if (name && text) {
      questions.push({
        '@type': 'Question',
        name,
        acceptedAnswer: { '@type': 'Answer', text },
      });
    }
  }

  if (questions.length) {
    graph.push({ '@type': 'FAQPage', mainEntity: questions });
  }

  const offers = [];
  const seen = new Set();
  for (const match of html.matchAll(/data-offer="([^"]+)"/g)) {
    const [name, price] = decode(match[1]).split('|');
    const key = `${name}|${price}`;
    if (!name || !price || seen.has(key)) {
      continue;
    }
    seen.add(key);
    offers.push({
      '@type': 'Offer',
      name,
      price,
      priceCurrency: 'BRL',
    });
  }

  if (offers.length) {
    graph.push(...offers);
  }

  if (!graph.length) {
    return '';
  }

  const payload = {
    '@context': 'https://schema.org',
    '@graph': graph,
  };

  return `<script type="application/ld+json">${JSON.stringify(payload).replace(/</g, '\\u003c')}</script>`;
}

function fileFor(page) {
  const locale = page.locale || 'pt-BR';
  const path = (page.path || '/').replace(/^\/+|\/+$/g, '');
  const name = path === '' ? 'index.html' : `${path}.html`;

  return join(outRoot, locale, name);
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
  const payloads = JSON.parse(await readFile(input, 'utf8'));
  let count = 0;

  for (const item of payloads) {
    const body = renderSite(item.page, item.site);
    const file = fileFor(item.page);
    const markup = `${body}${jsonLd(body)}`;
    await mkdir(dirname(file), { recursive: true });
    await writeFile(file, markup);
    count += 1;
    process.stdout.write(`${item.page.path}\n`);
  }

  process.stdout.write(`${count} páginas\n`);
} finally {
  await vite.close();
}
