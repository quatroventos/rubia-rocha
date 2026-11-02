import './mockup-compose.js?v=1';

const Compose = globalThis.MockupCompose;
const { FINISH_DEFAULTS, detectScreen, imageSize } = Compose;
const config = JSON.parse(document.getElementById('mockup-studio').textContent);
const photo = document.getElementById('photo');
const cropCanvas = document.getElementById('crop');
const status = document.getElementById('status');
const ctx = photo.getContext('2d');
const cropCtx = cropCanvas.getContext('2d');

let base = null;
let composed = null;
let screenFit = null;
let print = null;
let crop = null;
let frames = [];
let busy = false;
let composeTimer = 0;
let busyTimer = 0;
let saved = false;
let screenLayer = null;
let finishFrame = 0;

const MAX_REFERENCES = 3;
const FINISH_STORAGE = 'mockup-studio-finish';
const finishing = loadFinishing();

bind();

function bind() {
    const fileInput = document.getElementById('prints');
    fileInput.addEventListener('change', (event) => {
        const file = event.target.files?.[0];
        if (file) {
            loadPrint(file);
        }
        fileInput.value = '';
    });
    bindDrop();
    document.addEventListener('paste', (event) => {
        const file = [...(event.clipboardData?.files || [])].find((item) => item.type.startsWith('image/'));
        if (file) {
            event.preventDefault();
            loadPrint(file);
        }
    });
    document.getElementById('replace-print').addEventListener('click', () => fileInput.click());
    document.getElementById('capture').addEventListener('click', capturePage);
    document.getElementById('page-url').addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            capturePage();
        }
    });
    document.getElementById('crop-start').addEventListener('input', applyVerticalCrop);
    document.getElementById('crop-end').addEventListener('input', applyVerticalCrop);
    document.getElementById('generate').addEventListener('click', generatePhoto);
    document.getElementById('export').addEventListener('click', exportPng);
    const coverAlt = document.getElementById('cover-alt');
    if (coverAlt && config.cover) {
        coverAlt.value = config.cover.alt || '';
        coverAlt.addEventListener('input', updateActions);
        document.getElementById('use-cover').addEventListener('click', saveCover);
    }
    document.getElementById('animate')?.addEventListener('click', animate);
    document.getElementById('record')?.addEventListener('click', record);
    document.getElementById('device').addEventListener('change', syncDevice);
    document.querySelectorAll('input[name="reference"]').forEach((input) => {
        input.addEventListener('change', updateActions);
    });
    bindFinishing();
    syncViewportHint();
    updateActions();
}

function loadFinishing() {
    try {
        const stored = JSON.parse(localStorage.getItem(FINISH_STORAGE) || '{}');
        return Object.fromEntries(Object.entries(FINISH_DEFAULTS).map(([key, fallback]) => {
            const number = Number(stored[key]);
            return [key, Number.isFinite(number) ? number : fallback];
        }));
    } catch {
        return { ...FINISH_DEFAULTS };
    }
}

function bindFinishing() {
    document.querySelectorAll('[data-finish]').forEach((input) => {
        input.value = String(finishing[input.dataset.finish]);
        input.addEventListener('input', () => {
            finishing[input.dataset.finish] = Number(input.value);
            refreshFinishing();
        });
    });
    document.getElementById('finish-reset').addEventListener('click', () => {
        Object.assign(finishing, FINISH_DEFAULTS);
        document.querySelectorAll('[data-finish]').forEach((input) => {
            input.value = String(finishing[input.dataset.finish]);
        });
        refreshFinishing();
    });
    syncFinishOutputs();
}

function syncFinishOutputs() {
    document.querySelectorAll('[data-finish]').forEach((input) => {
        const output = document.querySelector(`output[for="${input.id}"]`);
        if (output) {
            output.textContent = input.dataset.unit === 'deg' ? `${input.value}°` : `${input.value}%`;
        }
    });
}

