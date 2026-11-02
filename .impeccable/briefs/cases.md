# Cases

Cases são conteúdo próprio do CMS, editáveis no painel, com página individual, prints e detalhamento. A página `/cases` lista todos; cada página de serviço exibe até dois cases vinculados àquele serviço.

## Modelo no CMS

Case é uma entidade própria (`CaseStudy`, tabela `case_studies`), com tela "Cases" no painel (menu Conteúdo), status rascunho/publicado, lixeira, log de auditoria, SEO próprio e entrada no sitemap.

URL: `/cases/{slug}`. O slug é plano (`digio`, `facilitapay`), sem conflito com as páginas.

### Campos do case

| Campo | Tipo | Obrigatório | Uso |
|---|---|---|---|
| `client` | texto | sim | Nome do cliente |
| `title` | texto | sim | Título do case, focado no processo melhorado |
| `summary` | texto curto (até 200 caracteres) | sim | Resumo usado no card da listagem e no bloco das páginas de serviço |
| `sector` | seleção | sim | Finanças, Educação, Varejo, Saúde, Serviços, Indústria |
| `services` | múltipla seleção de páginas de serviço | sim | Define em quais páginas de serviço o case pode aparecer e o filtro de `/cases` |
| `year` | número | não | Ano de entrega |
| `cover` | imagem | sim | Print principal (16:10), usado no card e no topo do case |
| `gallery` | lista de imagens com legenda | sim, mínimo 2 | Prints do projeto: telas, fluxos, painel, mobile |
| `challenge` | rich text | sim | O processo ou problema antes do projeto |
| `solution` | rich text | sim | O que foi construído e como a IA, a automação ou o software resolveram |
| `deliverables` | lista | não | Itens entregues (agente, integração com ERP, painel, site) |
| `stack` | lista | não | Tecnologias usadas |
| `results` | lista | não | Resultados reais informados pelo cliente ou medidos. Vazio quando não houver dado |
| `testimonial` | depoimento publicado | não | Seleção entre os depoimentos existentes |
| `url` | link | não | Site ou produto publicado |
| `featured` | booleano | não | Destaque no topo de `/cases` e na home |

Regra: `results` só recebe números reais. Sem dado, o case fala do processo e da solução, sem métrica.

## Página do case `/cases/{slug}`

Estrutura:

1. **Hero.** Cliente, título, setor, serviços (links para as páginas de serviço), ano e print de capa.
2. **Desafio.** `challenge`.
3. **Solução.** `solution` com `deliverables`.
4. **Galeria.** `gallery` em sequência, com legenda por print e ampliação em tela cheia.
5. **Stack.** `stack`.
6. **Resultados.** `results`, quando houver.
7. **Depoimento.** `testimonial`, quando houver.
8. **Próximo case.** Outro case do mesmo serviço.
9. **CTA.** "Qual processo poderia render mais?" → WhatsApp.

SEO por case: title `{title} | Case {client} | Quatro Ventos`, description a partir de `summary`, `og_image` = `cover`, JSON-LD `CreativeWork` e `BreadcrumbList` (Início › Cases › Cliente).

## Página `/cases`

1. **Hero.** H1 "Projetos", lead "Processos melhorados com IA, software e web em finanças, educação, varejo e serviços."
2. **Destaques.** Cases com `featured`, em card grande com capa.
3. **Filtro.** Inteligência artificial · Software · Web · Suporte e infraestrutura, a partir de `services`. Filtro por setor opcional.
4. **Grade.** Card com capa, cliente, título, setor e serviços. Clique abre `/cases/{slug}`.
5. **Depoimentos.** Os seis publicados.
6. **Logos.** Lista da home.
7. **CTA.**

## Bloco `cases` nas páginas de serviço

Novo tipo de seção `cases`, colocado antes da FAQ ou do CTA em todas as páginas de serviço e hubs.

| Campo | Uso |
|---|---|
| `title` | Padrão: "Cases" |
| `items` | Seleção manual de 1 ou 2 cases. O seletor do painel mostra só cases cujo `services` inclui a página atual |
| `layout` | `destaque` (1 case, capa grande e 3 prints) ou `par` (2 cases lado a lado) |

Cada case no bloco mostra: capa, cliente, título, `summary`, até 3 prints da `gallery`, serviços e link "Ver case completo" → `/cases/{slug}`.

Sem case selecionado, o bloco não renderiza. Nos hubs, sem seleção manual, entram automaticamente os dois cases mais recentes com qualquer serviço do hub.

## Cases candidatos (pendente do Gabriel)

Portfólio publicado, com o serviço sugerido. Cada case precisa de prints, desafio, solução e, se houver, resultados reais.

| Cliente | Serviços sugeridos |
|---|---|
| Digio | Software sob medida, Desenvolvimento de sites |
| Diálogos de Carreira | Software sob medida, Desenvolvimento de sites |
| Facilitapay | Desenvolvimento de sites |
| Multiversidade PUCPR | Desenvolvimento de sites |
| Hemera | Desenvolvimento de sites |
| Exaustor Estático | E-commerce |
| Ivorecap | E-commerce |
| Aliança Francesa de Curitiba | Desenvolvimento de sites (depoimento publicado) |
| Caudex, Matiza Plast, Enhance Fitness, Plan6, Yogha | Desenvolvimento de sites |

Cases de IA, automação, ERP/CRM, sustentação e infraestrutura: aguardando projetos do Gabriel. Até existirem, essas páginas não exibem o bloco.

## Implementação (feita)

- Model `CaseStudy`, migration `2026_10_29_120000_create_case_studies_table`, policy e ability `cases`.
- Painel: `/gestao/cases` (lista com capa, setor, serviços, status, lixeira) e `/gestao/cases/{id}` (editor com identificação, prints com capa e galeria ordenável, detalhamento, depoimento, publicação, serviços e SEO).
- API do painel: `GET|POST /gestao/api/cases`, `GET|PUT|DELETE /gestao/api/cases/{id}`, `POST /gestao/api/cases/{id}/restore`.
- Público: `/cases/{slug}` com SEO no primeiro HTML e JSON-LD `CreativeWork` + `BreadcrumbList`; `GET /api/cases?servico={slug}` e `GET /api/cases/{slug}`.
- Seções no `SectionCatalog`: `site.cases` (seletor de até 2 cases filtrado pelos cases vinculados à página, layout destaque ou par; sem seleção, entram os cases publicados vinculados à página) e `site.cases-list` (todos os publicados, destaques e filtro por serviço) para a página `/cases`.
- `PublicContent` resolve os cases no payload da página: capa, até 3 prints, resumo, setor e serviços, sem requisição extra.
- Sitemap inclui `/cases/{slug}` publicados.
- O catálogo passou a ter os tipos `site.*` existentes (hero, texto, cards, etapas, portfólio, logos, planos, chamada, contato); a biblioteca do editor lista só esses tipos.
