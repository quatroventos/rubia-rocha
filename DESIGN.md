---
name: Rubia Rocha Fotografia
description: Estúdio de dia. Fotos reais sobre papel claro, com o controle pequeno e a prova grande.
colors:
  tinta: "#100f17"
  grafite: "#5c5858"
  papel: "#f6f1eb"
  folha: "#fffcf9"
  branco: "#ffffff"
  blush: "#f3e4dc"
  rosa-queimado: "#b08978"
  barro: "#8d6e60"
  sepia: "#826559"
  linho: "#c9b2a6"
  whatsapp: "#128c7e"
  whatsapp-fundo: "#075e54"
typography:
  display:
    fontFamily: "Playfair Display, Iowan Old Style, Palatino, serif"
    fontSize: "clamp(2.5rem, 4.5vw, 3.25rem)"
    fontWeight: 400
    lineHeight: 1.2
    letterSpacing: "-0.02em"
  headline:
    fontFamily: "Playfair Display, Iowan Old Style, Palatino, serif"
    fontSize: "clamp(2rem, 3vw, 2.25rem)"
    fontWeight: 400
    lineHeight: 1.2
    letterSpacing: "-0.02em"
  title:
    fontFamily: "Playfair Display, Iowan Old Style, Palatino, serif"
    fontSize: "1.5rem"
    fontWeight: 400
    lineHeight: 1.2
    letterSpacing: "-0.02em"
  body:
    fontFamily: "Work Sans, Avenir Next, Segoe UI, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.65
  label:
    fontFamily: "Work Sans, Avenir Next, Segoe UI, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 500
    lineHeight: 1.4
    letterSpacing: "0.14em"
  contrast:
    fontFamily: "Kudryashev Display Contrast, Playfair Display, Palatino, serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.2
rounded:
  frame: "2px"
  pill: "999px"
  circle: "50%"
spacing:
  tight: "0.5rem"
  snug: "0.75rem"
  stack: "1rem"
  group: "1.5rem"
  frame: "2rem"
  section: "3rem"
  break: "4.5rem"
components:
  button-line:
    backgroundColor: "{colors.branco}"
    textColor: "{colors.tinta}"
    typography: "{typography.label}"
    rounded: "{rounded.pill}"
    padding: "0.75rem 1.5rem"
    height: "44px"
  button-line-hover:
    backgroundColor: "{colors.tinta}"
    textColor: "{colors.folha}"
    rounded: "{rounded.pill}"
  button-fill:
    backgroundColor: "{colors.blush}"
    textColor: "{colors.tinta}"
    typography: "{typography.label}"
    rounded: "{rounded.pill}"
    padding: "0.75rem 1.5rem"
    height: "44px"
  button-fill-hover:
    backgroundColor: "{colors.tinta}"
    textColor: "{colors.folha}"
    rounded: "{rounded.pill}"
  nav-link:
    textColor: "{colors.tinta}"
    typography: "{typography.contrast}"
    rounded: "{rounded.pill}"
    padding: "0.5rem 0.75rem"
    height: "44px"
  nav-link-hover:
    backgroundColor: "{colors.blush}"
    textColor: "{colors.tinta}"
    rounded: "{rounded.pill}"
  input:
    backgroundColor: "transparent"
    textColor: "{colors.tinta}"
    typography: "{typography.body}"
    rounded: "{rounded.frame}"
    padding: "0.5rem 0"
  whatsapp-float:
    backgroundColor: "{colors.whatsapp}"
    textColor: "{colors.branco}"
    rounded: "{rounded.circle}"
    size: "56px"
  whatsapp-float-hover:
    backgroundColor: "{colors.whatsapp-fundo}"
    textColor: "{colors.branco}"
    rounded: "{rounded.circle}"
---

# Design System: Rubia Rocha Fotografia

## Overview

**Creative North Star: "O estúdio de dia"**

A parede é clara e a prova está na parede. Não há cenário escuro, vinheta de estúdio nem letreiro de agência. A página é o papel em que as fotos pousam, e o texto fica perto, em primeira pessoa, falando de gente. Pacote, pose engessada e slogan não entram nessa voz.

A densidade é baixa. A fotografia ocupa o vão; o controle é uma pílula pequena, em caixa alta, que não compete com a imagem. O rosa aparece como marca, não como pintura. O verde existe num lugar só, o botão que chama.

**Key Characteristics:**

- Papel creme na página e uma folha mais clara pousada em cima, com sombra curta.
- Três vozes: Playfair Display nos títulos, Work Sans no texto e nos botões, Kudryashev Display Contrast no menu, no nome sobre a foto e no nome do depoimento.
- Fotografia com canto de 2px. O hero guarda a proporção da prancha: laterais estreitas, centro mais largo, a mesma altura.
- Pílula só no controle. Caixa alta e tracking largo só em botão, kicker e rótulo.
- Rosa queimado é marca. Barro é a borda do botão de linha. Sépia é o rótulo curto. Verde é só o WhatsApp.

## Colors

A paleta é de material de estúdio: papel, folha, tinta e um rosa de prova. O verde não faz parte desse material.

### Primary

- **Tinta** (`tinta`): texto, anel de foco e o hover dos dois botões. É o que se lê.
- **Grafite** (`grafite`): texto secundário, kicker, legenda e metadado. Não substitui a tinta no parágrafo principal de ação.

### Secondary

- **Blush** (`blush`): fundo do botão cheio e do item de menu em hover ou na página atual.
- **Rosa queimado** (`rosa-queimado`): marca decorativa. Não é cor de parágrafo.
- **Barro** (`barro`): borda de 1px do botão de linha. É o rosa escuro o bastante para a borda no papel e no branco.
- **Sépia** (`sepia`): rótulo curto de categoria sobre a folha, como o “Ver galeria” não, e sim a linha de categoria do card. Texto pequeno, nunca um bloco.

### Tertiary

- **Verde WhatsApp** (`whatsapp`): fundo do botão flutuante.
- **Verde fundo** (`whatsapp-fundo`): hover desse botão. Não colore link, ícone de rede nem selo.

### Neutral

- **Papel** (`papel`): fundo da página.
- **Folha** (`folha`): painel da seção “o que eu fotografo”, e o texto dos botões no hover.
- **Branco** (`branco`): face do botão de linha.
- **Linho** (`linho`): fio de divisor, borda do ícone de rede e borda de campo.

### Named Rules

**The Rose Is a Mark Rule.** Rosa queimado não entra em parágrafo. Barro só na borda do botão de linha. Sépia em rótulo curto, no rodapé e no véu das capas de foto.

**The Green Is the Channel Rule.** Verde WhatsApp e verde fundo existem só no botão flutuante.

## Typography

**Display Font:** Playfair Display (com Iowan Old Style, Palatino, serif)
**Body Font:** Work Sans (com Avenir Next, Segoe UI, sans-serif)
**Label/Mono Font:** Work Sans no rótulo; Kudryashev Display Contrast na voz de contraste, sem mono.

**Character:** Uma serifa de título, uma sans de leitura e uma display de contraste alto para o que está escrito em cima da foto ou no menu. As três já estão no desenho das pranchas. Nenhuma outra família entra.

### Hierarchy

- **Display** (400, `clamp(2.5rem, 4.5vw, 3.25rem)`, 1.2, tracking -0.02em): o H1, centrado, como “Oi! Eu sou a Rubia.”
- **Headline** (400, `clamp(2rem, 3vw, 2.25rem)`, 1.2): o H2 de seção.
- **Title** (400, 1.5rem, 1.2): o H3. O H4 fica em 1.25rem e o H5 em 1.125rem, mesma família e o mesmo tracking.
- **Body** (400, 1rem, 1.65): texto corrido, até 66ch, cor grafite quando é prosa de página.
- **Label** (500, 0.75rem, tracking 0.14em, caixa alta): botão, kicker e rótulo de categoria.
- **Contrast** (400, 0.875rem no menu e na assinatura; 1.125rem no nome sobre a foto): Kudryashev Display Contrast.

### Named Rules

**The Three Voices Rule.** Playfair Display nos títulos, no depoimento, na pergunta do FAQ e no título de card. Work Sans no texto, no botão, no kicker e em “Ver galeria”. Kudryashev Display Contrast no menu, no nome sobre a foto e no nome de quem assina o depoimento.

**The Uppercase Is the Control Rule.** Caixa alta e tracking largo marcam controle e rótulo. Título fica em caixa de frase.

## Layout

Coluna central `min(1080px, 100% - 2rem)`, no passo `stack` de cada lado. O ritmo é uma escala só: tight 0.5rem, snug 0.75rem, stack 1rem, group 1.5rem, frame 2rem, section 3rem, break 4.5rem.

O hero é a prova na parede. Três fotos, vão `frame`, laterais na razão 0.448 e centro na razão 1.046, a mesma altura. A foto da esquerda sai para a esquerda e as outras vêm da direita. Abaixo de 560px fica uma foto só, a do centro. Os pontos do carrossel ficam a `group` da faixa, mais perto dela do que do texto, que começa a `section`. O ponto é um anel sépia; o atual vira pílula sépia de 20px. Ao lado fica o botão de pausar, porque a troca automática não pode depender de hover.

A folha de “o que eu fotografo” usa padding `break` na vertical e `frame` na lateral, e a grade de fotos usa vão `group`. O depoimento, o FAQ e o formulário separam-se por `break`. O rodapé sai da coluna, ocupa a largura da tela em sépia, e o miolo volta para a mesma medida da página.

Abaixo de 800px as grades de duas colunas continuam em duas, e o menu vira painel sob a barra. Abaixo de 560px a grade de fotos e o formulário caem para uma coluna.

### Named Rules

**The Frame Rule.** O vão entre as fotos do hero é `frame`. Grade e trio usam `group`, um passo abaixo. O texto da página não encosta na prova: o intervalo seguinte é `section` ou `break`.

