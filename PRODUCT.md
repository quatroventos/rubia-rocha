# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

O CMS Laravel desta base continua: painel, mídia, SEO, formulários, menu e o prerender do front público (`renderToStaticMarkup` + HTML servido pelo Blade). O site público novo é a Rubia Rocha Fotografia. As páginas da primeira leva são estáticas em React, no mesmo prerender, e ainda não substituem as URLs do front anterior.

## Users

Quem procura fotógrafa em Curitiba e região para mini wedding, boda, ensaio de família, gestante, casal ou festa infantil. Chega pela busca, pelo Instagram ou por indicação, olha o trabalho e pede orçamento.

## Product Purpose

Site da Rubia Rocha Fotografia, em https://fotografacuritiba.com.br/. Mostra o trabalho e leva a um orçamento ou a uma conversa. Sucesso é a pessoa entender o que a Rubia fotografa e chamar.

## Positioning

Fotografia lifestyle e documental de momentos reais em Curitiba: famílias, gestantes, casais, festas e mini weddings, sem pose engessada.

## Operating Context

O visitante percorre Home, Quem sou, Ensaios, Eventos, Blog e Contato. O pedido de orçamento cai na caixa de mensagens do CMS. O número de WhatsApp ainda não foi informado.

## Capabilities and Constraints

Páginas da primeira leva, estáticas, fiéis às pranchas de `docs/RubiaFotografia-Site2026-v2.pdf`: Home, Quem sou, Ensaios, Eventos, Blog, um post e Contato. Servidas em `/estudio` até o corte para a raiz. O front anterior permanece em `/` e nas demais URLs do CMS.

Textos e fotos saem do PDF. Títulos que no PDF ainda são marcação de layout (`Título H2: O que nonononono ?`, `Lorem Ipsum nono`) entram como estão na prancha. Não inventar depoimento, preço, telefone, prazo ou cliente fora do PDF.

Formulário de contato desta leva: nome, e-mail e mensagem. Grava em `contact_submissions`.

## Brand Commitments

Nome: Rubia Rocha Fotografia. Instagram informado na prancha: @rubiarochafotografia.

Visual pinado pelo PDF, prancha de sistema: Playfair Display nos títulos, Work Sans no texto e nos botões, fundo claro, botão de orçamento em contorno, botão de WhatsApp em pílula rosada. A logomarca é a do PDF. O mundo visual anterior desta base (Quatro Ventos) não vale para estas páginas.

Voz das pranchas: primeira pessoa, a Rubia falando, em português do Brasil.

## Evidence on Hand

Pranchas: `docs/RubiaFotografia-Site2026-v2.pdf` e `.impeccable/mocks/rubia/`.

Depoimento presente na home: Nathalia Pratt, sobre a festa da filha.

Post presente no blog: "O casamento civil de Shante e Denise no cartório do Portão." e "Checklist: 10 perguntas para fazer antes de fechar o local do mini wedding em Curitiba".

Fotos usadas nas páginas foram extraídas desse PDF. Não há telefone, preço ou outros depoimentos no material.

## Product Principles

- A foto conduz. A interface fica atrás do trabalho.
- Copy e prova são as da prancha. O que o PDF não diz não entra.
- O passo seguinte é um orçamento.
- O CMS permanece. O front novo entra por cima, página a página.
