<?php

namespace App\Cms;

class SiteSectionDefinition
{
    /**
     * Animações do hero. O id é o valor salvo em data.visual; vazio usa o log do agente.
     *
     * @var array<string, string>
     */
    public const VISUALS = [
        'flow-agent' => 'Fluxo de agente',
        'flow-automation' => 'Fluxo de automação',
        'rag' => 'Busca em documentos (RAG)',
        'n8n' => 'Workflow n8n',
        'llm' => 'Modelos de linguagem',
        'system' => 'Sistema com IA',
        'software' => 'Software sob medida',
        'saas' => 'SaaS',
        'modernize' => 'Modernização',
        'pipeline' => 'ERP e CRM',
        'ux' => 'UI/UX',
        'qa' => 'QA e testes',
        'sites' => 'Sites',
        'landing' => 'Landing page',
        'ecom' => 'E-commerce',
        'wp' => 'WordPress',
        'seo' => 'SEO e LLMs',
        'sustain' => 'Sustentação',
        'maintain' => 'Manutenção',
        'recover' => 'Recuperação de WordPress',
        'host' => 'Hospedagem',
        'vps' => 'VPS',
    ];

    /**
     * @var array<string, string>
     */
    public const GLYPHS = [
        'search' => 'Lupa',
        'target' => 'Alvo',
        'team' => 'Equipe',
        'shield' => 'Escudo',
        'loop' => 'Ciclo',
        'calendar' => 'Calendário',
        'whatsapp' => 'WhatsApp',
        'mail' => 'E-mail',
        'support' => 'Suporte',
    ];

    /**
     * @var array<string, string>
     */
    public const ADHOC_ICONS = [
        'update' => 'Atualização',
        'speed' => 'Velocidade',
        'shield' => 'Escudo',
        'move' => 'Migração',
        'power' => 'Fora do ar',
        'search' => 'Diagnóstico',
    ];

