# Kit de marca: Quatro Ventos

Documento portátil do sistema visual do site público. Serve para montar propostas, apresentações e peças em Canva, Google Slides, Figma, Keynote e ferramentas de geração. Os valores abaixo são os do site em produção (`resources/css/site.css` e `DESIGN.md`).

O `DESIGN.md` na raiz continua sendo o spec de implementação do site. Este arquivo é o kit para reutilizar a marca fora do código.

---

## Como carregar no Canva

1. Marca → Kit de marca → criar kit **Quatro Ventos**.
2. Cores, nesta ordem: Noite, Tinta, Papel, Branco, Limão, Oliva, Texto suave (escuro), Texto mudo (escuro), Texto suave (claro), Texto mudo (claro), WhatsApp, Erro. Hex na tabela de cores.
3. Fontes: **Host Grotesk** (títulos, corpo, botões) e **Martian Mono** (números e nomes técnicos). As duas estão no Google Fonts. Se o Canva não listar, baixar o TTF em [Host Grotesk](https://fonts.google.com/specimen/Host+Grotesk) e [Martian Mono](https://fonts.google.com/specimen/Martian+Mono) e enviar no kit. Os arquivos do site (`resources/fonts/*.woff2`) são variable font para web; o Canva pede TTF/OTF.
4. Logotipo: enviar `public/assets/logo-quatroventos.svg`. O arquivo é branco. Duplicar e recolorir para `#0b0b0f` quando o fundo for Papel ou Branco.
5. Em cada página nova: fundo Noite, Papel ou Branco. Um único trecho do título em Limão. Botões em pílula. Cards com canto de 22 px, sem sombra.

### Prompt curto (colar na geração)

```
Marca Quatro Ventos. Fundo noite #07070a ou papel #f4f4f1 ou branco #ffffff. Texto em branco no escuro e em #0b0b0f no claro. Uma única cor de destaque: limão #c8f25a, só numa frase do título e no botão de WhatsApp. Fonte Host Grotesk, títulos enormes com tracking negativo, peso 650, entrelinha apertada. Números e rótulos técnicos em Martian Mono 13px. Botões em pílula (altura 54px): branco com texto tinta no escuro, tinta com texto branco no claro. WhatsApp é pílula tinta com texto limão. Cards brancos, raio 22px, sem sombra, padding 28px. Sem foto de banco, sem ícone de biblioteca, sem gradiente em texto, sem rótulo acima do título. Voz em português do Brasil, frases curtas, institucional, sem exclamação.
```

---

## Norte

**A operação rodando ao fundo.**

Peça escura e editorial na abertura, leitura em papel no miolo, prova em branco no fim. A hierarquia vem do tamanho da Host Grotesk. A cor aparece pouco: limão marca a frase que importa e o WhatsApp. O resto é preto, branco e cinza.

Densidade baixa. Escala tipográfica alta. Imagem só quando for material real (logo de cliente, print de projeto, dado do cliente da proposta).

---

## Logo

Arquivo oficial: `public/assets/logo-quatroventos.svg`.

| Versão | Fundo | Preenchimento |
|---|---|---|
| Principal | Noite `#07070a`, Tinta `#0b0b0f`, Painel `#121217` | Branco `#ffffff` (o SVG já nasce assim) |
| Invertida | Papel `#f4f4f1`, Branco `#ffffff` | Tinta `#0b0b0f` |

Proporção do arquivo: 436 × 68,8 px (cerca de 6,34:1). No site o header usa 28 px de altura; o rodapé vai de 26 a 34 px.

Regras de uso:

- Manter a proporção. Não esticar, não rotacionar, não colocar em caixa, não aplicar sombra.
- Área livre em volta: metade da altura do logo.
- Altura mínima: 28 px em tela, 8 mm em impresso.
- Não recriar o nome em Host Grotesk no lugar do arquivo.
- Não colorir de limão, oliva ou verde de WhatsApp.
- Nome escrito por extenso, quando não for o logo: **Quatro Ventos**.

---

## Cores

Tinta quase preta, papel quente, um limão de sinal. Fora do limão, a peça é preto, branco e cinza.

| Token | Nome | Hex | RGB | Onde entra |
|---|---|---|---|---|
| `bg` | Noite | `#07070a` | 7, 7, 10 | Capa, abertura, método, fechamento, rodapé |
| `ink` | Tinta | `#0b0b0f` | 11, 11, 15 | Texto em fundo claro. Fundo do botão de WhatsApp e do card de destaque |
| `panel` | Painel | `#121217` | 18, 18, 23 | Caixa sobre o escuro (menu, card translúcido). Em peça estática, use sólido |
| `paper` | Papel | `#f4f4f1` | 244, 244, 241 | Miolo de leitura, fundo de campo, card quando a página é branca |
| `white` | Branco | `#ffffff` | 255, 255, 255 | Prova, cards, planos, botão principal em fundo escuro |
| `lime` | Limão | `#c8f25a` | 200, 242, 90 | Uma frase do título. Texto e seta do botão de WhatsApp. Fundo do destaque quando o título está em papel |
| `olive` | Oliva | `#5f7a12` | 95, 122, 18 | Sublinhado de link em fundo claro. Nunca fundo, nunca texto corrido |
| `whatsapp` | Verde WhatsApp | `#25d366` | 37, 211, 102 | Só o botão redondo flutuante do canal. Em proposta, não usar |
| `on-dark-soft` | Suave no escuro | `#c9c9c4` | 201, 201, 196 | Parágrafo de abertura sobre Noite |
| `on-dark-mute` | Mudo no escuro | `#8d8d93` | 141, 141, 147 | Metadado, rodapé, legenda |
| `on-light-soft` | Suave no claro | `#3e3e44` | 62, 62, 68 | Corpo sobre Papel ou Branco |
| `on-light-mute` | Mudo no claro | `#6b6b72` | 107, 107, 114 | Empresa do depoimento, legenda, número secundário |
| `danger` | Erro | `#b42318` | 180, 35, 24 | Mensagem de campo inválido |

Linhas, 1 px:

- Sobre escuro: branco a 12% → `rgba(255, 255, 255, 0.12)`.
- Sobre claro: tinta a 12% → `rgba(11, 11, 15, 0.12)`.

Hovers de botão, se a ferramenta animar:

- Branco no hover: `#e4e4df`.
- Tinta no hover: `#2a2a31`.

### Regras de cor

**Três lugares.** Limão entra em composição só em três pontos: a frase de destaque do título, o botão de WhatsApp e, em tela, o anel de foco sobre fundo escuro. Nenhum outro elemento de conteúdo usa limão.

**Limão é a única cor.** CTA de WhatsApp: pílula tinta com texto limão. Qualquer outra ação: pílula sólida, branca no escuro e tinta no claro.

**Tom por tipo de página.** Capa, método e fechamento são Noite. Leitura e oferta são Papel. Prova (clientes, depoimentos, casos) é Branco. Duas páginas do mesmo tom seguidas leem como uma superfície só: sem faixa extra entre elas.

**Destaque de frase.** No máximo um trecho por título. Em fundo escuro, o trecho é texto limão. Em fundo claro, o trecho ganha uma faixa de limão por trás, com o texto em tinta. Sem gradiente no texto.

Contraste útil para checagem:

- Branco sobre Noite e Tinta sobre Papel passam em texto pequeno.
- Limão `#c8f25a` sobre Noite passa em título grande. Limão como texto sobre Papel não passa: nesse fundo o limão é faixa, e o texto fica em tinta.
- Oliva é sublinhado, não bloco de texto.

---

## Tipografia

| Papel | Família | Fallback |
|---|---|---|
| Títulos, corpo, botões | Host Grotesk | system-ui, -apple-system, Segoe UI, sans-serif |
| Números, preço, nomes de tecnologia | Martian Mono | ui-monospace, SF Mono, Menlo, monospace |

Host Grotesk no site é variável, 300 a 800. Pesos que a marca usa: 300 (não usar no logo), 400 corpo, 500 rótulo de campo, 600 botão e linha de lista, 650 título.

Martian Mono entra em número de ordem (`01`), unidade de preço, nome de tecnologia e log técnico. Não entra em título, botão nem rótulo de seção.

### Escala

Tracking dos títulos: −0,045em a −0,05em. Entrelinha dos títulos: 0,98. Corpo: 1,55. Abertura: 1,45.

| Papel | Peso | Site | Slide 1920×1080 | A4 / proposta |
|---|---|---|---|---|
| Display (capa) | 650 | 44–116 px | 72–96 px | 36–48 pt |
| Chamada | 650 | 40–92 px | 56–72 px | 28–36 pt |
| Título de seção | 650 | 34–68 px | 40–56 px | 22–28 pt |
| Título de card | 650 | 22–28 px | 24–28 px | 16–18 pt |
| Linha de lista | 600 | 22–34 px | 22–28 px | 14–16 pt |
| Abertura | 400 | 18–21 px | 20–22 px | 13–14 pt |
| Corpo | 400 | 18 px (17 px no mobile) | 18–20 px | 11–12 pt |
| Botão / navegação | 600 | 16 px | 16 px | 11–12 pt |
| Mono | 400 | 13 px | 13–14 px | 9–10 pt |

Larguras de leitura:

- Abertura: até 560 px.
- Corpo: até 62 caracteres.
- Título de seção: 15 a 16 caracteres por linha quando o título é o elemento da página. Em slide, duas linhas no máximo.

### Regras de tipo

**Mono é máquina.** Número, preço, stack. O resto é Host Grotesk.

**Tamanho é hierarquia.** Não existe rótulo, eyebrow nem kicker acima do título. A página começa no título.

**Um destaque por título.** O trecho que importa vai em limão (texto no escuro, faixa no claro). O restante do título fica na cor do fundo.

---

## Forma

Duas famílias:

| Família | Raio | Uso |
|---|---|---|
| Pílula | 999 px | Botão, chip, filtro, selo, metadado |
| Card | 22 px | Card, plano, depoimento, painel, capa de case |
| Campo | 14 px | Input, capa de imagem |
| Dropdown | 18 px (item 12 px) | Menu suspenso |

Contorno é interno, 1 px, na cor de linha do fundo. Não soma na largura do bloco.

Seta de destino: traço diagonal para cima à direita, dentro de um círculo quando o ícone fica sozinho. Sem biblioteca de ícones.

---

## Componentes para peça e proposta

### Botão

Altura 54 px. Padding horizontal 26 px. Pílula. Peso 600. Sobe 2 px no hover.

| Variante | Fundo | Texto | Quando |
|---|---|---|---|
| Principal no escuro | `#ffffff` | `#0b0b0f` | Ação que não é WhatsApp, sobre Noite |
| Principal no claro | `#0b0b0f` | `#ffffff` | A ação principal sobre Papel ou Branco |
| WhatsApp | `#0b0b0f` | `#c8f25a` | Único CTA de conversa. Contorno interno limão 1 px. Círculo limão de 30 px com seta à direita |
| Secundário | transparente | branco ou tinta | Contorno 1 px (branco 70% no escuro, tinta 50% no claro) |
| Pequeno | igual ao principal | igual | Altura 46 px, padding 20 px. Header e ações secundárias de card |

Texto de botão em sentence case. Exemplos do site: "Tenho interesse", "Ver case". Sem caixa alta, sem exclamação.

### Card

Fundo branco sobre Papel, fundo Papel sobre Branco. Raio 22 px. Padding 28 px. Sem sombra. Sem borda.

Card de navegação: número mono no topo, título e texto na base, altura mínima 260 px. No hover o card vira tinta e o texto fica branco.

Card de destaque (o primeiro de uma grade de quatro): fundo tinta, número em limão, título até duas linhas.

### Campo

Fundo Papel, borda 1 px na linha clara, raio 14 px, altura 52 px. Rótulo acima, 14,5 px, peso 500. No foco: borda tinta, fundo branco. Erro: mensagem 13,5 px em `#b42318` abaixo do campo.

### Depoimento

Card Papel, raio 22 px, padding 40×32×28 px. Texto 18,5 px, peso 450, cor tinta. Autor no pé, separado por linha de 1 px: nome peso 600, empresa em mudo.

### Número de ordem

Martian Mono 13 px, cor mudo do fundo. Formato `01`, `02`, `03`.

### Linha divisória

1 px na cor de linha do fundo. Separa itens de lista, FAQ, autor de depoimento e faixa de rodapé. Não usar para enfeitar título.

---

## Layout

Coluna central. Largura máxima no site: 1440 px. Respiro lateral: 20 a 44 px. Respiro vertical de seção: 80 a 136 px.

Em slide 1920×1080: margem de 64 a 80 px. Título à esquerda. Texto de apoio à direita ou abaixo, alinhado à base do título, nunca centralizado em bloco longo.

Em A4: margem 18 a 22 mm. Mesma lógica, título à esquerda.

Grade de miolo: título ocupa um pouco mais da metade à esquerda; o parágrafo de apoio ocupa o resto, alinhado pela base. Sem o parágrafo, o título ocupa a linha.

### Ritmo de uma proposta

A home é o modelo. Alternar escuro, papel e branco.

1. **Capa, Noite.** Título enorme com um trecho em limão. Uma abertura de até duas linhas em suave. Duas pílulas: WhatsApp (tinta + limão) e secundária em contorno.
2. **Problema ou oferta, Papel.** Título com faixa limão no trecho-chave. Grade de cards brancos. Se forem quatro itens, o primeiro é o card tinta.
3. **Afirmação, Papel.** Um painel branco, raio 22 px, padding generoso (32 a 72 px). Título à esquerda, um parágrafo e um botão à direita.
4. **Método, Noite.** Passos em linha, número mono, título, texto curto. Um ponto ou traço limão marca a etapa, não o parágrafo inteiro.
5. **Prova, Branco.** Depoimentos, logos ou cases. Logo de cliente em célula Papel, raio 22 px, silhueta em tinta.
6. **Fechamento, Branco com painel Noite.** Título branco, um trecho em limão, um parágrafo suave, pílula de WhatsApp.

Páginas internas e propostas curtas repetem o mesmo com menos blocos: capa, oferta, uma afirmação, prova, fechamento.

### Sombra

A página é plana. Sombra existe só no que flutua:

- Menu suspenso: `0 0 0 1px rgba(255,255,255,0.12), 0 24px 48px rgba(0,0,0,0.45)`.
- Botão flutuante: `0 10px 30px rgba(0,0,0,0.35)`.

Card, plano, depoimento e formulário não têm sombra. Em proposta estática, não usar sombra.

---

## Voz (para o texto da proposta)

Português do Brasil. Declarativa, institucional, técnica. IA é engenharia aplicada à operação.

- Sujeito: "a Quatro Ventos" ou primeira pessoa do plural.
- Frases curtas. Sem exclamação.
- Sem "a gente", "conta pra gente", "chama no WhatsApp", "muda o jogo", "sem compromisso", "cuidamos de tudo", "para empresas", "da sua empresa".
- CTA escrito como ação: "Falar no WhatsApp", "Tenho interesse". O canal é WhatsApp. Telefone publicado: (41) 3039-9593.

O que a proposta pode afirmar, porque está publicado:

- Atuação desde 2005.
- Clientes com logo: Santa Plural, Rodolfo Fontana, Liv Decora, Inove IT, Hemera DTVM, Garimpo Home, Fernanda Saad, Gisele Siqueira Dermatologista, Digio, Data Driven Mkt.
- Trabalhos, só o nome: Facilitapay, Multiversidade PUCPR, Caudex, Hemera, Matiza Plast, Enhance Fitness, Exaustor Estático, Digio, Diálogos de Carreira, Plan6, Yogha, Ivorecap.
- Preços publicados, até mudança explícita: manutenção Basic R$ 350/mês, Standard R$ 520/mês, Pro R$ 990/mês. Hospedagem anual Basic R$ 37,50, Standard R$ 58,50, Business R$ 140,50, Pro R$ 190,50. Sites em WordPress a partir de 6× R$ 999 no cartão.

O que a proposta não inventa: case novo, número de resultado, certificação, parceria, prazo e depoimento que não estejam publicados ou fornecidos para aquele cliente.

---

## Fazer

- Fundo Noite, Papel ou Branco, escolhido pelo papel da página.
- Um trecho de limão por título.
- Host Grotesk em tudo que é leitura. Martian Mono em número, preço e nome técnico.
- Pílula para ação. Card de 22 px para conteúdo. Linha de 1 px para lista.
- Botão principal branco no escuro e tinta no claro. WhatsApp sempre tinta com texto limão.
- Logo oficial em branco no escuro e em tinta no claro.
- Material real quando houver imagem: logo do cliente, print do projeto, dado da proposta.

## Não fazer

- Limão em ícone, fundo de seção, gráfico genérico ou segundo destaque no mesmo título.
- Verde `#25d366` em proposta. Ele é só o botão flutuante do site.
- Rótulo acima do título.
- Foto de banco, ilustração genérica, mockup de pessoa, gradiente em texto.
- Sombra em card.
- Ícone de biblioteca (Material, Font Awesome, Lucide, emoji).
- Recriar o logo com a fonte do título.
- Caixa alta em botão, título com tracking positivo, entrelinha folgada em display.
- Centralizar título longo e corpo na mesma página.

---

## Tokens (JSON)

Bloco para importar em ferramenta que aceite token simples. Componentes apontam para as cores por nome.

```json
{
  "name": "Quatro Ventos",
  "colors": {
    "bg": "#07070a",
    "ink": "#0b0b0f",
    "panel": "#121217",
    "paper": "#f4f4f1",
    "white": "#ffffff",
    "lime": "#c8f25a",
    "olive": "#5f7a12",
    "whatsapp": "#25d366",
    "onDarkSoft": "#c9c9c4",
    "onDarkMute": "#8d8d93",
    "onLightSoft": "#3e3e44",
    "onLightMute": "#6b6b72",
    "danger": "#b42318",
    "buttonHoverOnDark": "#e4e4df",
    "buttonHoverOnLight": "#2a2a31"
  },
  "typography": {
    "sans": "Host Grotesk",
    "mono": "Martian Mono",
    "display": { "fontFamily": "Host Grotesk", "fontWeight": 650, "lineHeight": 0.98, "letterSpacing": "-0.05em" },
    "body": { "fontFamily": "Host Grotesk", "fontSize": "18px", "fontWeight": 400, "lineHeight": 1.55 },
    "label": { "fontFamily": "Host Grotesk", "fontSize": "16px", "fontWeight": 600 },
    "monoRole": { "fontFamily": "Martian Mono", "fontSize": "13px", "fontWeight": 400 }
  },
  "rounded": { "pill": "999px", "card": "22px", "field": "14px" },
  "spacing": { "gutter": "20-44px", "section": "80-136px", "cardPadding": "28px", "maxWidth": "1440px" }
}
```

Arquivos de origem no repositório:

- Tokens e componentes do site: `resources/css/site.css`
- Spec de implementação: `DESIGN.md`
- Produto, voz e fatos publicáveis: `PRODUCT.md`
- Logo: `public/assets/logo-quatroventos.svg`
- Fontes web: `resources/fonts/host-grotesk.woff2`, `resources/fonts/martian-mono.woff2`
