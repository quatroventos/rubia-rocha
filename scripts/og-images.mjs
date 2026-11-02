// Gera as imagens de compartilhamento (1200x630) de cada pagina em public/assets/og/{slug}.jpg
// a partir do titulo do hero em database/data/pages/*.json, e grava og_image/og_image_alt no JSON.
// Uso: node scripts/og-images.mjs [slug ...]
import { execFileSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, readFileSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { createServer } from 'vite';

const root = resolve(import.meta.dirname, '..');
const pagesDir = join(root, 'database/data/pages');
const outDir = join(root, 'public/assets/og');
const chrome = process.env.CHROME_BIN ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const only = process.argv.slice(2);

const fontUrl = (file) => pathToFileURL(join(root, 'resources/fonts', file)).href;
const logo = readFileSync(join(root, 'public/assets/logo-quatroventos.svg'), 'utf8').replace(/<\?xml[^>]*>|<!--[\s\S]*?-->/g, '');

const escape = (value) => String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

function heroOf(page) {
  return page.sections.find((section) => section.type === 'site.hero')?.data ?? {};
}

function titleHtml(hero, page) {
  const title = String(hero.title ?? page.seo_title ?? '').trim();
  const rotating = Array.isArray(hero.rotating) ? hero.rotating.filter(Boolean) : [];

  if (rotating.length) {
    return `${escape(title)} <em>${escape(rotating[0])}</em>`;
  }

  return escape(title).replace(/\*([^*]+)\*/g, '<em>$1</em>');
}

function plainTitle(hero, page) {
  const rotating = Array.isArray(hero.rotating) ? hero.rotating.filter(Boolean) : [];
  return `${String(hero.title ?? page.seo_title ?? '').replace(/\*/g, '')}${rotating.length ? ` ${rotating[0]}` : ''}`.trim();
}

function sizeFor(text) {
  const length = text.length;
  if (length <= 26) {
    return 104;
  }
  if (length <= 40) {
    return 90;
  }
  if (length <= 56) {
    return 78;
  }
  return 68;
}

function logHtml(lines) {
  return lines
    .map((line) => line.map(([value, tone]) => (tone ? `<span class="${tone}">${escape(value)}</span>` : escape(value))).join(''))
    .join('\n');
}

function documentFor(page, log) {
  const hero = heroOf(page);
  const plain = plainTitle(hero, page);
  const path = page.slug === 'home' ? '' : `/${page.slug}`;

  return `<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><style>
@font-face { font-family: "Host Grotesk"; src: url("${fontUrl('host-grotesk.woff2')}") format("woff2"); font-weight: 300 800; }
@font-face { font-family: "Martian Mono"; src: url("${fontUrl('martian-mono.woff2')}") format("woff2"); font-weight: 100 800; }
* { box-sizing: border-box; margin: 0; }
html, body { width: 1200px; height: 630px; overflow: hidden; background: #07070a; }
.card { position: relative; width: 1200px; height: 630px; padding: 64px 72px; display: flex; flex-direction: column; justify-content: space-between; font-family: "Host Grotesk", sans-serif; color: #fff; overflow: hidden; }
.log { position: absolute; inset: -60px -80px auto -40px; font-family: "Martian Mono", monospace; font-size: 20px; line-height: 1.75; color: #3c4b66; white-space: pre; filter: blur(2.4px); opacity: 0.5; transform: rotate(-4deg); }
.log .k { color: #5b6e95; } .log .s { color: #7c8f4a; } .log .p { color: #6a4fb8; }
.card::after { content: ""; position: absolute; inset: 0; background: radial-gradient(85% 80% at 26% 62%, rgb(7 7 10 / 0.35) 0%, rgb(7 7 10 / 0.94) 75%); }
.logo, h1, .foot { position: relative; z-index: 1; }
.logo svg { display: block; width: 236px; height: auto; }
h1 { max-width: 1010px; font-size: ${sizeFor(plain)}px; font-weight: 650; line-height: 0.98; letter-spacing: -0.05em; text-wrap: balance; }
h1 em { font-style: normal; color: #c8f25a; }
.foot { display: flex; justify-content: space-between; align-items: center; font-size: 22px; color: #8d8d93; }
.foot strong { font-weight: 500; color: #c9c9c4; }
</style></head>
<body><div class="card">
<div class="log" aria-hidden="true">${logHtml(log)}\n${logHtml(log)}</div>
<div class="logo">${logo}</div>
<h1>${titleHtml(hero, page)}</h1>
<div class="foot"><span><strong>quatroventos.com.br</strong>${escape(path)}</span></div>
</div></body></html>`;
}

const server = await createServer({ root, configFile: false, logLevel: 'error', server: { middlewareMode: true, hmr: false }, appType: 'custom' });
let log;
try {
  ({ LOGS: { 'pt-BR': log } } = await server.ssrLoadModule('/resources/js/site/i18n/log.ts'));
} finally {
  await server.close();
}

mkdirSync(outDir, { recursive: true });
const work = mkdtempSync(join(tmpdir(), 'qv-og-'));
const files = readdirSync(pagesDir).filter((file) => file.endsWith('.json')).sort();
let count = 0;

try {
  for (const file of files) {
    const jsonPath = join(pagesDir, file);
    const page = JSON.parse(readFileSync(jsonPath, 'utf8'));

    if (only.length && !only.includes(page.slug)) {
      continue;
    }

    const html = join(work, `${page.slug}.html`);
    const png = join(work, `${page.slug}.png`);
    writeFileSync(html, documentFor(page, log));

    execFileSync(chrome, [
      '--headless=new',
      '--disable-gpu',
      '--hide-scrollbars',
      '--force-device-scale-factor=1',
      '--allow-file-access-from-files',
      '--virtual-time-budget=3000',
      '--window-size=1200,630',
      `--screenshot=${png}`,
      pathToFileURL(html).href,
    ], { stdio: 'ignore' });

    execFileSync('sips', ['-s', 'format', 'jpeg', '-s', 'formatOptions', '84', png, '--out', join(outDir, `${page.slug}.jpg`)], { stdio: 'ignore' });

    const hero = heroOf(page);
    const updated = {};
    for (const [key, value] of Object.entries(page)) {
      if (key === 'sections') {
        updated.og_image = `/assets/og/${page.slug}.jpg`;
        updated.og_image_alt = `${plainTitle(hero, page)} Quatro Ventos.`.replace(/([^.?!])\s+Quatro Ventos\.$/, '$1. Quatro Ventos.');
      }
      if (key !== 'og_image' && key !== 'og_image_alt') {
        updated[key] = value;
      }
    }
    writeFileSync(jsonPath, `${JSON.stringify(updated, null, 2)}\n`);
    count++;
  }
} finally {
  rmSync(work, { recursive: true, force: true });
}

console.log(`${count} imagens em public/assets/og`);