    /**
     * Camadas do formato Stack, de cima (usuário) para baixo (infraestrutura); integrações ficam fora do sistema.
     *
     * @var array<string, string>
     */
    public const STACK_LAYERS = [
        'interface' => 'Interface: o que o usuário vê',
        'aplicacao' => 'Aplicação: regras do negócio',
        'dados' => 'Dados: onde as informações ficam',
        'infra' => 'Infraestrutura: onde o sistema roda',
        'integracoes' => 'Integrações: sistemas de terceiros',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            [
                'label' => 'Hero',
                'preview' => 'hero',
                'fields' => [
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título', 'hint' => 'Trechos entre *asteriscos* ficam em destaque.'],
                    ['type' => 'repeater', 'name' => 'rotating', 'label' => 'Linha rotativa (a primeira completa o título)', 'simple' => true, 'itemType' => 'text'],
                    ['type' => 'rich', 'name' => 'subtitle', 'label' => 'Abertura'],
                    ['type' => 'repeater', 'name' => 'badges', 'label' => 'Selos', 'simple' => true, 'itemType' => 'text'],
                    ['type' => 'text', 'name' => 'primary_label', 'label' => 'Botão principal'],
                    ['type' => 'text', 'name' => 'primary_href', 'label' => 'Link do botão principal'],
                    ['type' => 'text', 'name' => 'secondary_label', 'label' => 'Botão secundário'],
                    ['type' => 'text', 'name' => 'secondary_href', 'label' => 'Link do botão secundário'],
                    ['type' => 'select', 'name' => 'visual', 'label' => 'Animação', 'options' => self::options(self::VISUALS), 'hint' => 'Padrão: log do agente ao fundo.'],
                    ['type' => 'toggle', 'name' => 'lead_form', 'label' => 'Exibir formulário de lead ao lado'],
                    ['type' => 'text', 'name' => 'lead_title', 'label' => 'Título do formulário de lead'],
                    ['type' => 'text', 'name' => 'lead_whatsapp', 'label' => 'Link de WhatsApp do formulário de lead'],
                ],
            ],
            [
                'label' => 'Lista',
                'preview' => 'list',
                'fields' => self::withCommon([
                    ...self::itemsFields(true, true),
                    ['type' => 'select', 'name' => 'layout', 'label' => 'Formato', 'options' => [
                        ['value' => 'stack', 'label' => 'Stack de tecnologias (camadas)'],
                    ], 'hint' => 'Padrão: a forma da lista é decidida pelo conteúdo. Em cases, a lista com título "Stack" já usa camadas. No formato Stack, cada item precisa da camada.'],
                ]),
            ],
            [
                'label' => 'Texto',
                'preview' => 'prose',
                'fields' => self::withCommon([
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'body', 'label' => 'Texto'],
                ]),
            ],
            [
                'label' => 'Cards',
                'preview' => 'cards',
                'fields' => self::withCommon([
                    ...self::itemsFields(true),
                    ['type' => 'toggle', 'name' => 'feature', 'label' => 'Cards em destaque (grandes)'],
                    ['type' => 'select', 'name' => 'columns', 'label' => 'Colunas', 'options' => [
                        ['value' => '3', 'label' => 'Até 3 por linha'],
                    ], 'hint' => 'Padrão: 4 ou 5 por linha quando a lista tem 4 ou 5 cards.'],
                ]),
            ],
            [
                'label' => 'Etapas',
                'preview' => 'steps',
                'fields' => self::withCommon([
                    ...self::itemsFields(false),
                    ['type' => 'toggle', 'name' => 'timeline', 'label' => 'Linha do tempo animada'],
                ]),
            ],
            [
                'label' => 'Portfólio',
                'preview' => 'portfolio',
                'fields' => self::withCommon(self::itemsFields(false)),
            ],
            [
                'label' => 'Logos',
                'preview' => 'logos',
                'fields' => self::withCommon([
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'intro', 'label' => 'Introdução'],
                    ['type' => 'toggle', 'name' => 'clients', 'label' => 'Layout de clientes (título ao lado)'],
                    ['type' => 'toggle', 'name' => 'mono', 'label' => 'Logos monocromáticos'],
                    ['type' => 'toggle', 'name' => 'own_logos', 'label' => 'Usar logos próprios nesta seção', 'hint' => 'Desligado, a seção mostra os logos de clientes cadastrados em Configuração > Identidade do site.'],
                    ['type' => 'logos', 'name' => 'items', 'label' => 'Logos', 'showIf' => 'own_logos'],
                ]),
            ],
            [
                'label' => 'Depoimentos',
                'preview' => 'quotes',
                'fields' => self::withCommon([
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    ['type' => 'toggle', 'name' => 'even', 'label' => 'Colunas iguais'],
                    [
                        'type' => 'repeater',
                        'name' => 'items',
                        'label' => 'Depoimentos',
                        'simple' => false,
                        'fields' => [
                            ['type' => 'rich', 'name' => 'quote', 'label' => 'Depoimento'],
                            ['type' => 'text', 'name' => 'author', 'label' => 'Autor'],
                            ['type' => 'text', 'name' => 'company', 'label' => 'Empresa'],
                        ],
                    ],
                ]),
            ],
            [
                'label' => 'Perguntas frequentes',
                'preview' => 'faq',
                'fields' => self::withCommon([
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    [
                        'type' => 'repeater',
                        'name' => 'items',
                        'label' => 'Perguntas',
                        'simple' => false,
                        'fields' => [
                            ['type' => 'text', 'name' => 'question', 'label' => 'Pergunta'],
                            ['type' => 'rich', 'name' => 'answer', 'label' => 'Resposta'],
                        ],
                    ],
                ]),
            ],
            [
                'label' => 'Planos (simples)',
                'preview' => 'plans',
                'fields' => self::withCommon([
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'intro', 'label' => 'Introdução'],
                    [
                        'type' => 'repeater',
                        'name' => 'items',
                        'label' => 'Planos',
                        'simple' => false,
                        'fields' => [
                            ['type' => 'text', 'name' => 'name', 'label' => 'Nome'],
                            ['type' => 'text', 'name' => 'price', 'label' => 'Preço'],
                            ['type' => 'text', 'name' => 'period', 'label' => 'Período'],
                            ['type' => 'rich', 'name' => 'body', 'label' => 'Descrição'],
                            ['type' => 'text', 'name' => 'cta', 'label' => 'Botão'],
                            ['type' => 'text', 'name' => 'href', 'label' => 'Link do botão'],
                        ],
                    ],
                ]),
            ],
            [
                'label' => 'Tabela de planos',
                'preview' => 'pricing',
                'fields' => self::withCommon([
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    ['type' => 'toggle', 'name' => 'billing', 'label' => 'Seletor mensal e anual'],
                    ['type' => 'text', 'name' => 'tabs_label', 'label' => 'Rótulo das abas (leitor de tela)', 'hint' => 'Com mais de uma categoria, as categorias viram abas.'],
                    ['type' => 'text', 'name' => 'panel_id', 'label' => 'Id do painel das abas'],
                    ['type' => 'text', 'name' => 'base_title', 'label' => 'Título da base comum'],
                    ['type' => 'textarea', 'name' => 'base_text', 'label' => 'Texto da base comum'],
                    [
                        'type' => 'repeater',
                        'name' => 'categories',
                        'label' => 'Categorias',
                        'simple' => false,
                        'fields' => [
                            ['type' => 'text', 'name' => 'id', 'label' => 'Id'],
                            ['type' => 'text', 'name' => 'tab', 'label' => 'Aba'],
                            ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                            ['type' => 'textarea', 'name' => 'text', 'label' => 'Texto'],
                            [
                                'type' => 'repeater',
                                'name' => 'plans',
                                'label' => 'Planos',
                                'simple' => false,
                                'fields' => [
                                    ['type' => 'text', 'name' => 'name', 'label' => 'Nome'],
                                    ['type' => 'text', 'name' => 'monthly', 'label' => 'Preço mensal (R$)', 'hint' => 'Só números, ex.: 45 ou 45.90.'],
                                    ['type' => 'text', 'name' => 'annual', 'label' => 'Preço anual (R$)'],
                                    ['type' => 'toggle', 'name' => 'featured', 'label' => 'Recomendado'],
                                    ['type' => 'text', 'name' => 'from', 'label' => 'Linha de abertura (ex.: Tudo do Basic, com)'],
                                    ['type' => 'repeater', 'name' => 'adds', 'label' => 'Itens do plano', 'simple' => true, 'itemType' => 'text'],
                                    ['type' => 'text', 'name' => 'href', 'label' => 'Link de contratação'],
                                    ['type' => 'select', 'name' => 'link_key', 'label' => 'Link de compra das Configurações', 'options' => self::options(config('cms.hosting_plans', [])), 'hint' => 'Quando preenchido em Configurações, substitui o link de contratação.'],
                                    ['type' => 'text', 'name' => 'alt_href', 'label' => 'Link "Falar com a equipe"'],
                                ],
                            ],
                            ['type' => 'repeater', 'name' => 'base_items', 'label' => 'Itens da base comum', 'simple' => true, 'itemType' => 'text'],
                        ],
                    ],
                    ['type' => 'rich', 'name' => 'note', 'label' => 'Nota abaixo dos planos'],
                ]),
            ],
            [
                'label' => 'Galeria de prints',
                'preview' => 'gallery',
                'fields' => self::withCommon([
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'intro', 'label' => 'Introdução'],
                    [
                        'type' => 'repeater',
                        'name' => 'items',
                        'label' => 'Prints',
                        'simple' => false,
                        'fields' => [
                            ['type' => 'media', 'name' => 'image', 'label' => 'Imagem', 'kind' => 'image'],
                            ['type' => 'text', 'name' => 'caption', 'label' => 'Legenda'],
                            ['type' => 'text', 'name' => 'alt', 'label' => 'Alt'],
                        ],
                    ],
                ]),
            ],
            [
                'label' => 'Resultados',
                'preview' => 'results',
                'fields' => self::withCommon([
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    [
                        'type' => 'repeater',
                        'name' => 'items',
                        'label' => 'Resultados',
                        'simple' => false,
                        'fields' => [
                            ['type' => 'text', 'name' => 'value', 'label' => 'Valor', 'hint' => 'Só números reais, medidos ou informados pelo cliente.'],
                            ['type' => 'text', 'name' => 'label', 'label' => 'Descrição'],
                        ],
                    ],
                ]),
            ],
            [
                'label' => 'Cases selecionados',
                'preview' => 'cases',
                'fields' => self::withCommon([
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    ['type' => 'cases', 'name' => 'items', 'label' => 'Cases (até 2)', 'max' => 2],
                    ['type' => 'select', 'name' => 'layout', 'label' => 'Layout', 'options' => [
                        ['value' => 'destaque', 'label' => 'Destaque (1 case, capa e 3 prints)'],
                        ['value' => 'par', 'label' => 'Par (2 cases lado a lado)'],
                    ]],
                ]),
            ],
            [
                'label' => 'Cases em destaque',
                'preview' => 'showcase',
                'fields' => self::withCommon([
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'intro', 'label' => 'Introdução'],
                    ['type' => 'cases', 'name' => 'items', 'label' => 'Cases', 'max' => 6, 'all' => true, 'hint' => 'Só os cases publicados aparecem no site.'],
                    ['type' => 'select', 'name' => 'link_to', 'label' => 'Link dos cases', 'options' => [
                        ['value' => 'case', 'label' => 'Página do case'],
                        ['value' => 'url', 'label' => 'Site do case (URL do cadastro)'],
                    ], 'hint' => 'Case sem URL cadastrada continua apontando para a página do case.'],
                    ['type' => 'text', 'name' => 'cta_label', 'label' => 'Texto do link', 'hint' => 'Padrão: Ver case.'],
                    [
                        'type' => 'repeater',
                        'name' => 'placeholders',
                        'label' => 'Espaços reservados (case em breve)',
                        'simple' => false,
                        'fields' => [
                            ['type' => 'text', 'name' => 'name', 'label' => 'Nome'],
                            ['type' => 'repeater', 'name' => 'tags', 'label' => 'Etiquetas', 'simple' => true, 'itemType' => 'text'],
                            ['type' => 'textarea', 'name' => 'text', 'label' => 'Texto'],
                        ],
                    ],
                ]),
            ],
            [
                'label' => 'Grade de cases',
                'preview' => 'case-grid',
                'fields' => self::withCommon([
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'intro', 'label' => 'Introdução'],
                    ['type' => 'cases', 'name' => 'featured', 'label' => 'Case em destaque', 'max' => 1, 'all' => true, 'hint' => 'Só os cases publicados aparecem no site.'],
                    ['type' => 'cases', 'name' => 'items', 'label' => 'Cases da grade', 'max' => 12, 'all' => true, 'hint' => 'Só os cases publicados aparecem no site.'],
                ]),
            ],
            [
                'label' => 'Lista de cases',
                'preview' => 'cases-list',
                'fields' => self::withCommon([
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'intro', 'label' => 'Introdução'],
                    ['type' => 'toggle', 'name' => 'filter', 'label' => 'Exibir filtro por serviço'],
                ]),
            ],
            [
                'label' => 'Busca de cases',
                'preview' => 'cases-browser',
                'fields' => self::withCommon([
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'intro', 'label' => 'Introdução'],
                    [
                        'type' => 'repeater',
                        'name' => 'categories',
                        'label' => 'Categorias do filtro',
                        'simple' => false,
                        'fields' => [
                            ['type' => 'text', 'name' => 'id', 'label' => 'Id'],
                            ['type' => 'text', 'name' => 'label', 'label' => 'Rótulo'],
                            ['type' => 'repeater', 'name' => 'services', 'label' => 'Serviços (slug da página)', 'simple' => true, 'itemType' => 'text'],
                        ],
                    ],
                ]),
            ],
            [
                'label' => 'Chamada',
                'preview' => 'cta',
                'fields' => self::withCommon([
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'body', 'label' => 'Texto'],
                    ['type' => 'text', 'name' => 'label', 'label' => 'Botão'],
                    ['type' => 'text', 'name' => 'href', 'label' => 'Link do botão'],
                    ['type' => 'text', 'name' => 'secondary_label', 'label' => 'Link secundário'],
                    ['type' => 'text', 'name' => 'secondary_href', 'label' => 'Endereço do link secundário'],
                ]),
            ],
            [
                'label' => 'Painel',
                'preview' => 'panel',
                'fields' => self::withCommon([
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'body', 'label' => 'Texto'],
                    ['type' => 'select', 'name' => 'variant', 'label' => 'Fundo do painel', 'options' => [
                        ['value' => 'light', 'label' => 'Claro'],
                    ], 'hint' => 'Padrão: escuro.'],
                    ['type' => 'media', 'name' => 'logo', 'label' => 'Logo', 'kind' => 'image'],
                    ['type' => 'text', 'name' => 'logo_alt', 'label' => 'Alt do logo'],
                    ['type' => 'select', 'name' => 'logo_place', 'label' => 'Posição do logo', 'options' => [
                        ['value' => 'brand', 'label' => 'No lugar do título, com legenda'],
                    ], 'hint' => 'Padrão: acima do texto.'],
                    ['type' => 'text', 'name' => 'logo_caption', 'label' => 'Legenda do logo'],
                    ['type' => 'text', 'name' => 'price_from', 'label' => 'Preço: linha de abertura (ex.: A partir de)'],
                    ['type' => 'text', 'name' => 'price_value', 'label' => 'Preço: valor'],
                    ['type' => 'text', 'name' => 'price_term', 'label' => 'Preço: condição'],
                    ['type' => 'text', 'name' => 'price_note', 'label' => 'Preço: nota'],
                    ['type' => 'text', 'name' => 'cta_label', 'label' => 'Botão'],
                    ['type' => 'text', 'name' => 'cta_href', 'label' => 'Link do botão'],
                    ['type' => 'toggle', 'name' => 'cta_line', 'label' => 'Botão contornado'],
                ]),
            ],
            [
                'label' => 'Números',
                'preview' => 'figures',
                'fields' => self::withCommon([
                    [
                        'type' => 'repeater',
                        'name' => 'items',
                        'label' => 'Números',
                        'simple' => false,
                        'fields' => [
                            ['type' => 'text', 'name' => 'prefix', 'label' => 'Prefixo (ex.: R$)'],
                            ['type' => 'text', 'name' => 'value', 'label' => 'Valor'],
                            ['type' => 'text', 'name' => 'caption', 'label' => 'Legenda'],
                        ],
                    ],
                    ['type' => 'toggle', 'name' => 'mini', 'label' => 'Exibir miniatura da página no ar'],
                    ['type' => 'textarea', 'name' => 'body', 'label' => 'Texto'],
                    ['type' => 'text', 'name' => 'cta_label', 'label' => 'Botão'],
                    ['type' => 'text', 'name' => 'cta_href', 'label' => 'Link do botão'],
                ]),
            ],
            [
                'label' => 'Aplicações (faixa)',
                'preview' => 'uses',
                'fields' => self::withCommon(self::textItems()),
            ],
            [
                'label' => 'Exemplos (grade)',
                'preview' => 'examples',
                'fields' => self::withCommon([
                    ...self::textItems(),
                    ['type' => 'toggle', 'name' => 'plain', 'label' => 'Grade simples, sem numeração'],
                    ['type' => 'text', 'name' => 'more_title', 'label' => 'Card final: título'],
                    ['type' => 'textarea', 'name' => 'more_text', 'label' => 'Card final: texto'],
                    ['type' => 'text', 'name' => 'more_label', 'label' => 'Card final: link'],
                    ['type' => 'text', 'name' => 'more_href', 'label' => 'Card final: endereço do link'],
                ]),
            ],
            [
                'label' => 'Grade com ícones',
                'preview' => 'glyphs',
                'fields' => self::withCommon([
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'intro', 'label' => 'Introdução'],
                    [
                        'type' => 'repeater',
                        'name' => 'items',
                        'label' => 'Itens',
                        'simple' => false,
                        'fields' => [
                            ['type' => 'select', 'name' => 'glyph', 'label' => 'Ícone', 'options' => self::options(self::GLYPHS)],
                            ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                            ['type' => 'text', 'name' => 'text', 'label' => 'Texto'],
                            ['type' => 'text', 'name' => 'href', 'label' => 'Link do título'],
                        ],
                    ],
                ]),
            ],
            [
                'label' => 'Checklist',
                'preview' => 'checklist',
                'fields' => self::withCommon([
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'intro', 'label' => 'Introdução'],
                    ['type' => 'repeater', 'name' => 'items', 'label' => 'Itens', 'simple' => true, 'itemType' => 'text'],
                ]),
            ],
            [
                'label' => 'Atendimentos avulsos',
                'preview' => 'adhoc',
                'fields' => self::withCommon([
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'intro', 'label' => 'Introdução'],
                    self::adhocColumn('left', 'Coluna da esquerda'),
                    self::adhocColumn('right', 'Coluna da direita'),
                    ['type' => 'text', 'name' => 'screen_url', 'label' => 'Endereço na tela'],
                    ['type' => 'repeater', 'name' => 'log', 'label' => 'Linhas do log', 'simple' => true, 'itemType' => 'text'],
                    ['type' => 'text', 'name' => 'status', 'label' => 'Status final'],
                    ['type' => 'text', 'name' => 'cta_label', 'label' => 'Botão'],
                    ['type' => 'text', 'name' => 'cta_href', 'label' => 'Link do botão'],
                ]),
            ],
            [
                'label' => 'História',
                'preview' => 'history',
                'fields' => self::withCommon([
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    [
                        'type' => 'repeater',
                        'name' => 'items',
                        'label' => 'Marcos',
                        'simple' => false,
                        'fields' => [
                            ['type' => 'text', 'name' => 'year', 'label' => 'Ano'],
                            ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                            ['type' => 'rich', 'name' => 'text', 'label' => 'Texto'],
                        ],
                    ],
                ]),
            ],
            [
                'label' => 'Método (órbita)',
                'preview' => 'method',
                'fields' => self::withCommon([
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'intro', 'label' => 'Introdução'],
                    ['type' => 'textarea', 'name' => 'center', 'label' => 'Texto do centro', 'hint' => 'Cada linha vira uma linha no centro da órbita.'],
                    [
                        'type' => 'repeater',
                        'name' => 'items',
                        'label' => 'Etapas (4)',
                        'simple' => false,
                        'fields' => [
                            ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                            ['type' => 'text', 'name' => 'text', 'label' => 'Texto'],
                        ],
                    ],
                ]),
            ],
            [
                'label' => 'Formulário de contato',
                'preview' => 'contact',
                'fields' => self::withCommon([
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'intro', 'label' => 'Introdução'],
                    ['type' => 'select', 'name' => 'variant', 'label' => 'Layout', 'options' => [
                        ['value' => 'split', 'label' => 'Texto e formulário escuro lado a lado'],
                    ]],
                ]),
            ],
        ];
    }

    /**
     * Campos de apresentação comuns a todas as seções, menos o hero.
     *
     * @param  list<array<string, mixed>>  $fields
     * @return list<array<string, mixed>>
     */
    private static function withCommon(array $fields): array
    {
        return [
            ...$fields,
            ['type' => 'select', 'name' => 'tone', 'label' => 'Fundo da seção', 'options' => [
                ['value' => 'light', 'label' => 'Claro'],
                ['value' => 'white', 'label' => 'Branco'],
                ['value' => 'dark', 'label' => 'Escuro'],
            ], 'hint' => 'Seções seguidas com o mesmo fundo se emendam.'],
            ['type' => 'toggle', 'name' => 'mark_draw', 'label' => 'Animar o destaque do título ao rolar'],
            ['type' => 'text', 'name' => 'anchor', 'label' => 'Âncora (id da seção)'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function itemsFields(bool $withLink, bool $withLayer = false): array
    {
        $item = [
            ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
            ['type' => 'rich', 'name' => 'body', 'label' => 'Texto'],
        ];

        if ($withLink) {
            $item[] = ['type' => 'text', 'name' => 'href', 'label' => 'Link'];
        }

        if ($withLayer) {
            $item[] = ['type' => 'select', 'name' => 'layer', 'label' => 'Camada (formato Stack)', 'options' => self::options(self::STACK_LAYERS)];
        }

        return [
            ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
            ['type' => 'rich', 'name' => 'intro', 'label' => 'Introdução'],
            ['type' => 'repeater', 'name' => 'items', 'label' => 'Itens', 'simple' => false, 'fields' => $item],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function textItems(): array
    {
        return [
            ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
            ['type' => 'rich', 'name' => 'intro', 'label' => 'Introdução'],
            [
                'type' => 'repeater',
                'name' => 'items',
                'label' => 'Itens',
                'simple' => false,
                'fields' => [
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    ['type' => 'textarea', 'name' => 'text', 'label' => 'Texto'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function adhocColumn(string $name, string $label): array
    {
        return [
            'type' => 'repeater',
            'name' => $name,
            'label' => $label,
            'simple' => false,
            'fields' => [
                ['type' => 'select', 'name' => 'icon', 'label' => 'Ícone', 'options' => self::options(self::ADHOC_ICONS)],
                ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                ['type' => 'textarea', 'name' => 'text', 'label' => 'Texto'],
            ],
        ];
    }

    /**
     * @param  array<string, string>  $labels
     * @return list<array{value: string, label: string}>
     */
    private static function options(array $labels): array
    {
        return array_map(fn (string $value, string $label) => ['value' => $value, 'label' => $label], array_keys($labels), $labels);
    }
}
