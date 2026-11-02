# Mapa do site

Estrutura no padrão da Innowise: home, hubs de serviço, páginas de serviço, cases, sobre, contato e conteúdo. URLs planas (o CMS aceita `[a-z0-9-]+`); a hierarquia aparece no menu, no breadcrumb e no JSON-LD `BreadcrumbList`.

Status: aprovado. Titles e descriptions espelham a copy em `.impeccable/briefs/copy/`.

## Páginas

### Institucional

| URL | Title | Description |
|---|---|---|
| `/` | Quatro Ventos \| Soluções criativas com inteligência artificial | Melhoramos processos com soluções criativas de IA: agentes, automações e sistemas sob medida, integrados à operação. Web, infraestrutura e sustentação no mesmo time. |
| `/servicos` | Serviços de IA, software, web e infraestrutura \| Quatro Ventos | Inteligência artificial, engenharia de software, desenvolvimento web, suporte e infraestrutura para melhorar processos, do diagnóstico à operação. |
| `/cases` | Cases e projetos \| Quatro Ventos | Cases de IA, software e web da Quatro Ventos para Digio, Hemera, Facilitapay, Multiversidade PUCPR e outras marcas. |
| `/sobre` | Sobre a Quatro Ventos \| Tecnologia para melhorar processos desde 2005 | Desde 2005 em Curitiba, a Quatro Ventos ajuda empresas a melhorar processos com tecnologia. Hoje, com soluções criativas de inteligência artificial. |
| `/contato` | Contato \| Quatro Ventos | Projetos de IA, automação, software e web. WhatsApp e telefone (41) 3039-9593, e-mail ou formulário. |
| `/obrigado` | Mensagem recebida \| Quatro Ventos | noindex |
| `/cases/{slug}` | {title} \| Case {client} \| Quatro Ventos | {summary}. Um por case, template `case`; ver `cases.md`. |

### Inteligência artificial (hub)

| URL | Title | Description |
|---|---|---|
| `/inteligencia-artificial` | Inteligência artificial para melhorar processos \| Quatro Ventos | Soluções criativas de IA para melhorar processos: agentes, automação, IA sobre dados proprietários, integração de LLMs e consultoria de adoção. |
| `/agentes-de-ia` | Agentes de IA para atendimento, vendas e operação \| Quatro Ventos | Agentes e assistentes de IA que atendem clientes, qualificam leads e executam tarefas internas, integrados aos sistemas da operação. |
| `/automacao-com-ia` | Automação de processos com IA e n8n \| Quatro Ventos | Automação de processos com IA, n8n e integrações. Workflows que eliminam etapas manuais e conectam os sistemas da operação. |
| `/ia-com-dados-da-empresa` | IA com os dados da empresa: RAG e base de conhecimento \| Quatro Ventos | Busca e respostas com IA sobre documentos, sistemas e bases internas, com RAG e controle de acesso. |
| `/integracao-de-llm` | Integração de ChatGPT, Claude e Gemini em sistemas \| Quatro Ventos | Integração de LLMs (OpenAI, Claude, Gemini) em sistemas existentes: APIs, prompts, avaliação, custo e segurança. |
| `/sistemas-com-ia` | Sistemas e produtos com IA embarcada \| Quatro Ventos | Sistemas e produtos sob medida com IA no fluxo principal, do discovery à produção. |
| `/consultoria-de-ia` | Consultoria e diagnóstico de adoção de IA \| Quatro Ventos | Diagnóstico dos processos com maior potencial para IA, priorização de casos de uso e plano de implantação. |

### Desenvolvimento de software (hub)

| URL | Title | Description |
|---|---|---|
| `/desenvolvimento-de-software` | Desenvolvimento de software sob medida \| Quatro Ventos | Software sob medida, SaaS, modernização de legado, ERP/CRM, UI/UX e QA para melhorar processos, com IA onde gera ganho. |
| `/software-sob-medida` | Desenvolvimento de software sob medida e web apps \| Quatro Ventos | Web apps e plataformas sob medida, desenhados a partir do processo, com frontend e backend no mesmo time. |
| `/desenvolvimento-de-saas` | Desenvolvimento de SaaS \| Quatro Ventos | Desenvolvimento de produtos SaaS: arquitetura multi-tenant, cobrança, painel e evolução contínua. |
| `/modernizacao-de-sistemas` | Modernização de sistemas legados e auditoria técnica \| Quatro Ventos | Auditoria técnica e modernização de sistemas legados: refatoração, migração de stack e plano priorizado. |
| `/erp-e-crm` | Implantação e integração de ERP e CRM \| Quatro Ventos | ERP e CRM sob medida ou em plataforma (Odoo, Salesforce), integrados aos sistemas da operação. |
| `/ui-ux-design` | UI/UX design e discovery de produto \| Quatro Ventos | Discovery, arquitetura de informação, protótipo e design de interface para produtos digitais. |
| `/qa-e-testes` | QA e testes de software \| Quatro Ventos | Testes manuais e automatizados, regressão e homologação para software e sites. |

