(() => {
    const FINISH_DEFAULTS = {
        brightness: 100,
        contrast: 100,
        saturation: 100,
        glare: 22,
        position: 30,
        angle: 120,
        spread: 40,
        shade: 18,
    };

    function toCanvas(image, region = null) {
        const size = imageSize(image);
        const area = region ?? { x: 0, y: 0, width: size.width, height: size.height };
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(area.width));
        canvas.height = Math.max(1, Math.round(area.height));
        canvas.getContext('2d').drawImage(image, area.x, area.y, area.width, area.height, 0, 0, canvas.width, canvas.height);
        return canvas;
    }

    function buildScreenLayer(source, print, quad) {
        const size = imageSize(source);
        const plateCanvas = document.createElement('canvas');
        plateCanvas.width = size.width;
        plateCanvas.height = size.height;
        const plateContext = plateCanvas.getContext('2d', { willReadFrequently: true });
        plateContext.drawImage(source, 0, 0);
        const plate = plateContext.getImageData(0, 0, size.width, size.height).data;
        const printData = print.getContext('2d', { willReadFrequently: true }).getImageData(0, 0, print.width, print.height);
        const inverse = homography(quad, [
            { x: 0, y: 0 },
            { x: print.width, y: 0 },
            { x: print.width, y: print.height },
            { x: 0, y: print.height },
        ]);
        const margin = screenMargin(quad);
        const box = {
            left: Math.max(0, Math.floor(Math.min(...quad.map((point) => point.x)) - margin)),
            top: Math.max(0, Math.floor(Math.min(...quad.map((point) => point.y)) - margin)),
            right: Math.min(size.width, Math.ceil(Math.max(...quad.map((point) => point.x)) + margin)),
            bottom: Math.min(size.height, Math.ceil(Math.max(...quad.map((point) => point.y)) + margin)),
        };
        const canvas = document.createElement('canvas');
        canvas.width = size.width;
        canvas.height = size.height;
        const context = canvas.getContext('2d', { willReadFrequently: true });
        const layer = context.createImageData(size.width, size.height);
        const pixels = layer.data;
        for (let y = box.top; y < box.bottom; y += 1) {
            for (let x = box.left; x < box.right; x += 1) {
                const offset = (y * size.width + x) * 4;
                if (!isChroma(plate[offset], plate[offset + 1], plate[offset + 2]) || !nearScreen(x + 0.5, y + 0.5, quad, margin)) {
                    continue;
                }
                const sample = apply(inverse, { x: x + 0.5, y: y + 0.5 });
                const color = samplePrint(printData.data, print.width, print.height, sample.x, sample.y);
                pixels[offset] = color[0];
                pixels[offset + 1] = color[1];
                pixels[offset + 2] = color[2];
                pixels[offset + 3] = 255;
            }
        }
        context.putImageData(layer, 0, 0);
        return { canvas, box, quad, original: layer };
    }

    function finish(base, layer, finishing) {
        const size = imageSize(base);
        const { box, quad } = layer;
        const width = box.right - box.left;
        const height = box.bottom - box.top;
        const out = document.createElement('canvas');
        out.width = size.width;
        out.height = size.height;
        const context = out.getContext('2d');
        context.drawImage(base, 0, 0);

        context.drawImage(tuneScreen(layer, width, height, finishing), box.left, box.top);

        const center = quad.reduce((acc, point) => ({ x: acc.x + point.x / 4, y: acc.y + point.y / 4 }), { x: 0, y: 0 });
        const reach = Math.max(...quad.map((point) => Math.hypot(point.x - center.x, point.y - center.y)));

        if (finishing.shade > 0) {
            const shade = maskedLayer(layer, width, height, (layerContext) => {
                const gradient = layerContext.createRadialGradient(
                    center.x - box.left, center.y - box.top, reach * 0.35,
                    center.x - box.left, center.y - box.top, reach,
                );
                gradient.addColorStop(0, 'rgba(0, 0, 0, 0)');
                gradient.addColorStop(1, `rgba(0, 0, 0, ${finishing.shade / 100 * 0.75})`);
                layerContext.fillStyle = gradient;
                layerContext.fillRect(0, 0, width, height);
            });
            context.drawImage(shade, box.left, box.top);
        }

        if (finishing.glare > 0) {
            const glare = maskedLayer(layer, width, height, (layerContext) => {
                const angle = finishing.angle * Math.PI / 180;
                const dx = Math.cos(angle) * reach;
                const dy = Math.sin(angle) * reach;
                const cx = center.x - box.left;
                const cy = center.y - box.top;
                const gradient = layerContext.createLinearGradient(cx - dx, cy - dy, cx + dx, cy + dy);
                const position = finishing.position / 100;
                const spread = Math.max(0.02, finishing.spread / 100 * 0.5);
                const peak = finishing.glare / 100 * 0.6;
                gradient.addColorStop(0, `rgba(255, 255, 255, ${peak * 0.18})`);
                gradient.addColorStop(clamp01(position - spread), `rgba(255, 255, 255, ${peak * 0.08})`);
                gradient.addColorStop(clamp01(position), `rgba(255, 255, 255, ${peak})`);
                gradient.addColorStop(clamp01(position + spread * 0.6), `rgba(255, 255, 255, ${peak * 0.12})`);
                gradient.addColorStop(1, 'rgba(255, 255, 255, 0)');
                layerContext.fillStyle = gradient;
                layerContext.fillRect(0, 0, width, height);
            });
            context.globalCompositeOperation = 'screen';
            context.drawImage(glare, box.left, box.top);
            context.globalCompositeOperation = 'source-over';
        }

        return out;
    }

    function tuneScreen(layer, width, height, finishing) {
        const { box, original } = layer;
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const context = canvas.getContext('2d');
        const region = context.createImageData(width, height);
        const target = region.data;
        const source = original.data;
        const stride = original.width;
        const brightness = finishing.brightness / 100;
        const contrast = finishing.contrast / 100;
        const saturation = finishing.saturation / 100;
        for (let y = 0; y < height; y += 1) {
            for (let x = 0; x < width; x += 1) {
                const from = ((y + box.top) * stride + (x + box.left)) * 4;
                const alpha = source[from + 3];
                if (alpha === 0) {
                    continue;
                }
                let r = source[from] * brightness;
                let g = source[from + 1] * brightness;
                let b = source[from + 2] * brightness;
                r = (r - 128) * contrast + 128;
                g = (g - 128) * contrast + 128;
                b = (b - 128) * contrast + 128;
                const gray = 0.2126 * r + 0.7152 * g + 0.0722 * b;
                const to = (y * width + x) * 4;
                target[to] = gray + (r - gray) * saturation;
                target[to + 1] = gray + (g - gray) * saturation;
                target[to + 2] = gray + (b - gray) * saturation;
                target[to + 3] = alpha;
            }
        }
        context.putImageData(region, 0, 0);
        return canvas;
    }

    function maskedLayer(layer, width, height, paint) {
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const context = canvas.getContext('2d');
        paint(context);
        context.globalCompositeOperation = 'destination-in';
        context.drawImage(layer.canvas, -layer.box.left, -layer.box.top);
        return canvas;
    }

    function clamp01(value) {
        return Math.min(1, Math.max(0, value));
    }

    function screenMargin(quad) {
        const span = Math.min(
            Math.hypot(quad[1].x - quad[0].x, quad[1].y - quad[0].y),
            Math.hypot(quad[2].x - quad[1].x, quad[2].y - quad[1].y),
        );
        return Math.max(4, span * 0.03);
    }

    function nearScreen(x, y, quad, margin) {
        if (pointInQuad(x, y, quad)) {
            return true;
        }
        for (let index = 0; index < quad.length; index += 1) {
            if (distanceToSegment(x, y, quad[index], quad[(index + 1) % quad.length]) <= margin) {
                return true;
            }
        }
        return false;
    }

    function distanceToSegment(x, y, start, end) {
        const dx = end.x - start.x;
        const dy = end.y - start.y;
        const length = dx * dx + dy * dy || 1;
        const t = Math.min(1, Math.max(0, ((x - start.x) * dx + (y - start.y) * dy) / length));
        return Math.hypot(x - (start.x + t * dx), y - (start.y + t * dy));
    }

    function pointInQuad(x, y, quad) {
        let sign = 0;
        for (let index = 0; index < quad.length; index += 1) {
            const start = quad[index];
            const end = quad[(index + 1) % quad.length];
            const cross = (end.x - start.x) * (y - start.y) - (end.y - start.y) * (x - start.x);
            if (cross === 0) {
                continue;
            }
            const next = cross > 0 ? 1 : -1;
            if (sign !== 0 && sign !== next) {
                return false;
            }
            sign = next;
        }
        return true;
    }

    function samplePrint(data, width, height, x, y) {
        const sx = Math.min(width - 1, Math.max(0, x));
        const sy = Math.min(height - 1, Math.max(0, y));
        const x0 = Math.floor(sx);
        const y0 = Math.floor(sy);
        const x1 = Math.min(width - 1, x0 + 1);
        const y1 = Math.min(height - 1, y0 + 1);
        const tx = sx - x0;
        const ty = sy - y0;
        const row = (py, px) => (py * width + px) * 4;
        const mix = (channel) => {
            const top = data[row(y0, x0) + channel] * (1 - tx) + data[row(y0, x1) + channel] * tx;
            const bottom = data[row(y1, x0) + channel] * (1 - tx) + data[row(y1, x1) + channel] * tx;
            return Math.round(top * (1 - ty) + bottom * ty);
        };
        return [mix(0), mix(1), mix(2)];
    }

    function isKeyPixel(r, g, b) {
        return r > 150 && b > 150 && g < 110 && Math.abs(r - b) < 80 && (r + b) > g * 3;
    }

    function isChroma(r, g, b) {
        return Math.min(r, b) > 60 && Math.min(r - g, b - g) > 12 && g < 245;
    }

    function detectScreen(image) {
        const size = imageSize(image);
        const max = 480;
        const scale = Math.min(1, max / size.width, max / size.height);
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(size.width * scale));
        canvas.height = Math.max(1, Math.round(size.height * scale));
        const context = canvas.getContext('2d', { willReadFrequently: true });
        context.drawImage(image, 0, 0, canvas.width, canvas.height);
        const data = context.getImageData(0, 0, canvas.width, canvas.height).data;
        const mask = new Uint8Array(canvas.width * canvas.height);
        for (let index = 0; index < mask.length; index += 1) {
            const offset = index * 4;
            mask[index] = isKeyPixel(data[offset], data[offset + 1], data[offset + 2]) ? 1 : 0;
        }
        const blob = largestBlob(mask, canvas.width, canvas.height);
        if (!blob || blob.length < 80) {
            return null;
        }
        const quad = fitQuad(blob).map((point) => ({ x: point.x / scale, y: point.y / scale }));
        if (quadArea(quad) < size.width * size.height * 0.04) {
            return null;
        }
        return { quad };
    }

    function fitQuad(points) {
        const corners = extremeCorners(points);
        const center = corners.reduce((acc, point) => ({
            x: acc.x + point.x / corners.length,
            y: acc.y + point.y / corners.length,
        }), { x: 0, y: 0 });
        const lines = corners.map((start, index) => {
            const end = corners[(index + 1) % corners.length];
            const edgeX = end.x - start.x;
            const edgeY = end.y - start.y;
            const length = Math.hypot(edgeX, edgeY) || 1;
            let normalX = -edgeY / length;
            let normalY = edgeX / length;
            const midX = (start.x + end.x) / 2;
            const midY = (start.y + end.y) / 2;
            if ((midX - center.x) * normalX + (midY - center.y) * normalY < 0) {
                normalX *= -1;
                normalY *= -1;
            }
            let best = -Infinity;
            const candidates = [];
            points.forEach((point) => {
                const along = ((point.x - start.x) * edgeX + (point.y - start.y) * edgeY) / (length * length);
                if (along < 0.18 || along > 0.82) {
                    return;
                }
                const outward = (point.x - midX) * normalX + (point.y - midY) * normalY;
                candidates.push({ point, outward });
                if (outward > best) {
                    best = outward;
                }
            });
            const selected = candidates.filter((item) => item.outward >= best - 1.5).map((item) => item.point);
            if (selected.length < 6) {
                return { point: { x: midX, y: midY }, dir: { x: edgeX / length, y: edgeY / length } };
            }
            return fitLine(selected);
        });
        const quad = [
            intersectLines(lines[3], lines[0]),
            intersectLines(lines[0], lines[1]),
            intersectLines(lines[1], lines[2]),
            intersectLines(lines[2], lines[3]),
        ];
        const plausible = quad.every((point, index) => point && Math.hypot(point.x - corners[index].x, point.y - corners[index].y) < 48);
        return plausible ? quad : corners;
    }

    function fitLine(points) {
        const origin = points.reduce((acc, point) => ({
            x: acc.x + point.x / points.length,
            y: acc.y + point.y / points.length,
        }), { x: 0, y: 0 });
        let xx = 0;
        let xy = 0;
        let yy = 0;
        points.forEach((point) => {
            const dx = point.x - origin.x;
            const dy = point.y - origin.y;
            xx += dx * dx;
            xy += dx * dy;
            yy += dy * dy;
        });
        const angle = 0.5 * Math.atan2(2 * xy, xx - yy);
        return {
            point: origin,
            dir: { x: Math.cos(angle), y: Math.sin(angle) },
        };
    }

    function intersectLines(first, second) {
        const cross = first.dir.x * second.dir.y - first.dir.y * second.dir.x;
        if (Math.abs(cross) < 1e-6) {
            return null;
        }
        const dx = second.point.x - first.point.x;
        const dy = second.point.y - first.point.y;
        const distance = (dx * second.dir.y - dy * second.dir.x) / cross;
        return {
            x: first.point.x + first.dir.x * distance,
            y: first.point.y + first.dir.y * distance,
        };
    }

    function imageSize(image) {
        return {
            width: image.naturalWidth || image.width,
            height: image.naturalHeight || image.height,
        };
    }

    function homography(from, to) {
        const sourceFrame = similarity(from);
        const targetFrame = similarity(to);
        const rows = [];
        from.forEach((point, index) => {
            const source = apply(sourceFrame, point);
            const target = apply(targetFrame, to[index]);
            rows.push([source.x, source.y, 1, 0, 0, 0, -target.x * source.x, -target.x * source.y, target.x]);
            rows.push([0, 0, 0, source.x, source.y, 1, -target.y * source.x, -target.y * source.y, target.y]);
        });
        return multiply(invertSimilarity(targetFrame), multiply(solve(rows), sourceFrame));
    }

    function similarity(points) {
        const center = points.reduce((acc, point) => ({
            x: acc.x + point.x / points.length,
            y: acc.y + point.y / points.length,
        }), { x: 0, y: 0 });
        const scale = points.reduce((sum, point) => sum + Math.hypot(point.x - center.x, point.y - center.y), 0) / points.length || 1;
        return [
            1 / scale, 0, -center.x / scale,
            0, 1 / scale, -center.y / scale,
            0, 0, 1,
        ];
    }

    function invertSimilarity(matrix) {
        const scale = 1 / matrix[0];
        return [scale, 0, -matrix[2] * scale, 0, scale, -matrix[5] * scale, 0, 0, 1];
    }

    function multiply(left, right) {
        const out = new Array(9);
        for (let row = 0; row < 3; row += 1) {
            for (let column = 0; column < 3; column += 1) {
                out[row * 3 + column] = left[row * 3] * right[column]
                    + left[row * 3 + 1] * right[column + 3]
                    + left[row * 3 + 2] * right[column + 6];
            }
        }
        return out;
    }

    function solve(rows) {
        const matrix = rows.map((row) => row.slice());
        const size = 8;
        for (let column = 0; column < size; column += 1) {
            let pivot = column;
            for (let row = column + 1; row < size; row += 1) {
                if (Math.abs(matrix[row][column]) > Math.abs(matrix[pivot][column])) {
                    pivot = row;
                }
            }
            [matrix[column], matrix[pivot]] = [matrix[pivot], matrix[column]];
            const scale = matrix[column][column] || 1e-9;
            for (let index = column; index <= size; index += 1) {
                matrix[column][index] /= scale;
            }
            for (let row = 0; row < size; row += 1) {
                if (row === column) {
                    continue;
                }
                const factor = matrix[row][column];
                for (let index = column; index <= size; index += 1) {
                    matrix[row][index] -= factor * matrix[column][index];
                }
            }
        }
        return matrix.map((row) => row[size]).concat(1);
    }

    function apply(matrix, point) {
        const weight = matrix[6] * point.x + matrix[7] * point.y + matrix[8];
        return {
            x: (matrix[0] * point.x + matrix[1] * point.y + matrix[2]) / weight,
            y: (matrix[3] * point.x + matrix[4] * point.y + matrix[5]) / weight,
        };
    }

    function largestBlob(mask, width, height) {
        const seen = new Uint8Array(mask.length);
        let best = null;
        for (let start = 0; start < mask.length; start += 1) {
            if (!mask[start] || seen[start]) {
                continue;
            }
            const stack = [start];
            const points = [];
            seen[start] = 1;
            while (stack.length) {
                const index = stack.pop();
                const x = index % width;
                const y = Math.floor(index / width);
                points.push({ x, y });
                [[1, 0], [-1, 0], [0, 1], [0, -1]].forEach(([dx, dy]) => {
                    const nextX = x + dx;
                    const nextY = y + dy;
                    const next = nextY * width + nextX;
                    if (nextX < 0 || nextY < 0 || nextX >= width || nextY >= height || !mask[next] || seen[next]) {
                        return;
                    }
                    seen[next] = 1;
                    stack.push(next);
                });
            }
            if (!best || points.length > best.length) {
                best = points;
            }
        }
        return best;
    }

    function extremeCorners(points) {
        const pick = (sx, sy) => points.reduce((best, point) => {
            const score = point.x * sx + point.y * sy;
            const bestScore = best.x * sx + best.y * sy;
            return score > bestScore ? point : best;
        });
        return [
            pick(-1, -1),
            pick(1, -1),
            pick(1, 1),
            pick(-1, 1),
        ];
    }

    function quadArea(quad) {
        const sum = quad.reduce((area, point, index) => {
            const next = quad[(index + 1) % quad.length];
            return area + (point.x * next.y) - (next.x * point.y);
        }, 0);
        return Math.abs(sum) / 2;
    }

    function loadImage(src) {
        return new Promise((resolve, reject) => {
            const image = new Image();
            image.onload = () => resolve(image);
            image.onerror = () => reject(new Error('Imagem ilegível.'));
            image.src = src;
        });
    }

    /**
     * Composição sem interface, usada pelo servidor no Chrome headless. Devolve o PNG em data URL ou null quando a foto não tem tela magenta.
     */
    async function render(sceneSrc, printSrc, finishing = {}) {
        const [scene, print] = await Promise.all([loadImage(sceneSrc), loadImage(printSrc)]);
        const fit = detectScreen(scene);
        if (!fit) {
            return null;
        }
        const layer = buildScreenLayer(scene, toCanvas(print), fit.quad);
        return finish(scene, layer, { ...FINISH_DEFAULTS, ...finishing }).toDataURL('image/png');
    }

    globalThis.MockupCompose = {
        FINISH_DEFAULTS,
        toCanvas,
        detectScreen,
        buildScreenLayer,
        finish,
        imageSize,
        render,
    };
})();
