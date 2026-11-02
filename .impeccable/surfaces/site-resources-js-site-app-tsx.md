---
version: 1
slug: "site-resources-js-site-app-tsx"
primary_target: "resources/js/site/App.tsx"
related_targets: ["resources/css/site.css","resources/js/site/sections.tsx","resources/views/site/app.blade.php"]
---

# Site público

Escopo: todas as URLs públicas do mapa do site (home, hubs, páginas de serviço, cases, sobre, contato, conteúdo) em React, renderizadas por seções vindas do CMS. Visitante em modo Persuade. Público: decisores de empresas que querem melhorar processos com IA. Trabalho: entender a oferta e iniciar um projeto pelo WhatsApp. Prova: cases com prints, clientes, depoimentos e preços publicados. Restrição: copy de `.impeccable/briefs/copy/`, URLs e 301 de `mapa-do-site.md`. Sem depoimento ou número inventado.

Referências de design: somente Vime e Huge (PRODUCT.md).

## Direction contract

THESIS: A IA trabalhando na operação é o fundo da página: um log de agente roda desfocado atrás do título, e a segunda linha do H1 troca entre o que a Quatro Ventos entrega. Recusa o hero de imagem de banco, o gradiente de IA e a grade de três cards iguais.

OWN-WORLD: Fundo #07070A nas aberturas, papel #F4F4F1 nas seções de leitura, tinta #0B0B0F. Verde-limão #C8F25A só na linha rotativa, nos destaques de frase e no CTA de WhatsApp em contorno; CTA principal monocromático (branco no escuro, tinta no claro) e hover dos cards em tinta; oliva #5F7A12 só como sublinhado de link em seção clara. Host Grotesk em corpo grande com tracking negativo; Martian Mono nos números e no log. Botões em pílula, cards brancos com raio 22px, botão flutuante de WhatsApp.

STORY: O visitante lê "Processos melhores com" e vê a linha trocar entre agentes, automações e sistemas, entende o ponto de melhoria, vê as capacidades e os cases, e inicia o projeto no WhatsApp.

FIRST VIEWPORT: Escuro. Nav com logo quatroventos, sete links (quatro hubs com submenu, Cases, Sobre, Contato) e pílula roxa. H1 em duas linhas à esquerda, a segunda em limão rotativa, ocupando cerca de 55% da largura. Lead curto e dois CTAs em pílula abaixo. Log de agente desfocado ao fundo, marcado como ilustrativo. A seção clara começa logo abaixo da dobra.

Assinatura: a linha rotativa do H1 na home (campo "Linha rotativa" do hero no CMS); heros de hub e serviço mantêm o H1 aprovado estático sobre o mesmo log, e ganham a linha rotativa quando houver termos aprovados por página. Movimento: rolagem vertical da linha, log em deriva lenta, cards de navegação que sobem e ficam tinta no hover; tudo desligado com movimento reduzido.

Mock aprovado: `.impeccable/mocks/hifi/v2-rotativo.html` (build guiado por código; sem comp gerado, ferramenta de imagem indisponível na sessão).

FORM: Linha rotativa, opção 2 da rodada restrita a Vime e Huge, fora da lista sorteada. seed:e4b29f60fab72c6e

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance
