# Lia — Direção de arte e sistema de UX

Brief para reproduzir o estilo visual do painel CMS (ou qualquer superfície administrativa). Este documento descreve o produto **como ele é**, não uma versão idealizada. Copie o arquivo inteiro para o agente implementador.

Fonte de verdade no código: `apps/frontend/src/app/globals.css` + `apps/frontend/src/lib/branding-theme.ts`.

---

## 1. Posicionamento

Painel CMS. O usuário vive horas dentro da interface. O design não é marketing, não é dashboard de startup neon, não é admin genérico de SaaS americano.

**Personalidade da marca**

- Calorosa, mas operacional.
- Confiante sem ser agressiva.
- Densidade de ferramenta, acabamento de produto premium.
- Português brasileiro direto. Sem copy de landing page dentro do app.

**Metáfora visual**

Escritório contemporâneo à beira da praia no fim da tarde: navy profundo no mobiliário (sidebar), coral vivo no gesto humano (ação, destaque, marca), papel quase branco na mesa (canvas). O coral é o “toque de pessoa”. O navy é o “sistema que segura a operação”.

**Três palavras que o CMS precisa herdar**

1. **Quiet luxury operacional** — superfícies brancas, linhas quase invisíveis, sombra mínima. Nada grita.
2. **Coral como verbo** — a cor de marca só aparece quando algo acontece: CTA, item ativo, badge de urgência, underline de tab, send.
3. **Tipografia apertada e compacta** — letter-spacing negativo nos títulos, labels minúsculos em uppercase, números grandes e curtos.

Se o CMS parecer “Material Design + indigo” ou “Filament default + sky-500”, está errado. Se parecer Linear/Notion com um coral pontual e uma sidebar marinho, está no caminho.

---

## 2. Tese de design (regras que não negociar)

1. **O fundo nunca é branco puro na página.** Canvas = `#f7f8fb`. Branco é privilégio de card, modal, topbar e chat.
2. **Bordas estruturam; sombras só elevam.** Quase tudo usa `1px solid var(--line)`. Sombra é fumaça, não drop-shadow de 2014.
3. **Radius médio, nunca pílula em bloco.** Cards 13px, botões 9px, inputs 8–10px, chips e avatars sim usam 999px.
4. **Um acento por região.** Sidebar: navy + coral no ativo. Conteúdo: coral no CTA. Status: paleta semântica (verde/roxo/azul/ouro). Não misture os três papéis no mesmo elemento.
5. **Ação primária é única.** Um `primary-button` por header de página. O resto é secondary, text ou icon.
6. **Densidade alta, não apertada.** Texto 9–13px na UI operacional. Títulos 21–28px. Nunca 16px de body “confortável” em tabela.
7. **Ícones Lucide, 14–18px, stroke fino.** Sem emoji. Sem ilustração decorativa em tela de CRUD.
8. **Motion é 180ms ease, 1px de translateY no hover do primary.** Sem bounce, sem fade teatral, sem skeleton rainbow.
9. **pt-BR, sentence case, sem ponto final em botão.** “Adicionar contato”, não “ADICIONAR CONTATO” nem “Add Contact”.
10. **White-label existe, mas a geometria não muda.** Reseller troca `--brand`, `--coral`, `--navy` e logo. Radius, type scale, densidade e layout ficam.

---

## 3. Tokens — copiar literalmente

### 3.1 Superfícies e tinta

```css
:root {
    --canvas: #f7f8fb; /* fundo da aplicação */
    --surface: #ffffff; /* cards, painéis, modais */
    --surface-soft: #fbfbfd; /* card interno, hover suave, form nested */
    --ink: #182230; /* texto principal */
    --ink-muted: #7d8797; /* subtítulos, descrições */
    --ink-faint: #a6afbc; /* meta, timestamps, placeholders */
    --line: #e9edf2; /* borda padrão */
    --line-strong: #dfe5ec; /* input, search, ênfase de borda */
}
```

Neutros auxiliares que aparecem no CSS (não são tokens nomeados, mas façam parte da paleta):

| Uso                          | Hex                   |
| ---------------------------- | --------------------- |
| Texto secondary em botão     | `#5c6878`             |
| Texto de célula de tabela    | `#778392`             |
| Placeholder                  | `#b6bec9` / `#b2bac5` |
| Ícone muted                  | `#8d98a7` / `#a7b2c2` |
| Hover de linha               | `#fcfcfd`             |
| Fundo de lista/inbox lateral | `#fcfcfd`             |
| Fundo de coluna de kanban    | `#f4f6f8`             |
| Fundo de input               | `#fbfcfd`             |
| Input disabled               | `#f4f6f8`             |
| Track de meter/progress      | `#eef1f5`             |

### 3.2 Marca e acentos