function refreshFinishing() {
    syncFinishOutputs();
    try {
        localStorage.setItem(FINISH_STORAGE, JSON.stringify(finishing));
    } catch {
        // localStorage pode estar bloqueado no iframe.
    }
    if (!screenLayer) {
        return;
    }
    cancelAnimationFrame(finishFrame);
    finishFrame = requestAnimationFrame(() => {
        composed = finish();
        saved = false;
        render();
    });
}

function bindDrop() {
    const drop = document.getElementById('drop');
    ['dragenter', 'dragover'].forEach((type) => {
        drop.addEventListener(type, (event) => {
            event.preventDefault();
            drop.classList.add('is-over');
        });
    });
    ['dragleave', 'drop'].forEach((type) => {
        drop.addEventListener(type, () => drop.classList.remove('is-over'));
    });
    drop.addEventListener('drop', (event) => {
        event.preventDefault();
        const file = [...(event.dataTransfer?.files || [])].find((item) => item.type.startsWith('image/'));
        if (file) {
            loadPrint(file);
        } else {
            say('Solte um arquivo de imagem.', 'error');
        }
    });
}

function say(text, tone = '') {
    status.textContent = text;
    status.classList.toggle('is-visible', text !== '');
    status.classList.toggle('is-ok', tone === 'ok');
    status.classList.toggle('is-error', tone === 'error');
}

function loadPrint(file) {
    const image = new Image();
    image.onload = () => {
        URL.revokeObjectURL(image.src);
        adoptPrint(image);
        say(`Print em ${image.naturalWidth}×${image.naturalHeight}.`);
    };
    image.onerror = () => {
        say('Não consegui ler essa imagem.', 'error');
    };
    image.src = URL.createObjectURL(file);
}

function adoptPrint(image) {
    print = image;
    document.getElementById('crop-wrap').hidden = false;
    document.getElementById('crop-start').value = '0';
    document.getElementById('crop-end').value = '100';
    applyVerticalCrop();
    render();
    updateActions();
}

async function capturePage() {
    const url = value('page-url').trim();
    if (url === '') {
        say('Informe a URL da página.', 'error');
        document.getElementById('page-url').focus();
        return;
    }

    await run('Abrindo a página e limpando a tela…', async () => {
        const payload = await postJson(config.captureUrl, {
            url,
            device: value('device'),
            clean: document.getElementById('clean-capture').checked,
        });
        await new Promise((resolve, reject) => {
            const image = new Image();
            image.onload = () => {
                adoptPrint(image);
                resolve();
            };
            image.onerror = () => reject(new Error('A print voltou ilegível.'));
            image.src = payload.image;
        });
        say(`Print em ${print.naturalWidth}×${print.naturalHeight}.`, 'ok');
    });
}

function applyVerticalCrop() {
    if (!print) {
        return;
    }
    const startInput = document.getElementById('crop-start');
    const endInput = document.getElementById('crop-end');
    let start = Number(startInput.value);
    let end = Number(endInput.value);
    if (end < start + 1) {
        if (document.activeElement === endInput) {
            start = Math.max(0, end - 1);
            startInput.value = String(start);
        } else {
            end = Math.min(100, start + 1);
            endInput.value = String(end);
        }
    }
    document.getElementById('crop-start-label').textContent = `${start}%`;
    document.getElementById('crop-end-label').textContent = `${end}%`;
    const y = Math.round(print.naturalHeight * (start / 100));
    const bottom = Math.round(print.naturalHeight * (end / 100));
    crop = {
        x: 0,
        y,
        width: print.naturalWidth,
        height: Math.max(1, bottom - y),
    };
    paintCrop(start, end);
    scheduleCompose();
}

function paintCrop(start, end) {
    const scale = Math.min(672 / print.naturalWidth, 360 / print.naturalHeight, 1);
    cropCanvas.width = Math.max(1, Math.round(print.naturalWidth * scale));
    cropCanvas.height = Math.max(1, Math.round(print.naturalHeight * scale));
    cropCtx.drawImage(print, 0, 0, cropCanvas.width, cropCanvas.height);
    const top = cropCanvas.height * (start / 100);
    const bottom = cropCanvas.height * (end / 100);
    cropCtx.fillStyle = 'rgba(24, 34, 48, 0.62)';
    cropCtx.fillRect(0, 0, cropCanvas.width, top);
    cropCtx.fillRect(0, bottom, cropCanvas.width, cropCanvas.height - bottom);
}