### Desenvolvimento web (hub)

| URL | Title | Description |
|---|---|---|
| `/desenvolvimento-web` | Desenvolvimento web: sites, landing pages e e-commerce \| Quatro Ventos | Sites institucionais, landing pages, e-commerce e WordPress integrados ao processo comercial, rápidos, gerenciáveis e preparados para o Google. |
| `/desenvolvimento-de-sites` | Desenvolvimento de sites sob medida \| Quatro Ventos | Sites sob medida, responsivos e gerenciáveis, com arquitetura, protótipo, desenvolvimento, homologação e entrega. |
| `/landing-pages` | Desenvolvimento de landing pages \| Quatro Ventos | Landing pages sob medida, focadas em conversão, integradas a WhatsApp, formulários e ferramentas de marketing. |
| `/desenvolvimento-de-ecommerce` | Desenvolvimento de e-commerce \| Quatro Ventos | Lojas virtuais em Shopify, WooCommerce e Magento, integradas a ERP, pagamentos e logística. |
| `/sites-wordpress` | Sites em WordPress \| Quatro Ventos | Sites em WordPress sob medida, a partir de 6x de R$ 999,00 no cartão. |
| `/seo-e-llms` | SEO e otimização para LLMs \| Quatro Ventos | Otimização de sites para mecanismos de busca e para respostas de IA como ChatGPT, Gemini e Perplexity: SEO técnico, conteúdo, dados estruturados e autoridade. |

### Suporte e infraestrutura (hub)

| URL | Title | Description |
|---|---|---|
| `/suporte-e-infraestrutura` | Suporte, manutenção e infraestrutura \| Quatro Ventos | Sustentação de software, manutenção de sites, recuperação de WordPress, hospedagem e VPS para manter a operação estável. |
| `/sustentacao-de-software` | Sustentação e suporte de software \| Quatro Ventos | Suporte contínuo a sistemas em produção: monitoramento, correções, atualizações e evolução. |
| `/manutencao-de-sites` | Manutenção de sites e WordPress: planos a partir de R$ 350 \| Quatro Ventos | Planos de manutenção de sites e WordPress: atualizações, backups, monitoramento e suporte. Basic R$ 350, Standard R$ 520, Pro R$ 990 por mês. |
| `/recuperacao-de-site-wordpress` | Recuperação de site WordPress invadido \| Quatro Ventos | Recuperação de sites WordPress invadidos, limpeza de malware e restauração do site no ar. |
| `/hospedagem-de-sites` | Hospedagem de sites a partir de R$ 37,50 \| Quatro Ventos | Hospedagem de sites com SSL, e-mails e anti-spam. Planos a partir de R$ 37,50 por mês no anual. |
| `/vps-e-infraestrutura` | VPS e infraestrutura gerenciada \| Quatro Ventos | VPS e infraestrutura própria gerenciada para sistemas, automações, aplicações de IA e sites. |

### Conversão e conteúdo

| URL | Title | Description |
|---|---|---|
| `/diagnostico-de-site` | Diagnóstico gratuito de site \| Quatro Ventos | Relatório de segurança, performance, SEO on-page e velocidade do site, em PDF. |
| `/blog` | Blog: IA aplicada a processos, automação e desenvolvimento \| Quatro Ventos | Artigos sobre inteligência artificial aplicada a processos, automação, desenvolvimento de software e sites. Hub de SEO; artigos entram numa fase seguinte. |

Indústrias (seção da Innowise) ficam fora até existir case real por setor.

## Redirects 301

| URL atual | Destino |
|---|---|
| `/criacao-de-sites` | `/desenvolvimento-de-sites` |
| `/desenvolvimento-de-landing-page` | `/landing-pages` |
| `/criar-landing-page` | `/landing-pages` |
| `/criar-landing-pages` | `/landing-pages` |
| `/criacao-de-landing-pages` | `/landing-pages` |
| `/desenvolvimento-de-sites-wordpress` | `/sites-wordpress` |
| `/desenvolvimento-full-stack` | `/software-sob-medida` |
| `/manutencao-de-sites-wordpress` | `/manutencao-de-sites` |
| `/porque-meu-site-nao-vende` | `/diagnostico-de-site` |

Mantidas no mesmo endereço: `/`, `/desenvolvimento-de-sites`, `/desenvolvimento-de-ecommerce`, `/manutencao-de-sites`, `/recuperacao-de-site-wordpress`, `/hospedagem-de-sites`, `/sobre`, `/obrigado`.

## Menu principal

Inteligência artificial · Software · Web · Suporte e infraestrutura · Cases · Sobre · Contato (WhatsApp em destaque)