```css
:root {
    --navy: #14253d; /* sidebar, bubble do agente, “hoje” na agenda */
    --coral: #ed725d; /* ação, marca, ativo, send */
    --coral-soft: #fff0ec;
    --purple: #7768db; /* inteligência / IA / insight */
    --purple-soft: #f0efff;
    --green: #4ca87f; /* sucesso, conectado, toggle on */
    --green-soft: #eaf8f1;
    --blue: #4d8edc; /* info, estágio “novo” */
    --blue-soft: #edf5ff;
    --gold: #c8903b; /* atenção, timer, warning quente */
    --gold-soft: #fff6e5;
}
```

Branding runtime (white-label):

```css
--brand: #ed725d; /* default Lia = coral. Reseller pode ser #7c3aed */
--brand-hover: mix(brand, black, 16%);
--brand-shadow: accent + 17% alpha; /* ex: rgba(237,114,93,.17) */
--accent: mesmo que coral no default;
```

Default de reseller em `branding-theme.ts`: primary `#7c3aed`, sidebar `#14253d`, accent `#ed725d`. No produto Lia puro, o botão primário cai no coral. **No CMS, use coral como primary**, não o roxo de reseller.

### 3.3 Sombra

```css
--shadow-sm:
    0 1px 2px rgba(22, 35, 53, 0.035), 0 5px 18px rgba(22, 35, 53, 0.025);
--shadow-md:
    0 10px 30px rgba(22, 35, 53, 0.08), 0 2px 8px rgba(22, 35, 53, 0.04);
```

Exceções pontuais (não virar token genérico):

- Modal: `0 22px 70px rgba(13, 27, 46, .23)`
- Login card: `0 22px 60px rgba(20, 37, 61, .08), 0 4px 14px rgba(20, 37, 61, .035)`
- Dropdown/overflow: `0 14px 32px rgba(15, 23, 42, .14)`
- Notification tray: `0 18px 40px rgba(15, 23, 42, .14)`
- Primary button: `0 5px 12px rgba(237, 114, 93, .17)`

A sombra é **fria e azulada** (navy no canal RGB: 22,35,53). Nunca sombra preta pura `rgba(0,0,0,.2)`.

### 3.4 Radius

| Superfície                                           | Radius                                                      |
| ---------------------------------------------------- | ----------------------------------------------------------- |
| Modal                                                | 15px                                                        |
| Hero / login card                                    | 14–22px (login card 22px desktop, 17px mobile)              |
| Panel / stat-card / knowledge-card                   | 13px                                                        |
| Sidebar nav item, workspace switcher, help-card      | 10–12px                                                     |
| Botão primary/secondary, icon-button, input de login | 9–10px                                                      |
| Input de form, select, chip interno                  | 8px                                                         |
| Badge compacto (não pill)                            | 5px                                                         |
| Avatar                                               | 50% (círculo)                                               |
| Workspace avatar (sidebar)                           | 9px (quase square-round, não círculo)                       |
| Toggle, meter, chip de filtro                        | 999px                                                       |
| Bolha de mensagem                                    | 12px, com canto inferior “cortado” em 4px no lado da origem |

### 3.5 Espaçamento

Sistema implícito de 2px, mas a UI opera nestes ritmos:

- **App chrome:** sidebar padding `24px 14px 16px`; topbar `0 34px`; content `30px 34px 50px`.
- **Gap de grid:** 13–16px entre cards; 8–10px em toolbars.
- **Panel header:** `19px 20px 10px`.
- **Célula de tabela:** `14px 18px` (td), `13px 18px` (th).
- **Modal:** header `24px 24px 16px`, fields `14px 24px 0`, footer `22px 24px 24px`.
- **Mobile:** topbar/content caem para `18px`.

Não use escala Tailwind 4/8 genérica se isso inflar a densidade. Prefira os números acima.

---

## 4. Tipografia

**Família:** `Arial, Helvetica, sans-serif`. Sem Inter, sem Poppins, sem Geist. O produto é deliberadamente system-adjacent. No CMS, **Arial/Helvetica**. Se o stack exigir webfont, use uma grotesk neutra (Inter só se for obrigatório do framework) e **compense com tracking negativo e pesos altos**, senão fica “admin genérico”.

**Base:** `14px` no `body`. Quase nenhum componente operacional usa 14px. A hierarquia real é menor.

### Escala

