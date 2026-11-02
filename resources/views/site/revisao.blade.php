<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Revisão do site</title>
  <style>
    :root {
      --bg: #07070a;
      --ink: #0b0b0f;
      --panel: #121217;
      --paper: #f4f4f1;
      --line: rgb(255 255 255 / 0.12);
      --soft: #c9c9c4;
      --mute: #8d8d93;
      --lime: #c8f25a;
      --white: #fff;
    }
    * { box-sizing: border-box; }
    html, body { margin: 0; height: 100%; background: var(--bg); color: var(--white); font: 15px/1.4 system-ui, -apple-system, "Segoe UI", sans-serif; }
    button, input { font: inherit; color: inherit; }
    .app { display: grid; grid-template-columns: 360px 1fr; height: 100%; }
    .side { display: flex; flex-direction: column; min-height: 0; border-right: 1px solid var(--line); background: var(--bg); }
    .side-head { padding: 18px 16px 12px; border-bottom: 1px solid var(--line); }
    .side-head h1 { margin: 0; font-size: 18px; font-weight: 650; letter-spacing: -0.03em; }
    .progress { margin: 6px 0 0; color: var(--soft); font-size: 13px; }
    .progress b { color: var(--lime); font-weight: 650; }
    .tools { display: flex; gap: 8px; margin-top: 12px; }
    .tools input { flex: 1; min-width: 0; height: 36px; padding: 0 12px; border: 1px solid var(--line); border-radius: 999px; background: var(--panel); }
    .filters { display: flex; gap: 6px; margin-top: 10px; }
    .filters button, .bar button { height: 32px; padding: 0 12px; border: 1px solid var(--line); border-radius: 999px; background: transparent; color: var(--soft); cursor: pointer; }
    .filters button.is-on, .bar button.is-on { background: var(--white); color: var(--ink); border-color: var(--white); }
    .list { overflow: auto; padding: 8px 8px 24px; }
    .group { margin: 14px 8px 6px; color: var(--mute); font-size: 12px; font-weight: 650; letter-spacing: 0.04em; text-transform: uppercase; }
    .row { display: grid; grid-template-columns: 22px 1fr; gap: 8px; align-items: start; width: 100%; margin: 0; padding: 8px; border: 0; border-radius: 12px; background: transparent; text-align: left; cursor: pointer; }
    .row:hover { background: rgb(255 255 255 / 0.05); }
    .row.is-current { background: var(--white); color: var(--ink); }
    .row.is-current .path, .row.is-current .flag { color: #6b6b72; }
    .check { width: 16px; height: 16px; margin-top: 2px; accent-color: #5f7a12; }
    .title { display: block; font-weight: 600; letter-spacing: -0.02em; }
    .path { display: block; margin-top: 2px; color: var(--mute); font: 12px/1.3 ui-monospace, "SF Mono", Menlo, monospace; }
    .flag { color: var(--lime); font-weight: 600; }
    .stage { display: flex; flex-direction: column; min-width: 0; min-height: 0; background: #1a1a1f; }
    .bar { display: flex; align-items: center; gap: 8px; height: 56px; padding: 0 12px; border-bottom: 1px solid var(--line); background: var(--bg); }
    .bar .addr { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--soft); font: 13px/1 ui-monospace, "SF Mono", Menlo, monospace; }
    .frame-wrap { flex: 1; min-height: 0; display: flex; justify-content: center; }
    iframe { width: 100%; height: 100%; border: 0; background: var(--paper); }
    .frame-wrap.is-1100 iframe { width: 1100px; }
    .frame-wrap.is-390 iframe { width: 390px; box-shadow: 0 0 0 1px var(--line); }
    .empty { margin: auto; color: var(--mute); }
    @media (max-width: 800px) {
      .app { grid-template-columns: 1fr; grid-template-rows: 42vh 1fr; }
      .side { border-right: 0; border-bottom: 1px solid var(--line); }
    }
  </style>
</head>
<body>
  <div class="app">
    <aside class="side">
      <div class="side-head">
        <h1>Revisão do site</h1>
        <p class="progress"><b id="done">0</b> de <b id="total">0</b> páginas publicadas marcadas</p>
        <div class="tools">
          <input id="q" type="search" placeholder="Filtrar por título ou URL" autocomplete="off">
        </div>
        <div class="filters" role="tablist">
          <button type="button" class="is-on" data-filter="page">Publicadas</button>
          <button type="button" data-filter="pending">Pendentes</button>
          <button type="button" data-filter="all">Tudo</button>
        </div>
      </div>
      <div class="list" id="list"></div>
    </aside>
    <section class="stage">
      <div class="bar">
        <button type="button" id="prev">Anterior</button>
        <button type="button" id="next">Próxima</button>
        <button type="button" id="mark">Marcar revisada</button>
        <span class="addr" id="addr">Escolha uma página</span>
        <button type="button" class="is-on" data-width="full">Largura</button>
        <button type="button" data-width="1100">1100</button>
        <button type="button" data-width="390">390</button>
        <button type="button" id="open">Abrir</button>
      </div>
      <div class="frame-wrap" id="wrap">
        <p class="empty" id="empty">A lista à esquerda abre a página aqui.</p>
        <iframe id="frame" title="Página em revisão" hidden></iframe>
      </div>
    </section>
  </div>
  <script>
    const GROUPS = [
      { label: 'Institucional', items: [
        { path: '/', title: 'Quatro Ventos | Soluções criativas com inteligência artificial', kind: 'page' },
        { path: '/servicos', title: 'Serviços de IA, software, web e infraestrutura', kind: 'page' },
        { path: '/cases', title: 'Cases e projetos', kind: 'page' },
        { path: '/sobre', title: 'Sobre a Quatro Ventos', kind: 'page' },
        { path: '/contato', title: 'Contato', kind: 'page' },
        { path: '/obrigado', title: 'Mensagem recebida', kind: 'page' },
      ]},
      { label: 'Inteligência artificial', items: [
        { path: '/inteligencia-artificial', title: 'Inteligência artificial para melhorar processos', kind: 'page' },
        { path: '/agentes-de-ia', title: 'Agentes de IA para atendimento, vendas e operação', kind: 'page' },
        { path: '/automacao-com-ia', title: 'Automação de processos com IA e n8n', kind: 'page' },
        { path: '/ia-com-dados-da-empresa', title: 'IA com os dados da empresa: RAG e base de conhecimento', kind: 'page' },
        { path: '/integracao-de-llm', title: 'Integração de ChatGPT, Claude e Gemini em sistemas', kind: 'page' },
        { path: '/sistemas-com-ia', title: 'Sistemas e produtos com IA embarcada', kind: 'page' },
      ]},
      { label: 'Desenvolvimento de software', items: [
        { path: '/desenvolvimento-de-software', title: 'Desenvolvimento de software sob medida', kind: 'page' },
        { path: '/software-sob-medida', title: 'Desenvolvimento de software sob medida e web apps', kind: 'page' },
        { path: '/desenvolvimento-de-saas', title: 'Desenvolvimento de SaaS', kind: 'page' },
        { path: '/modernizacao-de-sistemas', title: 'Modernização de sistemas legados e auditoria técnica', kind: 'page' },
        { path: '/erp-e-crm', title: 'Implantação e integração de ERP e CRM', kind: 'page' },
        { path: '/ui-ux-design', title: 'UI/UX design e discovery de produto', kind: 'page' },
        { path: '/qa-e-testes', title: 'QA e testes de software', kind: 'page' },
      ]},
      { label: 'Desenvolvimento web', items: [
        { path: '/desenvolvimento-web', title: 'Desenvolvimento web: sites, landing pages e e-commerce', kind: 'page' },
        { path: '/desenvolvimento-de-sites', title: 'Desenvolvimento de sites sob medida', kind: 'page' },
        { path: '/landing-pages', title: 'Desenvolvimento de landing pages', kind: 'page' },
        { path: '/desenvolvimento-de-ecommerce', title: 'Desenvolvimento de e-commerce', kind: 'page' },
        { path: '/sites-wordpress', title: 'Sites em WordPress', kind: 'page' },
        { path: '/seo-e-llms', title: 'SEO e otimização para LLMs', kind: 'page' },
      ]},
      { label: 'Suporte e infraestrutura', items: [
        { path: '/suporte-e-infraestrutura', title: 'Suporte, manutenção e infraestrutura', kind: 'page' },
        { path: '/sustentacao-de-software', title: 'Sustentação e suporte de software', kind: 'page' },
        { path: '/manutencao-de-sites', title: 'Manutenção de sites e WordPress', kind: 'page' },
        { path: '/recuperacao-de-site-wordpress', title: 'Recuperação de site WordPress invadido', kind: 'page' },
        { path: '/hospedagem-de-sites', title: 'Hospedagem de sites', kind: 'page' },
        { path: '/vps-e-infraestrutura', title: 'VPS e infraestrutura gerenciada', kind: 'page' },
      ]},
      { label: 'Conversão', items: [
        { path: '/diagnostico-de-site', title: 'Diagnóstico gratuito de site', kind: 'page' },
      ]},
      { label: 'Case publicado', items: [
        { path: '/cases/fiscal-hub', title: 'Fiscal Hub', kind: 'page' },
      ]},
      { label: 'Redirects 301', items: [
        { path: '/criacao-de-sites', title: 'Criação de sites', kind: 'redirect', note: '→ /desenvolvimento-de-sites' },
        { path: '/desenvolvimento-de-landing-page', title: 'Desenvolvimento de landing page', kind: 'redirect', note: '→ /landing-pages' },
        { path: '/criar-landing-page', title: 'Criar landing page', kind: 'redirect', note: '→ /landing-pages' },
        { path: '/criar-landing-pages', title: 'Criar landing pages', kind: 'redirect', note: '→ /landing-pages' },
        { path: '/criacao-de-landing-pages', title: 'Criação de landing pages', kind: 'redirect', note: '→ /landing-pages' },
        { path: '/desenvolvimento-de-sites-wordpress', title: 'Desenvolvimento de sites WordPress', kind: 'redirect', note: '→ /sites-wordpress' },
        { path: '/desenvolvimento-full-stack', title: 'Desenvolvimento full stack', kind: 'redirect', note: '→ /software-sob-medida' },
        { path: '/manutencao-de-sites-wordpress', title: 'Manutenção de sites WordPress', kind: 'redirect', note: '→ /manutencao-de-sites' },
        { path: '/porque-meu-site-nao-vende', title: 'Porque meu site não vende', kind: 'redirect', note: '→ /diagnostico-de-site' },
      ]},
      { label: 'Rascunho, não publica', items: [
        { path: '/consultoria-de-ia', title: 'Consultoria e diagnóstico de adoção de IA', kind: 'draft' },
        { path: '/blog', title: 'Blog', kind: 'draft' },
      ]},
      { label: 'Cases linkados na home, sem página', items: [
        { path: '/cases/assessment-produtive', title: 'Assessment Produtive', kind: 'missing' },
        { path: '/cases/alianca-francesa-brasilia', title: 'Aliança Francesa Brasília', kind: 'missing' },
        { path: '/cases/conexme', title: 'Conexme', kind: 'missing' },
        { path: '/cases/tripfy', title: 'Tripfy', kind: 'missing' },
        { path: '/cases/alianca-francesa-curitiba', title: 'Aliança Francesa Curitiba', kind: 'missing' },
        { path: '/cases/multiversidade-pucpr', title: 'Multiversidade PUCPR', kind: 'missing' },
      ]},
    ];

    const KEY = 'qv-revisao';
    const pages = GROUPS.flatMap((group) => group.items);
    const published = pages.filter((item) => item.kind === 'page');
    let reviewed = new Set(JSON.parse(localStorage.getItem(KEY) || '[]'));
    let filter = 'page';
    let current = null;

    const list = document.getElementById('list');
    const frame = document.getElementById('frame');
    const empty = document.getElementById('empty');
    const addr = document.getElementById('addr');
    const wrap = document.getElementById('wrap');

    function save() {
      localStorage.setItem(KEY, JSON.stringify([...reviewed]));
      const done = published.filter((item) => reviewed.has(item.path)).length;
      document.getElementById('done').textContent = String(done);
      document.getElementById('total').textContent = String(published.length);
    }

    function visible(item) {
      const q = document.getElementById('q').value.trim().toLowerCase();
      if (q && !`${item.title} ${item.path} ${item.note || ''}`.toLowerCase().includes(q)) {
        return false;
      }
      if (filter === 'page') return item.kind === 'page';
      if (filter === 'pending') return item.kind === 'page' && !reviewed.has(item.path);
      return true;
    }

    function render() {
      list.replaceChildren();
      GROUPS.forEach((group) => {
        const items = group.items.filter(visible);
        if (!items.length) return;
        const heading = document.createElement('div');
        heading.className = 'group';
        heading.textContent = group.label;
        list.appendChild(heading);
        items.forEach((item) => {
          const row = document.createElement('div');
          row.className = 'row' + (current === item.path ? ' is-current' : '');
          row.dataset.path = item.path;
          const check = document.createElement('input');
          check.type = 'checkbox';
          check.className = 'check';
          check.checked = reviewed.has(item.path);
          check.disabled = item.kind !== 'page';
          check.title = 'Marcar como revisada';
          check.addEventListener('click', (event) => {
            event.stopPropagation();
            if (check.checked) reviewed.add(item.path);
            else reviewed.delete(item.path);
            save();
            if (filter === 'pending') render();
          });
          const body = document.createElement('button');
          body.type = 'button';
          body.style.cssText = 'display:block;width:100%;padding:0;border:0;background:transparent;text-align:left;cursor:pointer;color:inherit;';
          const title = document.createElement('span');
          title.className = 'title';
          title.textContent = item.title;
          const path = document.createElement('span');
          path.className = 'path';
          path.textContent = item.note ? `${item.path}  ${item.note}` : item.path;
          if (item.kind !== 'page') {
            const flag = document.createElement('span');
            flag.className = 'flag';
            flag.textContent = item.kind === 'redirect' ? ' · redirect' : item.kind === 'draft' ? ' · rascunho' : ' · sem página';
            path.appendChild(flag);
          }
          body.append(title, path);
          body.addEventListener('click', () => open(item.path));
          row.append(check, body);
          list.appendChild(row);
        });
      });
      const selected = list.querySelector('.row.is-current');
      if (selected) selected.scrollIntoView({ block: 'nearest' });
    }

    function queue() {
      return published.filter(visible);
    }

    function open(path) {
      current = path;
      history.replaceState(null, '', '#' + path);
      empty.hidden = true;
      frame.hidden = false;
      frame.src = path;
      addr.textContent = path;
      render();
    }

    function move(step) {
      const items = queue();
      if (!items.length) return;
      const index = Math.max(0, items.findIndex((item) => item.path === current));
      const next = items[(index + step + items.length) % items.length];
      open(next.path);
    }

    document.getElementById('q').addEventListener('input', render);
    document.querySelectorAll('.filters button').forEach((button) => {
      button.addEventListener('click', () => {
        filter = button.dataset.filter;
        document.querySelectorAll('.filters button').forEach((item) => item.classList.toggle('is-on', item === button));
        render();
      });
    });
    document.querySelectorAll('[data-width]').forEach((button) => {
      button.addEventListener('click', () => {
        wrap.classList.remove('is-1100', 'is-390');
        if (button.dataset.width !== 'full') wrap.classList.add('is-' + button.dataset.width);
        document.querySelectorAll('[data-width]').forEach((item) => item.classList.toggle('is-on', item === button));
      });
    });
    document.getElementById('prev').addEventListener('click', () => move(-1));
    document.getElementById('next').addEventListener('click', () => move(1));
    document.getElementById('mark').addEventListener('click', () => {
      if (!current || !published.some((item) => item.path === current)) return;
      reviewed.add(current);
      save();
      const pending = published.filter((item) => !reviewed.has(item.path));
      if (pending.length) open(pending[0].path);
      else render();
    });
    document.getElementById('open').addEventListener('click', () => {
      if (current) window.open(current, '_blank', 'noopener');
    });
    frame.addEventListener('load', () => {
      try {
        const path = frame.contentWindow.location.pathname || current;
        addr.textContent = path;
      } catch {
        addr.textContent = current || '';
      }
    });
    document.addEventListener('keydown', (event) => {
      if (event.target.id === 'q' || event.metaKey || event.ctrlKey || event.altKey) return;
      if (event.key === 'ArrowDown') { event.preventDefault(); move(1); }
      if (event.key === 'ArrowUp') { event.preventDefault(); move(-1); }
    });

    save();
    const hash = decodeURIComponent(location.hash.replace(/^#/, ''));
    if (hash.startsWith('/')) open(hash);
    else render();
  </script>
</body>
</html>
