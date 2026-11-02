<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mockup studio</title>
    <style>
        :root {
            --canvas: #f7f8fb;
            --surface: #ffffff;
            --soft: #fbfcfd;
            --ink: #182230;
            --muted: #5f6b7b;
            --faint: #8a94a3;
            --line: #e9edf2;
            --line-strong: #dfe5ec;
            --brand: #ed725d;
            --brand-hover: #df604e;
            --brand-soft: #fff0ec;
            --ok: #2f8a62;
            --ok-soft: #eaf8f1;
            --error: #b42318;
            --error-soft: #fef3f2;
            --stage: #15171b;
            --font: Arial, Helvetica, sans-serif;
            color-scheme: light;
        }
        * { box-sizing: border-box; }
        ::selection { background: var(--brand-soft); color: var(--ink); }
        html, body { height: 100%; }
        body { margin: 0; background: var(--canvas); color: var(--ink); font: 13px/1.45 var(--font); }
        button, input, select, textarea { font: inherit; color: inherit; }
        main { display: grid; grid-template-columns: minmax(0, 1fr) 380px; height: 100vh; }

        .stage { position: relative; display: grid; grid-template-rows: minmax(0, 1fr) auto; min-height: 0; background: var(--stage); }
        .stage-view { position: relative; display: grid; place-items: center; min-height: 0; padding: 28px; overflow: hidden; }
        #photo { display: block; max-width: 100%; max-height: 100%; width: auto; height: auto; border-radius: 6px; box-shadow: 0 18px 50px rgb(0 0 0 / 0.35); }
        #photo[hidden] { display: none; }
        .stage-tag { position: absolute; top: 16px; left: 16px; padding: 5px 10px; border-radius: 999px; background: rgb(255 255 255 / 0.08); color: #c7ccd4; font-size: 11px; font-weight: 700; }
        .stage-tag[hidden] { display: none; }
        .empty { max-width: 340px; text-align: center; color: #aab1bc; }
        .empty[hidden] { display: none; }
        .empty strong { display: block; margin-bottom: 10px; color: #eef0f3; font-size: 15px; }
        .empty ol { margin: 0; padding: 0; list-style: none; display: grid; gap: 6px; font-size: 12px; }
        .busy { position: absolute; inset: 0; display: grid; place-items: center; background: rgb(21 23 27 / 0.72); backdrop-filter: blur(3px); color: #eef0f3; text-align: center; }
        .busy[hidden] { display: none; }
        .busy p { margin: 12px 0 0; font-weight: 700; }
        .busy small { display: block; margin-top: 4px; color: #aab1bc; font-variant-numeric: tabular-nums; }
        .spinner { width: 28px; height: 28px; margin: 0 auto; border: 3px solid rgb(255 255 255 / 0.18); border-top-color: var(--brand); border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .strip { display: flex; gap: 8px; overflow-x: auto; padding: 12px 16px; border-top: 1px solid rgb(255 255 255 / 0.08); }
        .strip:empty { display: none; }
        .strip img { height: 64px; border-radius: 4px; }

        aside { display: flex; flex-direction: column; min-height: 0; border-left: 1px solid var(--line); background: var(--surface); }
        .panel { flex: 1; overflow: auto; padding: 22px 22px 8px; }
        .head h1 { margin: 0; font-size: 18px; font-weight: 700; letter-spacing: -0.02em; }
        .head p { margin: 4px 0 0; color: var(--muted); font-size: 12px; }
        .notice { margin: 16px 0 0; padding: 10px 12px; border-radius: 8px; background: var(--error-soft); color: var(--error); font-size: 12px; font-weight: 700; }

        .step { padding: 18px 0; border-bottom: 1px solid var(--line); }
        .step:last-of-type { border-bottom: 0; }
        .step-head { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
        .step-num { display: grid; place-items: center; width: 22px; height: 22px; border-radius: 50%; background: var(--canvas); border: 1px solid var(--line-strong); color: var(--muted); font-size: 11px; font-weight: 700; flex-shrink: 0; }
        .step.is-done .step-num { background: var(--ok); border-color: var(--ok); color: #fff; }
        .step-head h2 { margin: 0; font-size: 14px; font-weight: 700; }
        .step-head span.meta { margin-left: auto; color: var(--faint); font-size: 11px; font-weight: 700; }

        .field { display: grid; gap: 6px; margin-bottom: 12px; }
        .field > span, .label { font-size: 11px; font-weight: 700; color: #5c6878; }
        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .grid2 .field { margin-bottom: 0; }
        input[type="url"], input[type="text"], select, textarea {
            width: 100%; padding: 9px 11px; border: 1px solid var(--line-strong); border-radius: 8px; background: var(--soft); font-size: 12px; outline: none;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }
        input:focus, select:focus, textarea:focus { border-color: #c5cbd3; box-shadow: 0 0 0 3px #eef0f3; background: #fff; }
        textarea { min-height: 64px; resize: vertical; }
        :focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }
        input:focus-visible, select:focus-visible, textarea:focus-visible { outline: 0; }

        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 36px; padding: 0 14px; border-radius: 9px; border: 1px solid transparent; font-size: 12px; font-weight: 700; cursor: pointer; transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease; }
        .btn svg { width: 15px; height: 15px; flex-shrink: 0; }
        .btn-primary { background: var(--brand); color: #fff; }
        .btn-primary:hover { background: var(--brand-hover); }
        .btn-secondary { background: #fff; color: #4b5666; border-color: var(--line-strong); }
        .btn-secondary:hover { color: var(--ink); background: #fafbfc; }
        .btn-block { width: 100%; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .gate { margin: 8px 0 0; color: var(--faint); font-size: 11px; }
        .gate:empty { display: none; }

        .drop { display: grid; place-items: center; gap: 4px; padding: 18px 12px; border: 1px dashed #cfd6df; border-radius: 10px; background: var(--soft); color: var(--muted); text-align: center; cursor: pointer; transition: border-color 0.15s ease, background 0.15s ease; }
        .drop:hover, .drop.is-over { border-color: var(--brand); background: var(--brand-soft); }
        .drop svg { width: 20px; height: 20px; color: var(--faint); }
        .drop strong { color: var(--ink); font-size: 12px; }
        .drop small { font-size: 11px; }
        .or { display: flex; align-items: center; gap: 10px; margin: 12px 0; color: var(--faint); font-size: 11px; }
        .or::before, .or::after { content: ""; flex: 1; height: 1px; background: var(--line); }
        .inline { display: flex; gap: 8px; }
        .inline input { flex: 1; min-width: 0; }
        .check { display: flex; align-items: center; gap: 8px; margin-top: -2px; color: var(--muted); font-size: 12px; cursor: pointer; }
        .check input { width: 15px; height: 15px; margin: 0; accent-color: var(--brand); }
        .hint { margin: 6px 0 0; color: var(--faint); font-size: 11px; }
        .hint:empty { display: none; }

        .crop { margin-top: 14px; }
        .crop[hidden] { display: none; }
        .crop-view { display: grid; place-items: center; padding: 8px; border-radius: 8px; background: var(--canvas); border: 1px solid var(--line); }
        #crop { display: block; max-width: 100%; max-height: 180px; border-radius: 3px; }
        .crop-row { display: grid; grid-template-columns: 44px 1fr 38px; align-items: center; gap: 8px; margin-top: 8px; font-size: 11px; color: #5c6878; font-weight: 700; }
        .crop-row output { text-align: right; color: var(--faint); font-variant-numeric: tabular-nums; }
        input[type="range"] { width: 100%; margin: 0; accent-color: var(--brand); }
        .finish { margin: 0; padding: 0; border: 0; min-width: 0; }
        .finish:disabled { opacity: 0.45; }
        .finish .label { margin: 12px 0 2px; }
        .finish .label:first-child { margin-top: 0; }
        .slider-row { display: grid; grid-template-columns: 112px 1fr 40px; align-items: center; gap: 10px; min-height: 28px; font-size: 12px; color: var(--muted); }
        .slider-row output { text-align: right; color: var(--faint); font-size: 11px; font-variant-numeric: tabular-nums; }
        .link { padding: 0; border: 0; background: none; color: var(--brand); font-size: 11px; font-weight: 700; cursor: pointer; }

        .refs { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .ref { position: relative; display: block; cursor: pointer; }
        .ref input { position: absolute; opacity: 0; pointer-events: none; }
        .ref img { display: block; width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: 8px; border: 2px solid transparent; outline: 1px solid var(--line); transition: border-color 0.15s ease, opacity 0.15s ease; opacity: 0.72; }
        .ref span { display: block; margin-top: 4px; color: var(--muted); font-size: 10.5px; line-height: 1.25; }
        .ref:hover img { opacity: 1; }
        .ref input:checked + img { border-color: var(--brand); outline-color: transparent; opacity: 1; }
        .ref input:checked ~ span { color: var(--ink); font-weight: 700; }
        .ref input:focus-visible + img { box-shadow: 0 0 0 3px var(--brand-soft); }
        .ref .tick { position: absolute; top: 6px; right: 6px; display: none; width: 18px; height: 18px; border-radius: 50%; background: var(--brand); color: #fff; }
        .ref .tick svg { width: 18px; height: 18px; }
        .ref input:checked ~ .tick { display: block; }
        .ref input:disabled + img { opacity: 0.35; cursor: not-allowed; }

        .actions { display: grid; gap: 8px; }
        .status { display: none; align-items: flex-start; gap: 8px; margin: 0 22px 16px; padding: 10px 12px; border-radius: 8px; background: var(--canvas); color: var(--muted); font-size: 12px; white-space: pre-wrap; }
        .status.is-visible { display: flex; }
        .status.is-ok { background: var(--ok-soft); color: var(--ok); font-weight: 700; }
        .status.is-error { background: var(--error-soft); color: var(--error); font-weight: 700; }

        @media (prefers-reduced-motion: reduce) { .spinner { animation-duration: 2.4s; } * { transition: none !important; } }
        @media (max-width: 900px) {
            main { grid-template-columns: 1fr; height: auto; }
            .stage { min-height: 60vh; }
            aside { border-left: 0; border-top: 1px solid var(--line); }
        }
    </style>
</head>
<body>
<main>
    <section class="stage" aria-label="Prévia">
        <div class="stage-view">
            <canvas id="photo" hidden></canvas>
            <span class="stage-tag" id="stage-tag" hidden></span>
            <div class="empty" id="empty">
                <strong>A prévia aparece aqui</strong>
                <ol>
                    <li>1. Envie ou capture a tela do projeto.</li>
                    <li>2. Escolha o aparelho e gere a foto.</li>
                    <li>3. Ajuste brilho e reflexo da tela.</li>
                    <li>4. Grave como capa.</li>
                </ol>
            </div>
            <div class="busy" id="busy" hidden role="status" aria-live="polite">
                <div>
                    <div class="spinner" aria-hidden="true"></div>
                    <p id="busy-label">Gerando a foto…</p>
                    <small id="busy-time">0 s</small>
                </div>
            </div>
        </div>
        <div class="strip" id="strip"></div>
    </section>

    <aside>
        <div class="panel">
            <div class="head">
                <h1>Mockup studio</h1>
                <p>A IA gera o aparelho com a tela em magenta, e a print do projeto entra no lugar, já na perspectiva da foto.</p>
            </div>
            @unless ($studio['configured'])
                <p class="notice">A geração está desligada: falta META_API_KEY no .env.</p>
            @endunless

            <section class="step" id="step-screen">
                <div class="step-head">
                    <span class="step-num">1</span>
                    <h2>Tela do projeto</h2>
                </div>
                <label class="drop" id="drop">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/></svg>
                    <strong>Enviar print</strong>
                    <small>Clique, arraste a imagem ou cole com ⌘V</small>
                    <input id="prints" type="file" accept="image/*" hidden>
                </label>
                <div class="or">ou</div>
                <label class="field" for="page-url">
                    <span>Capturar pela URL</span>
                    <div class="inline">
                        <input id="page-url" type="url" inputmode="url" placeholder="https://" autocomplete="off">
                        <button class="btn btn-secondary" id="capture" type="button">Capturar tela</button>
                    </div>
                </label>
                <label class="check">
                    <input id="clean-capture" type="checkbox" checked>
                    <span>Fechar avisos de cookies, popups e anúncios fixos</span>
                </label>
                <p class="hint" id="viewport-hint"></p>

                <div class="crop" id="crop-wrap" hidden>
                    <div class="crop-view"><canvas id="crop"></canvas></div>
                    <div class="crop-row">
                        <label for="crop-start">Topo</label>
                        <input id="crop-start" type="range" min="0" max="100" value="0">
                        <output id="crop-start-label" for="crop-start">0%</output>
                    </div>
                    <div class="crop-row">
                        <label for="crop-end">Base</label>
                        <input id="crop-end" type="range" min="0" max="100" value="100">
                        <output id="crop-end-label" for="crop-end">100%</output>
                    </div>
                    <p class="hint">A largura entra inteira. Os sliders cortam só na vertical.</p>
                    <button class="link" id="replace-print" type="button">Trocar a print</button>
                </div>
            </section>

            <section class="step" id="step-photo">
                <div class="step-head">
                    <span class="step-num">2</span>
                    <h2>Foto</h2>
                </div>
                <div class="grid2">
                    <label class="field">
                        <span>Dispositivo</span>
                        <select id="device">
                            @foreach ($studio['devices'] as $option)
                                <option value="{{ $option['id'] }}" @selected($option['id'] === 'macbook')>{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">
                        <span>Ângulo</span>
                        <select id="angle">
                            @foreach ($studio['angles'] as $option)
                                <option value="{{ $option['id'] }}" @selected($option['id'] === 'three-quarter-right')>{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <label class="field" style="margin-top: 10px">
                    <span>Cenário</span>
                    <select id="scene">
                        @foreach ($studio['scenes'] as $option)
                            <option value="{{ $option['id'] }}" @selected($option['id'] === 'rail')>{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="grid2">
                    <label class="field">
                        <span>Tamanho</span>
                        <select id="size">
                            @foreach ($studio['sizes'] as $option)
                                <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">
                        <span>Qualidade</span>
                        <select id="quality">
                            @foreach ($studio['qualities'] as $option)
                                <option value="{{ $option['id'] }}" @selected($option['id'] === 'high')>{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="field" style="margin-top: 14px">
                    <div class="step-head" style="margin: 0">
                        <span class="label">Referências de estilo</span>
                        <span class="meta" id="ref-count"></span>
                    </div>
                    <div class="refs">
                        @foreach ($studio['references'] as $reference)
                            <label class="ref">
                                <input type="checkbox" name="reference" value="{{ $reference['id'] }}" data-device="{{ $reference['device'] }}" @checked($reference['device'] === 'macbook')>
                                <img src="{{ $reference['src'] }}" alt="">
                                <span>{{ $reference['label'] }}</span>
                                <i class="tick" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M7 12.5l3.2 3.2L17 9"/></svg></i>
                            </label>
                        @endforeach
                    </div>
                </div>

                <label class="field">
                    <span>Nota para a IA (opcional)</span>
                    <textarea id="note" maxlength="400" placeholder="Luz mais dura, aparelho mais perto da câmera"></textarea>
                </label>
                <button class="btn btn-primary btn-block" id="generate" type="button">Gerar foto</button>
                <p class="gate" id="generate-gate"></p>
            </section>

            <section class="step" id="step-finish">
                <div class="step-head">
                    <span class="step-num">3</span>
                    <h2>Acabamento da tela</h2>
                    <button class="link" id="finish-reset" type="button" style="margin-left: auto">Restaurar</button>
                </div>
                <fieldset class="finish" id="finish-fields" disabled>
                    <p class="label">Imagem</p>
                    @foreach ([['brightness', 'Brilho', 50, 150], ['contrast', 'Contraste', 50, 150], ['saturation', 'Saturação', 0, 150]] as [$key, $label, $min, $max])
                        <div class="slider-row">
                            <label for="finish-{{ $key }}">{{ $label }}</label>
                            <input id="finish-{{ $key }}" type="range" min="{{ $min }}" max="{{ $max }}" data-finish="{{ $key }}">
                            <output for="finish-{{ $key }}"></output>
                        </div>
                    @endforeach
                    <p class="label">Reflexo do vidro</p>
                    @foreach ([['glare', 'Intensidade', 0, 100, ''], ['position', 'Posição', 0, 100, ''], ['angle', 'Ângulo', 0, 360, 'deg'], ['spread', 'Largura', 5, 100, '']] as [$key, $label, $min, $max, $unit])
                        <div class="slider-row">
                            <label for="finish-{{ $key }}">{{ $label }}</label>
                            <input id="finish-{{ $key }}" type="range" min="{{ $min }}" max="{{ $max }}" data-finish="{{ $key }}" @if ($unit) data-unit="{{ $unit }}" @endif>
                            <output for="finish-{{ $key }}"></output>
                        </div>
                    @endforeach
                    <p class="label">Luz</p>
                    <div class="slider-row">
                        <label for="finish-shade">Sombra nas bordas</label>
                        <input id="finish-shade" type="range" min="0" max="100" data-finish="shade">
                        <output for="finish-shade"></output>
                    </div>
                </fieldset>
                <p class="gate" id="finish-gate"></p>
            </section>

            <section class="step" id="step-save">
                <div class="step-head">
                    <span class="step-num">4</span>
                    <h2>{{ $studio['cover'] ? 'Capa do case' : 'Exportar' }}</h2>
                </div>
                @if ($studio['cover'])
                    <label class="field">
                        <span>Texto alternativo da capa</span>
                        <input id="cover-alt" type="text" maxlength="255" autocomplete="off">
                    </label>
                @endif
                <div class="actions">
                    @if ($studio['cover'])
                        <button class="btn btn-primary btn-block" id="use-cover" type="button">Usar como capa</button>
                    @endif
                    <button class="btn btn-secondary btn-block" id="export" type="button">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v12M7 11l5 5 5-5M4 20h16"/></svg>
                        Baixar PNG
                    </button>
                </div>
                <p class="gate" id="save-gate"></p>
            </section>

            @if ($studio['runway'])
            <section class="step" id="step-motion">
                <div class="step-head">
                    <span class="step-num">5</span>
                    <h2>Animação</h2>
                    <span class="meta">Opcional</span>
                </div>
                <label class="field">
                    <span>Movimento</span>
                    <select id="motion">
                        <option value="turn">Girar</option>
                        <option value="open" selected>Abrir</option>
                    </select>
                </label>
                <div class="grid2">
                    <label class="field">
                        <span>Passos</span>
                        <select id="steps">
                            <option value="4">4</option>
                            <option value="6" selected>6</option>
                            <option value="8">8</option>
                        </select>
                    </label>
                    <label class="field">
                        <span>Duração</span>
                        <select id="duration">
                            <option value="3">3 s</option>
                            <option value="4" selected>4 s</option>
                            <option value="6">6 s</option>
                        </select>
                    </label>
                </div>
                <div class="actions" style="margin-top: 12px">
                    <button class="btn btn-primary btn-block" id="animate" type="button">Animar mockup</button>
                    <button class="btn btn-secondary btn-block" id="record" type="button" disabled>Baixar minivídeo</button>
                </div>
            </section>
            @endif
        </div>
        <div class="status" id="status" role="status" aria-live="polite"></div>
    </aside>
</main>
<script id="mockup-studio" type="application/json">@json($studio)</script>
<script type="module" src="/tools/mockup-studio.js?v=20"></script>
</body>
</html>