| Papel                    | Size                                            | Weight  | Tracking              | Cor                                 |
| ------------------------ | ----------------------------------------------- | ------- | --------------------- | ----------------------------------- |
| Login visual H1          | `clamp(36px, 4.6vw, 58px)`                      | 700+    | `-0.06em`             | `#fff`, em em coral `#f5a091`       |
| Login heading H2         | 28px                                            | 700     | `-0.055em`            | ink                                 |
| Section H2               | 25px                                            | 760     | `-0.055em`            | ink                                 |
| Topbar H1                | 21px                                            | 750     | `-0.04em`             | ink                                 |
| Modal H2                 | 20px                                            | 700     | `-0.04em`             | ink                                 |
| KPI número               | 22px (stat) / 20px (funnel) / 42px (usage hero) | 700     | `-0.04em` a `-0.06em` | ink                                 |
| Panel H3                 | 13px                                            | 750     | 0                     | ink                                 |
| Botão                    | 12px                                            | 750     | 0                     | —                                   |
| Nav item                 | 12px                                            | 600     | 0                     | `#aab5c5` / `#fff` ativo            |
| Body de descrição        | 12px                                            | 400     | 0                     | ink-muted                           |
| Label de campo / eyebrow | 10px                                            | 800     | `.12em` no eyebrow    | coral (eyebrow) / `#707c8c` (label) |
| TH de tabela             | 9px                                             | 800     | `.05em`               | `#a0a9b5` uppercase                 |
| TD / meta / badge        | 9–11px                                          | 650–750 | 0 ou `.02em`          | muted                               |
| Eyebrow                  | 10px                                            | 800     | `.12em`               | coral, uppercase                    |

Pesos 650, 750, 760, 800 aparecem no CSS. Em engines que não interpolam variable font, mapeie: 650→600, 750/760→700, 800→800.

**Eyebrow** é o carimbo de seção: `10px / 800 / letter-spacing .12em / uppercase / coral`. Sempre acima do H2. Exemplos reais: `Operação`, `Relacionamento`, `Pipeline`, `Base de conhecimento`, `Bem-vindo de volta`.

**Números:** `font-variant-numeric: tabular-nums` em créditos, horários de agenda, meters.

**Código / tokens / hex:** `ui-monospace, SFMono-Regular, Menlo, Consolas`.

---

## 5. Arquitetura de layout

O app é um **frame de ferramenta**, não um site com scroll de documento.

```
┌ app-frame (100dvh, overflow hidden, canvas)
│  ├ workspace-banner (opcional, full-bleed)
│  └ app-shell (flex row)
│     ├ sidebar 250px, navy, coluna
│     │    brand
│     │    workspace switcher
│     │    nav agrupada por labels
│     │    help-card
│     │    profile
│     └ main-shell
│          ├ topbar 82px, branco 82% + blur 12px
│          └ content-scroll OU inbox-layout (edge-to-edge)
```

**Sidebar 250px** (220px < 1180px). Fundo `--navy`. Texto claro. Não usa logo colorido sobre navy: wordmark branco + bubble coral rotacionada 8°.

**Topbar** não é um header pesado. É título + subtítulo à esquerda, ações à direita (usage badge, sino, logout). Fundo `rgba(255,255,255,.82)` + `backdrop-filter: blur(12px)` + borda inferior `--line`. Altura 82px (70px no mobile).

**Duas famílias de página**

1. **Páginas de gestão** (contatos, agentes, catálogo, conta): `content-scroll` com padding, `SectionHeader`, toolbar, `panel`.
2. **Páginas de canvas** (inbox, editor de fluxo, agenda): ocupam o resto da viewport, sem padding externo, divididas em colunas com bordas `--line`.

O CMS deve ter as duas famílias. Listagens de conteúdo = família 1. Editores visuais (blocos, preview) = família 2.

### Agrupamento de navegação

Labels da sidebar: `10px / 800 / .12em / uppercase / rgba(255,255,255,.34)`. Grupos reais: **Operação**, **Configuração**, **Revenda**. Item ativo: fundo `rgba(255,255,255,.12)` + **inset bar coral de 3px à esquerda**. Hover: `rgba(255,255,255,.07)`, texto branco. Ícone 18px, gap 12px, min-height 40px, radius 10px.

Não use highlight full-pill colorido. O ativo é “vidro + traço coral”.

### Breakpoints

| Largura  | Comportamento                                                                                                                            |
| -------- | ---------------------------------------------------------------------------------------------------------------------------------------- |
| ≤ 1180px | Sidebar 220px; inbox esconde painel de detalhes; FAB para detalhes                                                                       |
| ≤ 900px  | Sidebar vira drawer (`translateX(-105%)`), scrim `rgba(13,24,42,.35)`, botão menu no topbar; grids de 2 colunas viram 1                  |
| ≤ 680px  | Inbox vira stack lista/chat; stats 2 colunas; tabelas scroll horizontal `min-width: 650px`; section header empilha e CTA vira full-width |
| ≤ 820px  | Login deixa de ser split e vira stacked (visual 270px + form)                                                                            |
| ≤ 420px  | Login ainda mais compacto                                                                                                                |

Drawer da sidebar **não** usa overlay-nav estilo mobile web. É o mesmo navy, deslizando.

---

## 6. Cor semântica — o sistema de “tons”

O produto classifica entidades com **tones**, não com badges cinza infinitos.

### Avatares (iniciais, círculo)