async function generatePhoto() {
    const references = [...document.querySelectorAll('input[name="reference"]:checked')].map((input) => input.value);
    if (!print) {
        say('Envie ou capture a tela do projeto.', 'error');
        return;
    }
    if (references.length === 0) {
        say('Escolha ao menos uma referência.', 'error');
        return;
    }

    await run('Gerando a foto. Leva cerca de um minuto…', async () => {
        const payload = await postJson(config.generateUrl, {
            device: value('device'),
            angle: value('angle'),
            scene: value('scene'),
            size: value('size'),
            quality: value('quality'),
            references,
            note: value('note'),
        });
        const placed = await showPlate(payload.image);
        saved = false;
        notifyParent();
        if (placed) {
            say('Foto pronta, com a print encaixada.', 'ok');
        } else {
            say('A foto veio, mas não achei a tela magenta para encaixar a print. Gere de novo.', 'error');
        }
    });
}

async function animate() {
    if (!base) {
        say('Gere a foto antes de animar.', 'error');
        return;
    }

    const steps = Number(value('steps'));
    const motion = value('motion');
    frames = [await toPngBlob(composed || base)];
    paintStrip();

    await run('Animando…', async () => {
        let current = base;
        for (let step = 2; step <= steps; step += 1) {
            setBusyLabel(`Foto ${step} de ${steps}…`);
            const body = new FormData();
            body.append('frame', await toPngBlob(current), 'frame.png');
            body.append('motion', motion);
            body.append('device', value('device'));
            body.append('size', value('size'));
            body.append('quality', value('quality'));
            body.append('step', String(step));
            body.append('steps', String(steps));
            body.append('note', value('note'));
            const payload = await postForm(config.advanceUrl, body);
            await showPlate(payload.image);
            current = base;
            frames.push(await toPngBlob(composed || base));
            paintStrip();
        }
        render();
        const recordButton = document.getElementById('record');
        if (recordButton) {
            recordButton.disabled = frames.length < 2;
        }
        say(`${frames.length} fotos. Baixe o minivídeo.`, 'ok');
    });
}

function showPlate(dataUrl) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => {
            base = image;
            screenFit = null;
            resolve(recompose());
        };
        image.onerror = () => reject(new Error('A imagem voltou ilegível.'));
        image.src = dataUrl;
    });
}

function render() {
    const image = composed || base || (print ? cropPrint(print) : null);
    const tag = document.getElementById('stage-tag');
    if (!image) {
        photo.hidden = true;
        tag.hidden = true;
        document.getElementById('empty').hidden = false;
        return;
    }
    const size = imageSize(image);
    photo.width = size.width;
    photo.height = size.height;
    ctx.drawImage(image, 0, 0);
    photo.hidden = false;
    document.getElementById('empty').hidden = true;
    tag.hidden = false;
    tag.textContent = composed ? 'Mockup' : (base ? 'Foto sem a print' : 'Tela recortada');
    updateActions();
}

async function exportPng() {
    const image = composed || base;
    if (!image) {
        return;
    }
    download(await toPngBlob(image), 'mockup.png');
}

async function saveCover() {
    const image = composed || base;
    if (!image || !config.cover) {
        say('Gere o mockup antes de gravar a capa.', 'error');
        return;
    }

    const alt = document.getElementById('cover-alt').value.trim();
    if (alt === '') {
        say('Escreva o texto alternativo da capa.', 'error');
        document.getElementById('cover-alt').focus();
        return;
    }

    await run('Gravando a capa em WebP…', async () => {
        const body = new FormData();
        body.append('image', await toPngBlob(image), 'mockup.png');
        body.append('alt', alt);
        const payload = await postForm(config.cover.saveUrl, body);
        if (window.parent !== window) {
            window.parent.postMessage({
                type: 'case-mockup',
                path: payload.path,
                alt: payload.alt,
            }, location.origin);
        }
        saved = true;
        notifyParent();
        say('Capa gravada em WebP.', 'ok');
    });
}

