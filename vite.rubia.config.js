import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

const projectRoot = fileURLToPath(new URL('.', import.meta.url));
const publicRoot = path.join(projectRoot, 'public');

const TYPES = {
    '.css': 'text/css; charset=utf-8',
    '.jpg': 'image/jpeg',
    '.jpeg': 'image/jpeg',
    '.js': 'text/javascript; charset=utf-8',
    '.png': 'image/png',
    '.svg': 'image/svg+xml',
    '.webp': 'image/webp',
    '.woff2': 'font/woff2',
};

function rubiaAssets() {
    return {
        name: 'rubia-assets',
        configureServer(server) {
            server.middlewares.use(servePublic);
        },
        configurePreviewServer(server) {
            server.middlewares.use(servePublic);
        },
    };
}

function servePublic(request, response, next) {
    const url = (request.url ?? '').split('?')[0];

    if (url === '/') {
        response.statusCode = 302;
        response.setHeader('Location', '/estudio/');
        response.end();

        return;
    }

    if (!url.startsWith('/rubia/')) {
        next();

        return;
    }

    const relative = decodeURIComponent(url).replace(/^\/+/, '');

    if (relative.includes('..')) {
        next();

        return;
    }

    const file = path.join(publicRoot, relative);

    if (!file.startsWith(publicRoot) || !fs.existsSync(file) || !fs.statSync(file).isFile()) {
        next();

        return;
    }

    response.setHeader('Content-Type', TYPES[path.extname(file)] ?? 'application/octet-stream');
    fs.createReadStream(file).pipe(response);
}

export default defineConfig({
    root: path.join(projectRoot, 'rubia'),
    publicDir: false,
    base: '/estudio/',
    plugins: [react(), rubiaAssets()],
    server: {
        host: '127.0.0.1',
        port: 5174,
        strictPort: true,
    },
    preview: {
        host: '127.0.0.1',
        port: 5174,
        strictPort: true,
    },
    build: {
        outDir: path.join(projectRoot, 'public/estudio'),
        emptyOutDir: true,
    },
});