| Tone   | Texto     | Fundo     | Uso               |
| ------ | --------- | --------- | ----------------- |
| coral  | `#d25f4e` | `#ffe1da` | default, conversa |
| blue   | `#3d74be` | `#dcecff` | novo              |
| mint   | `#368b69` | `#d8f2e5` | handoff           |
| violet | `#7666c5` | `#e8e3ff` | qualificado       |
| gold   | `#ad772c` | `#ffedc7` | perdido / atenção |
| navy   | `#fff`    | `#49627e` | sistema           |

Tamanhos: sm 32px / md 35px / lg 55px. Weight 800, tracking `-0.05em`.

### Badges (radius 5px, 9px, weight 750)

Não são pills, salvo health/severity.

| Classe  | Texto     | Fundo           |
| ------- | --------- | --------------- |
| neutral | `#8793a1` | `#eff2f5`       |
| green   | `#3a916b` | `--green-soft`  |
| orange  | `#bb7030` | `#fff2e2`       |
| purple  | `#6b5bc3` | `--purple-soft` |
| blue    | `#4d80be` | `--blue-soft`   |
| cold    | `#3b6ea5` | `#e8f1fb`       |
| warm    | `#bb7030` | `#fff2e2`       |
| hot     | `#c0453a` | `#ffe8e4`       |

### Health / severity (estes sim são pills 999px)

| Estado              | Texto     | Fundo     |
| ------------------- | --------- | --------- |
| healthy / ok        | `#166534` | `#bbf7d0` |
| attention / warning | `#92400e` | `#fde68a` |
| critical            | `#991b1b` | `#fecaca` |
| inactive            | `#475569` | `#e2e8f0` |
| info                | `#1e40af` | `#bfdbfe` |

Linha de tabela herda um wash: critical `#fff7f6` / `#fef2f2`, attention `#fffdf5` / `#fffbeb`, info `#eff6ff`. Não pinte a linha inteira de vermelho sólido.

### Temperatura comercial

Frio / morno / quente usam cold / warm / hot acima. É um sistema de **calor**, não de tráfego (não use vermelho/amarelo/verde semântico de pipeline aqui — o funil tem dots próprios).

### Funil (dots 7px)

`#eeb270` orange · `#75a7e5` blue · `#9b91e9` purple · `#75c59e` green.

### Ícones tinted (stat, activity, flow node)

Sempre **cor no ícone + soft no fundo**, square-round 8–12px, nunca círculo colorido sólido:

- coral / coral-soft
- purple / purple-soft
- green / green-soft
- blue / blue-soft
- gold / gold-soft

Esse par “ink da família + wash da família” é o DNA cromático do produto. Replique em todo CMS: tipo de conteúdo, status de publicação, autor, etc.

### Papel das cores (não confundir)

- **Coral** = marca + ação + “agora”.
- **Navy** = chrome, autoridade, mensagem do agente, “hoje”.
- **Purple** = inteligência, insight, IA, hint box (`#f9f7ff` / `--purple-soft`, texto `#5649bf` / `#5b548f`).
- **Green** = conectado, publicado, toggle on, sucesso.
- **Gold** = espera, timer, warning não-bloqueante.
- **Blue** = informação, entrada, estágio inicial.

---

## 7. Componentes — especificação para o CMS

### 7.1 Botões

**Primary**

- min-height 36px, padding `0 14px`, radius 9px, gap 7px
- 12px / 750 / branco
- background `var(--brand, var(--coral))`
- shadow `0 5px 12px rgba(237,114,93,.17)`
- hover: darken (~`#df604e`) + `translateY(-1px)`
- ícone 16–17px à esquerda do label, ou `ArrowRight` 16px à direita em auth
- login submit: height 48px, radius 10px, 13px, full width

**Secondary**

- mesma caixa do primary
- branco, borda `--line`, texto `#5c6878`
- hover: ink, `--line-strong`, fundo `#fafbfc`
- sem sombra

**Text button**

- sem caixa, coral, 11px / 800, gap 5px
- danger: `#c0453a` → hover `#9b2c24`

**Danger**

- mesma caixa do primary, fundo `#c44b4b`, hover `#b03f3f`
- não usar coral para destruir. Coral é vida; vermelho queimado é dano.

**Icon button**

- 34px (30px em row-actions, 27–28px em composer/chat)
- borda `--line`, radius 9px, fundo surface, ícone `#8d98a7`
- hover: ink, `#f8f9fb`
- danger hover: `#c0453a`, borda `#f0c4c0`, fundo `#fff7f5`

Disabled: `opacity: .5`, `cursor: not-allowed`. Sem “grey out” de cor diferente.

### 7.2 Campos

Label **acima** do input, não floating.

```
LABEL  10px / 800 / #707c8c   (login: 11px / #657283)
INPUT  margin-top 7px
       padding 10px 11px
       border 1px --line-strong
       radius 8px
       bg #fbfcfd
       11px ink
FOCUS  border #c2bbed
       box-shadow 0 0 0 3px #f0eeff
HINT   10px / 500 / ink-faint, margin-top 6px
ERROR  10px / 600 / #b94e40, margin-top 6px
```