function notifyParent() {
    if (window.parent !== window) {
        window.parent.postMessage({
            type: 'case-mockup-state',
            dirty: Boolean(base) && !saved,
        }, location.origin);
    }
}

function scheduleCompose() {
    clearTimeout(composeTimer);
    composeTimer = setTimeout(recompose, 80);
}

function recompose() {
    if (!base) {
        render();
        return false;
    }
    if (!print) {
        composed = null;
        screenLayer = null;
        render();
        return false;
    }
    screenFit = screenFit ?? detectScreen(base);
    if (!screenFit) {
        composed = null;
        screenLayer = null;
        render();
        return false;
    }
    screenLayer = buildScreenLayer(base, print, screenFit.quad);
    composed = finish();
    render();
    return true;
}

function buildScreenLayer(source, screen, quad) {
    return Compose.buildScreenLayer(source, cropPrint(screen), quad);
}

function finish() {
    if (!base || !screenLayer) {
        return null;
    }
    return Compose.finish(base, screenLayer, finishing);
}

function cropPrint(image) {
    return Compose.toCanvas(image, crop);
}

function paintStrip() {
    const strip = document.getElementById('strip');
    strip.replaceChildren();
    frames.forEach((blob) => {
        const image = document.createElement('img');
        image.src = URL.createObjectURL(blob);
        strip.append(image);
    });
}

async function record() {
    if (frames.length < 2) {
        return;
    }
    const seconds = Number(value('duration'));
    const images = await Promise.all(frames.map(loadBlob));
    const film = document.createElement('canvas');
    const width = Math.min(960, images[0].naturalWidth);
    const height = Math.round(images[0].naturalHeight * (width / images[0].naturalWidth));
    film.width = width;
    film.height = height;
    const filmCtx = film.getContext('2d');
    const stream = film.captureStream(30);
    const mime = ['video/mp4;codecs=avc1.42E01E', 'video/webm;codecs=vp9', 'video/webm;codecs=vp8', 'video/webm']
        .find((type) => MediaRecorder.isTypeSupported(type));
    if (!mime) {
        say('Este navegador não grava vídeo.', 'error');
        return;
    }
    const recorder = new MediaRecorder(stream, { mimeType: mime, videoBitsPerSecond: 4_000_000 });
    const chunks = [];
    recorder.ondataavailable = (event) => {
        if (event.data.size) {
            chunks.push(event.data);
        }
    };
    const done = new Promise((resolve) => {
        recorder.onstop = () => resolve(new Blob(chunks, { type: mime }));
    });
    recorder.start();
    const started = performance.now();
    await new Promise((resolve) => {
        const tick = (now) => {
            const progress = Math.min(1, (now - started) / (seconds * 1000));
            const index = Math.min(images.length - 1, Math.floor(progress * images.length));
            filmCtx.drawImage(images[index], 0, 0, width, height);
            if (progress < 1) {
                requestAnimationFrame(tick);
            } else {
                resolve();
            }
        };
        requestAnimationFrame(tick);
    });
    recorder.stop();
    download(await done, mime.includes('mp4') ? 'mockup.mp4' : 'mockup.webm');
}

function toPngBlob(source) {
    return new Promise((resolve) => {
        if (source instanceof HTMLCanvasElement) {
            source.toBlob((blob) => resolve(blob), 'image/png');
            return;
        }
        const canvas = document.createElement('canvas');
        canvas.width = source.naturalWidth;
        canvas.height = source.naturalHeight;
        canvas.getContext('2d').drawImage(source, 0, 0);
        canvas.toBlob((blob) => resolve(blob), 'image/png');
    });
}