**The Same Height Rule.** No hero, laterais e centro compartilham a altura. A largura muda; a altura não.

## Elevation & Depth

A página é plana. A profundidade é a folha pousada no papel: a seção clara é um plano `#fffcf9` com uma sombra curta, e o resto não levanta. A fotografia não tem sombra. O único objeto que flutua é o botão de WhatsApp.

### Shadow Vocabulary

- **Folha** (`box-shadow: 0 10px 18px -14px rgb(16 15 23 / 0.28)`): a seção clara sobre o papel. Curta, tinta, sem halo colorido.
- **Flutuante** (`box-shadow: 0 8px 20px rgb(16 15 23 / 0.18)`): o botão de WhatsApp, porque está fora do fluxo da página.

### Named Rules

**The Sheet Rule.** A folha separa-se do papel pela cor e por essa sombra curta. Foto, card e campo não ganham sombra.

**The Float Rule.** Sombra de objeto solto existe só no botão de WhatsApp.

## Shapes

Três silhuetas. A fotografia, a folha, a marca, o card, o campo e o painel do menu levam canto de 2px. O controle é pílula (`999px`): botão, item de menu e o alvo do menu. O retrato redondo, o ponto inativo do carrossel, o ícone de rede e o WhatsApp são círculo. O campo continua um fio, só a borda de baixo, com o mesmo canto de 2px.

### Named Rules

**The Two-Pixel Frame Rule.** O que era canto vivo leva 2px. Pílula e círculo ficam como estão.

## Components

O controle é pequeno de propósito. A pílula não cresce para virar faixa, e o hover dos dois botões é o mesmo: tinta com texto folha.

### Buttons

- **Shape:** pílula, altura mínima 44px, padding 0.75rem 1.5rem, rótulo em label (0.75rem, peso 500, tracking 0.14em, caixa alta) com uma seta.
- **Line:** face branca, borda de 1px em barro, texto tinta. É o pedido de orçamento.
- **Fill:** face blush, sem borda, texto tinta. É o chamado do WhatsApp dentro da página, não o botão flutuante.
- **Hover / Focus:** os dois fecham em tinta, texto folha, borda tinta. O foco de teclado é anel de 2px em tinta com offset de 3px, em qualquer controle.

### Cards / Containers

- **Corner Style:** a foto do card tem canto de 2px. O card de blog é uma capa, a data em Playfair a 2rem e o título em Playfair a 1.375rem.
- **Background:** nenhum. O card senta no papel ou na folha.
- **Shadow Strategy:** nenhuma. Ver The Sheet Rule.
- **Border:** nenhuma.
- **Internal Padding:** o texto da capa de foto usa `frame` acima e `snug` nas laterais e embaixo, sobre um véu sépia que sobe de transparente a 86%. O título fica em folha e a pílula "Ver galeria" é folha com texto sépia.

### Inputs / Fields

- **Style:** fundo transparente, canto de 2px, só a borda inferior de 1px em linho, padding 0.5rem 0, texto na fonte do corpo.
- **Focus:** o anel de 2px em tinta. A borda não troca de cor sozinha.
- **Error / Disabled:** o estado de erro do envio é uma faixa blush; não há campo vermelho desenhado.

### Navigation

Menu em Kudryashev Display Contrast, 0.875rem, alvo de 44px, pílula sem borda. Hover e página atual ganham blush. Abaixo de 800px o menu deixa a linha e abre um painel folha colado sob a barra.

### WhatsApp flutuante

Círculo de 56px, verde WhatsApp, ícone branco, fixo a `group` do canto (`stack` abaixo de 800px). Hover em verde fundo. É o único verde da página e a única sombra de objeto solto.

### Hero

Três fotos na proporção da prancha, vão `frame`. A saída é para a esquerda, em 700ms, com 80ms entre uma foto e a outra, na curva `cubic-bezier(0.16, 1, 0.3, 1)`. Com `prefers-reduced-motion` a troca é imediata.

## Do's and Don'ts

### Do:

- **Do** deixar a fotografia na frente e o controle em pílula pequena.
- **Do** usar as três famílias já carregadas, cada uma no papel da The Three Voices Rule.
- **Do** manter o hero na razão 0.448 / 1.046, com o vão em `frame`.
- **Do** falar de gente, em primeira pessoa, como a Rubia.
- **Do** manter o kicker quando a prancha tem um. Ele é rótulo do desenho, em Work Sans, caixa alta.
- **Do** desligar o deslize do hero e a entrada da grade quando o visitante pede menos movimento.

### Don't:

- **Don't** pintar parágrafo com rosa queimado, nem usar verde fora do botão flutuante.
- **Don't** passar de 2px na fotografia, nem trocar pílula e círculo por esse canto.
- **Don't** dar sombra a foto, card ou campo.
- **Don't** trazer fundo preto, limão ou outra família de tipo para estas páginas. O rodapé contrasta em sépia, não em tinta.
- **Don't** escrever como pacote, pose ou slogan de agência.
- **Don't** inventar depoimento, preço ou telefone que a prancha não tenha.