O anel de foco é **lavanda**, não coral. Isso é intencional: o coral é ação; o foco é “estou aqui”. Não troque por outline coral.

Input com ícone (login): height 48px, radius 10px, ícone 17px `#9aa6b5`, focus-within `border #b9b0ed` + `0 0 0 4px #f0eeff` + bg branco.

Select: same field, chevron SVG inline cinza, `appearance: none`.

Checkbox: `accent-color: var(--coral)`, 14px.

Toggle: 38×22, track `#d9dee6`, knob branco 16px, on = `--green` (não coral). O verde no toggle significa “ligado/saudável”.

### 7.3 Feedback de form

```
.form-error    border #ffd3cc  bg #fff4f1  text #b94e40  radius 9px  11px
.form-success  border #c9ebd4  bg #f1fbf4  text #1f7a45  radius 9px  11px
```

Banner de plano/info roxo: `#f7f5ff` / `#ddd7fb` / `#5649bf`.
Banner de silêncio/offline: `#fff8ec` / `#f0dfc2` / `#8b6b3a`.

Não use toast genérico no canto se o padrão da tela é inline no topo do form/modal.

### 7.4 Panel / card

```
border 1px --line
radius 13px
bg --surface
box-shadow --shadow-sm
```

Header do panel: H3 13/750 + description 10px muted. Ações no canto (select-button 10px, radius 7px).

Stat card: min-height 116px, padding 18px, ícone 39×39 radius 11px no par tone/soft, label 11/600 muted, valor 22px tracking -0.04em, change 10px verde `#48a278`.

Selected card (mode, catalog item, knowledge): borda coral + wash `#fff7f5` (ou coral-soft + ring `0 0 0 3px rgba(237,114,93,.08)`). Esse “selected wash coral” é o estado ativo de qualquer lista de escolha.

Empty state: dashed `--line-strong`, radius 12px, fundo `#fbfcfd`, ícone 42px no soft da família (purple ou coral), título 13 ink, texto 11 muted max-width ~280–320px. Sem ilustração.

### 7.5 Tabelas

- Dentro de `.panel.table-panel`, overflow hidden (scroll no mobile).
- TH uppercase 9px/800 tracking .05em `#a0a9b5`, borda `--line`.
- TD 10px `#778392`, borda `#f0f2f5`.
- Hover de row `#fcfcfd`.
- Primeira coluna é **pessoa**: avatar + strong 11px ink + span 9px `#a1aab6`.
- Coluna de ações: ícones 30px alinhados à direita, overflow menu 188px+.
- Empty: padding 40px, muted, frase curta. “Nenhum contato encontrado.”

Não zebrar. Não usar header cinza sólido. Não sticky thead colorido.

### 7.6 Modal

- Backdrop `rgba(11,24,42,.48)` + blur 4px. Navy no overlay, nunca preto.
- Card branco, radius 15px, borda `rgba(255,255,255,.5)` (halo), sombra profunda.
- Largura default 460px; wide 720px; confirm ~440px.
- Header: eyebrow + H2 20px + p 11px muted + icon-button fechar.
- Footer: ações à direita, secondary à esquerda do primary, gap 8px.
- Não full-screen no desktop. No mobile, radius 12px e padding 18px.

### 7.7 Tabs

Duas espécies:

1. **Underline (inbox, editor):** texto 11–12px/700 `#a0a9b5`, ativo ink + barra coral 2px radius 3px colada embaixo. Sem fundo.
2. **Chip / segmented (agenda mode, usage month):** track `#f7f9fc` radius 10px, item ativo branco com sombra `0 1px 3px rgba(15,30,50,.08)`. Month chip ativo: borda `#f0c2b8`, fundo `#fff7f5`.

Nunca tabs estilo browser com fundo cinza no ativo.

### 7.8 Chips e busca

Search de toolbar: height 36px, radius 8px, ícone Search 16px, placeholder 10–13px.
Search de lista densa (inbox): height 33px, radius 7px, 10px.
Chips de filtro: height 32px, pill, 11px/700 `#64748b`, ativo indigo-lavanda `#eef2ff` / `#3730a3` / borda `#c7d2fe` — único momento em que o produto usa indigo de “filtro”, não coral. Respeite: **filtro ≠ ação**.

### 7.9 Dropdown / overflow

Branco, radius 10–12px, borda `--line`, sombra md. Item 8px 10px, radius 7px, hover `#f5f7fa`. Danger item `#c0453a` hover `#fff5f3`. Na sidebar (workspace menu): fundo `#1a2b42`, hover branco 8% — o menu herda o chrome navy, não o canvas.

### 7.10 Notificações