async function run(label, action) {
    if (busy) {
        return;
    }
    busy = true;
    say('');
    setBusyLabel(label);
    const overlay = document.getElementById('busy');
    const clock = document.getElementById('busy-time');
    const started = Date.now();
    clock.textContent = '0 s';
    overlay.hidden = false;
    busyTimer = setInterval(() => {
        clock.textContent = `${Math.round((Date.now() - started) / 1000)} s`;
    }, 1000);
    document.querySelectorAll('button').forEach((button) => {
        button.disabled = true;
    });
    try {
        await action();
    } catch (error) {
        say(error.message, 'error');
    } finally {
        busy = false;
        clearInterval(busyTimer);
        overlay.hidden = true;
        document.querySelectorAll('button').forEach((button) => {
            button.disabled = false;
        });
        updateActions();
    }
}

function setBusyLabel(label) {
    document.getElementById('busy-label').textContent = label;
}

function updateActions() {
    if (busy) {
        return;
    }
    const result = composed || base;
    const references = document.querySelectorAll('input[name="reference"]:checked').length;
    document.querySelectorAll('input[name="reference"]').forEach((input) => {
        input.disabled = !input.checked && references >= MAX_REFERENCES;
    });
    document.getElementById('ref-count').textContent = `${references} de ${MAX_REFERENCES}`;

    let generateGate = '';
    if (!config.configured) {
        generateGate = 'Geração desligada: falta META_API_KEY.';
    } else if (!print) {
        generateGate = 'Falta a tela do projeto, no passo 1.';
    } else if (references === 0) {
        generateGate = 'Escolha ao menos uma referência.';
    }
    document.getElementById('generate').disabled = generateGate !== '';
    document.getElementById('generate-gate').textContent = generateGate;
    document.getElementById('generate').textContent = base ? 'Gerar outra foto' : 'Gerar foto';

    document.getElementById('export').disabled = !result;
    const coverButton = document.getElementById('use-cover');
    if (coverButton) {
        coverButton.disabled = !result || document.getElementById('cover-alt').value.trim() === '';
    }
    let saveGate = '';
    if (!result) {
        saveGate = 'Disponível depois de gerar a foto.';
    } else if (coverButton && coverButton.disabled) {
        saveGate = 'Escreva o texto alternativo para gravar a capa.';
    }
    document.getElementById('save-gate').textContent = saveGate;

    const animateButton = document.getElementById('animate');
    if (animateButton) {
        animateButton.disabled = !config.configured || !base;
    }
    const recordButton = document.getElementById('record');
    if (recordButton) {
        recordButton.disabled = frames.length < 2;
    }

    document.getElementById('step-screen').classList.toggle('is-done', Boolean(print));
    document.getElementById('step-photo').classList.toggle('is-done', Boolean(composed));
    document.getElementById('finish-fields').disabled = !screenLayer;
    document.getElementById('finish-gate').textContent = screenLayer ? '' : 'Disponível depois que a print entrar na foto.';
    document.getElementById('step-save').classList.toggle('is-done', saved);
}

function syncDevice() {
    const device = value('device');
    let picked = 0;
    document.querySelectorAll('input[name="reference"]').forEach((input) => {
        input.checked = input.dataset.device === device && picked < MAX_REFERENCES;
        if (input.checked) {
            picked += 1;
        }
    });
    const landscape = device === 'macbook' || device === 'studio-display';
    document.getElementById('size').value = landscape ? '2048x1152' : '1152x2048';
    syncViewportHint();
    recompose();
    updateActions();
}

function syncViewportHint() {
    const viewport = config.viewports?.[value('device')];
    document.getElementById('viewport-hint').textContent = viewport ? `A captura usa ${viewport.label}.` : '';
}

function value(id) {
    return document.getElementById(id).value;
}

function loadBlob(blob) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('Quadro ilegível.'));
        image.src = URL.createObjectURL(blob);
    });
}

function download(blob, name) {
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = name;
    link.click();
    URL.revokeObjectURL(link.href);
}

async function postJson(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify(body),
    });
    return readPayload(response);
}

async function postForm(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body,
    });
    return readPayload(response);
}

async function readPayload(response) {
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        const message = payload.message
            || Object.values(payload.errors || {}).flat()[0]
            || `Erro ${response.status}.`;
        throw new Error(message);
    }
    return payload;
}