Trigger 36px, borda `#d9e2ef`. Badge count: 18px pill `#ef4444` (este vermelho é **alerta de sistema**, não coral), borda branca 2px, 10px/800. Panel 380px, radius 14px. Itens com wash de severity (ver §6).

### 7.11 Composer / inputs compostos

Bloco branco, borda `--line-strong`, radius 11px, sombra `0 4px 12px rgba(32,47,69,.04)`. Send: coral, 10px/800, height 29px, radius 7px. Ferramentas são icon-buttons sem borda.

---

## 8. Padrões de página para o CMS

Mapeie cada tela do CMS para um padrão Lia. Não invente um terceiro.

### Padrão A — Listagem de conteúdo (Contatos)

```
SectionHeader
  eyebrow + H2 + description | primary-button “Novo …”
Toolbar
  search | chips | sort
Panel.table
  pessoa | status badge | meta | row-actions
```

Use para posts, páginas, mídia, usuários, taxonomias.

### Padrão B — Dashboard / overview

```
SectionHeader + badge-neutral “Últimos 7 dias”
stat-grid 4 colunas (2 no tablet)
overview-grid 1.55fr / 0.9fr
  panel gráfico | panel funil/lista
```

KPIs com ícone tinted, não com sparkline colorida. Gráfico de barras: coral `#ef8976→#f4b2a6` e purple `#8e81e2→#bfb9f4`, eixos 9px `#aeb7c3`, linhas tracejadas `#edf0f3`. Legenda 10px.

### Padrão C — Split editor (Knowledge / Catalog / Team)

```
grid 0.92fr / 1.18fr (ou 280px + 1fr)
esquerda: lista de cards selecionáveis
direita: form config-form dentro de panel
```

Card ativo = coral wash. Hint de IA/ajuda = caixa purple-soft, radius 10px, 11px, ícone Sparkles/Book 14px.

### Padrão D — Canvas + inspector (Flow editor)

```
palette 220px | canvas #f4f6f8 | inspector/log 320px
toolbar branca 56px com status + ações
nós: branco, radius 11px, ícone tinted 28px, borda --line
selecionado: borda navy + ring rgba(20,37,61,.1)
```

Preview/publicado = verde `#2f9e64`. Dirty save = o secondary vira coral (exceção documentada: o botão “Salvar” só ganha primary quando há mudança).

No CMS: editor Gutenberg-like, builder de layout, preview de tema. Palette à esquerda, canvas cinza-frio, inspector à direita. **Não** usar fundo quadriculado tipo Figma; o canvas Lia é `#f4f6f8` liso.

### Padrão E — Auth / onboarding

Split 1.04fr visual navy + 0.96fr form `#fbfcfe`.
Visual: radial purple 24% + radial coral 18% sobre navy, anel concêntrico branco 10% no canto superior direito.
Eyebrow coral claro `#f4a091`. H1 branco com palavra êmfase em coral claro. Card 430px, radius 22px, padding 42px.
CTA full-width com ArrowRight.

Páginas legais: canvas, bar branca 16/28, body max 760px, H1 32px, body 15px/1.65 muted. Não aplicar o split de login em settings.

### Padrão F — Kanban (Funil)

Coluna `#f4f6f8`, radius 11px, card branco radius 9px sombra `0 2px 6px rgba(25,37,55,.025)`. Drop target: borda coral, fundo `#fff7f5`. Head 10px/800 `#657283` + stage-dot.

### Padrão G — Hero de feature (Automações)

Único lugar com gradiente grande:

`linear-gradient(115deg, #1e314c 0%, #283f5e 58%, #6670bd 100%)`

Texto branco, subtítulo `#b9c6d8`, CTA **invertido** (branco, texto navy). Art decorativo: órbitas e bubbles em branco 12%. Não repetir esse hero em toda listagem. No CMS, reserve para empty state de um módulo novo ou onboarding, não para o dashboard diário.

### Padrão H — Banners de workspace

Full-bleed, 13px/600, padding `10px 20px`:

- Trial/aviso: gradiente ouro `#fff4d6 → #ffe8b5 → #ffd89a`, texto `#5c4818`, borda `#ead7a8`
- OK: `#eef8f1` / `#c6e4cf` / `#1f5b32`
- Error: `#fdeeee` / `#f0c9c9` / `#8a1f1f`

Não são toasts. Empurram o app-shell para baixo.

---

## 9. Iconografia

- Biblioteca: **Lucide React**.
- Tamanhos canônicos: nav 18, topbar 16–21, botão 14–17, meta 14, composer/send contexto 14.
- Stroke default do Lucide (2). Não engrossar.
- Cor herda o texto do container, ou a família tone no ícone tinted.
- Marca Lia: wordmark Arial weight 750 tracking `-0.05em` + **speech-bubble coral rotacionada 8°**, com estrela/plus interno branco e drop-shadow `0 5px 12px rgba(237,114,93,.32)`. Favicon: square navy radius 8 + a mesma bubble.

No CMS, não desenhe a mascote em toda tela. Use Lucide. A bubble é marca, não ícone de UI.

Ícones que o produto “fala”: `Inbox`, `LayoutDashboard`, `UsersRound`, `TrendingUp`, `Bot`, `Zap`, `FileText`, `Tag`, `Users`, `Gauge`, `Settings2`, `Sparkles` (IA), `Handshake` (handoff), `Search`, `Plus`, `ArrowRight`.

`Sparkles` = inteligência. Não use estrela, robô 3D, nem gradiente no ícone de IA.

---

## 10. Motion e estados

```
transition: .18s ease;          /* default hover */
transform: translateY(-1px);    /* só primary e save dirty */
drawer: transform .2s ease;
tour/spot: .2s em top/left/width/height
meter fill: width .25s ease
alert leave: .28–.32s em opacity + translateY(-12px)
spinner: 22px, border 2px --line, top coral, .8s linear
typing dots: 1.2s bounce 3px
```

`prefers-reduced-motion: reduce` zera as transições de alerta.

Não: scale bounce em botão, skeleton shimmer colorido, page fade, parallax, gradient animation.

Loading de auth: navy ou canvas + brand mark + spinner coral. Texto muted.

---

## 11. Voz de interface (UX writing)

O tom é **operador experiente falando com outro operador**. Curto, específico, pt-BR.

**Faça**

- Eyebrow de domínio + título concreto: `Relacionamento` / `Contatos`
- Description que diz o que a tela faz: “Cadastre, edite e acompanhe cada pessoa da sua operação.”
- CTA com verbo no infinitivo: `Adicionar contato`, `Novo agente`, `Efetuar Login`
- Empty: “Nenhum contato encontrado.” / “Carregando contatos...”
- Erro humano: “Não foi possível entrar agora.”
- Estados de conexão: `Conectado` / `Conectando` / `Desconectado`
- Datas `pt-BR`, hora `2-digit`

**Não faça**

- “Oops!”, “Yay!”, “Let’s go”
- “Create your first amazing post”
- Microcopy em inglês
- Exclamação em botão
- Title Case Em Tudo
- Emoji em label, nav, empty state, toast

Placeholder: “Buscar por nome, empresa ou telefone”, “voce@empresa.com”, “Sua senha”. Minúsculo, exemplo real, sem “Digite aqui…”

---

## 12. Acessibilidade que o estilo já assume

- `html lang="pt-BR"`
- Icon-only buttons têm `aria-label`
- Focus visível lavanda nos fields; botões dependem do outline nativo — no CMS, mantenha o anel lavanda e um `:focus-visible` de 2px offset em botões (`#c2bbed`)
- Contraste no navy: nav inativa `#aab5c5` sobre `#14253d` — não clarear a sidebar
- Disabled é opacity, não some o controle
- `sr-only` existe; use para labels de ícone
- Overlay de tour/modal: navy 48–55%, nunca esconda o foco no card

---

## 13. White-label — o que o CMS pode e não pode herdar

O produto troca `--brand`, `--coral`, `--navy`, logos e fundo de login. A geometria permanece.

Para um CMS **da Lia** (não white-label):

- Primary = coral `#ed725d`
- Sidebar = navy `#14253d`
- Não use o primary reseller `#7c3aed` como botão, a menos que a tela seja do painel do revendedor

Se o CMS for white-label também, exponha os mesmos três tokens e derive:

```
--brand-hover = mix(primary, #000, 16%)
--coral-soft  = mix(accent, #fff, 88%)
--brand-shadow = accent em hex + 2b (17% alpha)
```

Nunca deixe o implementador “escolher uma paleta nova”. A estrutura cromática (navy chrome + 5 families soft) é o estilo; o hex do primary é só o sotaque.

---

## 14. O que NÃO é Lia (anti-referências)

O CMS está errado se tiver:

- Inter/Poppins/Roboto como face principal sem compensar densidade
- Primary indigo/sky/emerald (Filament default, Tailwind demo)
- Sidebar clara com item ativo em pill colorida
- Cards com sombra `0 10px 15px rgba(0,0,0,.1)` e radius 16–24px em tudo
- Gradiente no botão primário
- Fundo branco puro `#fff` na página
- Espaçamento “airbnb” (padding 32–48px em card de tabela)
- Tipografia 16px em célula
- Dark mode invertido de página (o produto é light; dark só existe no playground interno e na sidebar)
- Ilustrações unDraw / blobs
- Glassmorphism além do blur da topbar
- Bordas 2px coloridas em todo card
- Status só cinza/verde
- Skeleton em arco-íris
- Nav com ícones filled coloridos

O playground tester (`.pt-*`) é uma superfície **interna, dark, navy `#0f1b2d`**. Não copie isso para o CMS. É laboratório, não produto.

---

## 15. Receita de implementação (para o agente do CMS)

Siga nesta ordem. Não comece pelo componente isolado.

1. **Tokens CSS** exatamente da §3. Sem tema dark.
2. **Reset:** `box-sizing: border-box`; body 14px Arial ink sobre canvas; button/input inherit font.
3. **Shell:** sidebar 250 navy + main canvas + topbar blur. Primeiro pixel que precisa bater.
4. **Nav:** labels uppercase, item 40px, ativo inset coral 3px.
5. **SectionHeader** + primary/secondary/icon/text.
6. **Panel, stat-card, table, form, modal, tabs underline.**
7. **Tons:** avatar + badge + icon-soft. Cadastre as 6 famílias.
8. **Estados:** selected wash `#fff7f5`, empty dashed, form-error/success, banners.
9. **Páginas:** mapeie cada rota CMS → padrão A–H da §8.
10. **Motion 180ms** e foco lavanda.
11. **Copy pt-BR** no tom da §11.
12. **QA visual:** compare sidebar, um index de lista, um form modal, um editor split. Se esses quatro estiverem certos, o resto segue.

### Mapeamento sugerido CMS → Lia

| Superfície CMS              | Padrão Lia                                              |
| --------------------------- | ------------------------------------------------------- |
| Login                       | E                                                       |
| Dashboard                   | B                                                       |
| Posts / Páginas / Mídia     | A                                                       |
| Editar post (form clássico) | C (lista de campos em panel, não Gutenberg obrigatório) |
| Editor visual / builder     | D                                                       |
| Categorias, tags            | A (tabela simples) + modal create                       |
| Usuários / papéis           | A + modal                                               |
| Ajustes                     | C, account-grid 2 colunas                               |
| Menus / navegação           | D reduzido (palette de itens + canvas de árvore)        |
| Onboarding vazio            | G uma vez, depois empty dashed                          |
| Alertas de sistema          | H ou form-error inline                                  |

### Checklist de pixel (abrir lado a lado com o app)

- [ ] Canvas `#f7f8fb` visível nas margens; cards brancos com borda `#e9edf2`
- [ ] Sidebar `#14253d`, wordmark branco, ativo com barra coral 3px
- [ ] Topbar 82px, título 21px tracking -0.04em, subtítulo 12px muted
- [ ] Eyebrow 10px coral uppercase tracking .12em sobre o H2 25px
- [ ] Primary coral, hover sobe 1px, sombra coral 17%
- [ ] Input `#fbfcfd`, focus anel `#f0eeff` + borda `#c2bbed`
- [ ] Tabela TH 9px uppercase, TD 10px, sem zebra
- [ ] Modal overlay navy 48% + blur 4px
- [ ] Ícones Lucide 14–18, nunca emoji
- [ ] Um primary por header
- [ ] Toggle on é verde, não coral
- [ ] Filtro ativo pode ser indigo-lavanda; ação continua coral
- [ ] Mobile: drawer navy, content padding 18px, tabela com scroll-x

---

## 16. Resumo executivo para colar no system prompt do outro agente

> Reproduza o design system do painel Lia. Light UI operacional sobre canvas `#f7f8fb`, chrome navy `#14253d`, acento coral `#ed725d`. Tipografia Arial, títulos com tracking negativo, UI densa 9–13px. Cards brancos radius 13px, borda `#e9edf2`, sombra fria mínima. Primary coral com hover translateY(-1px); secondary outlined cinza; toggle verde. Foco de input lavanda (`#c2bbed` / `#f0eeff`), nunca coral. Sidebar com item ativo em vidro + inset coral 3px. Eyebrow uppercase coral acima de H2. Ícones Lucide stroke 2. Sem Inter, sem indigo de admin, sem dark mode de página, sem emoji, sem gradiente em botão. Copy pt-BR curta, verbo no infinitivo. Layout: frame 100dvh, listagens com SectionHeader + panel/table, editores em split palette/canvas/inspector. Tokens e componentes detalhados no restante deste arquivo — não improvisar paleta.

---

## 17. Referência rápida de hex

```
canvas        #f7f8fb
surface       #ffffff
surface-soft  #fbfbfd
ink           #182230
ink-muted     #7d8797
ink-faint     #a6afbc
line          #e9edf2
line-strong   #dfe5ec
navy          #14253d
coral         #ed725d
coral-soft    #fff0ec
coral-hover   #df604e
purple        #7768db
purple-soft   #f0efff
green         #4ca87f
green-soft    #eaf8f1
blue          #4d8edc
blue-soft     #edf5ff
gold          #c8903b
gold-soft     #fff6e5
danger        #c44b4b
focus-border  #c2bbed
focus-ring    #f0eeff
selected-bg   #fff7f5
login-em      #f5a091
agent-bubble  #14253d
contact-bubble #f3f5f7
```

Fim do brief. Qualquer desvio precisa ser consciente e documentado; o default é copiar, não reinterpretar.
