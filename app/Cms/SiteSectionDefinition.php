<?php

namespace App\Cms;

class SiteSectionDefinition
{
    /**
     * Seções do site da Rubia Rocha Fotografia. O tipo salvo é "site.{preview}".
     *
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            [
                'label' => 'Carrossel de fotos',
                'preview' => 'hero',
                'fields' => [
                    ['type' => 'text', 'name' => 'label', 'label' => 'Nome do carrossel (leitor de tela)'],
                    [
                        'type' => 'repeater',
                        'name' => 'photos',
                        'label' => 'Fotos',
                        'simple' => false,
                        'fields' => self::photoFields(),
                    ],
                ],
            ],
            [
                'label' => 'Apresentação com retrato',
                'preview' => 'intro',
                'fields' => [
                    ...self::imageFields('Retrato'),
                    ['type' => 'text', 'name' => 'kicker', 'label' => 'Linha de abertura'],
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título (cada palavra entra animada)'],
                    ['type' => 'rich', 'name' => 'body', 'label' => 'Texto'],
                    ...self::buttonFields(),
                ],
            ],
            [
                'label' => 'Galerias no papel',
                'preview' => 'sheet',
                'fields' => [
                    ['type' => 'toggle', 'name' => 'leaf', 'label' => 'Exibir a ilustração da folha'],
                    ['type' => 'text', 'name' => 'kicker', 'label' => 'Linha de abertura'],
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'body', 'label' => 'Texto'],
                    ...self::galleryCards(),
                ],
            ],
            [
                'label' => 'Depoimentos',
                'preview' => 'quotes',
                'fields' => [
                    ['type' => 'text', 'name' => 'kicker', 'label' => 'Linha de abertura'],
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    [
                        'type' => 'repeater',
                        'name' => 'items',
                        'label' => 'Depoimentos',
                        'simple' => false,
                        'fields' => [
                            ['type' => 'textarea', 'name' => 'text', 'label' => 'Depoimento'],
                            ['type' => 'text', 'name' => 'name', 'label' => 'Nome'],
                            ['type' => 'media', 'name' => 'photo', 'label' => 'Foto (opcional)', 'kind' => 'image'],
                        ],
                    ],
                ],
            ],
            [
                'label' => 'Chamada final',
                'preview' => 'closing',
                'fields' => [
                    ['type' => 'text', 'name' => 'kicker', 'label' => 'Linha de abertura'],
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'body', 'label' => 'Texto'],
                    ...self::buttonFields(),
                ],
            ],
            [
                'label' => 'Trio de fotos',
                'preview' => 'trio',
                'fields' => [
                    ['type' => 'text', 'name' => 'label', 'label' => 'Descrição do grupo (leitor de tela)'],
                    [
                        'type' => 'repeater',
                        'name' => 'photos',
                        'label' => 'Fotos (3)',
                        'simple' => false,
                        'fields' => self::photoFields(),
                    ],
                ],
            ],
            [
                'label' => 'Quem sou',
                'preview' => 'about',
                'fields' => [
                    ['type' => 'text', 'name' => 'kicker', 'label' => 'Linha de abertura'],
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'body', 'label' => 'Texto'],
                    ['type' => 'text', 'name' => 'list_title', 'label' => 'Título do card'],
                    [
                        'type' => 'repeater',
                        'name' => 'items',
                        'label' => 'Itens do card',
                        'simple' => false,
                        'fields' => [
                            ['type' => 'text', 'name' => 'label', 'label' => 'Texto'],
                            ['type' => 'text', 'name' => 'href', 'label' => 'Link'],
                        ],
                    ],
                    ['type' => 'text', 'name' => 'primary_label', 'label' => 'Botão do card'],
                    ['type' => 'text', 'name' => 'primary_href', 'label' => 'Link do botão do card', 'hint' => 'Vazio usa o WhatsApp do site.'],
                ],
            ],
            [
                'label' => 'Fatos sobre mim',
                'preview' => 'facts',
                'fields' => [
                    ...self::imageFields('Foto'),
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    [
                        'type' => 'repeater',
                        'name' => 'items',
                        'label' => 'Fatos',
                        'simple' => false,
                        'fields' => [
                            ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                            ['type' => 'rich', 'name' => 'body', 'label' => 'Texto'],
                        ],
                    ],
                    ...self::buttonFields(),
                ],
            ],
            [
                'label' => 'Topo da página (foto e título)',
                'preview' => 'folio-hero',
                'fields' => [
                    ...self::imageFields('Foto'),
                    ['type' => 'text', 'name' => 'kicker', 'label' => 'Linha de abertura'],
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título (H1)'],
                    ['type' => 'textarea', 'name' => 'lede', 'label' => 'Subtítulo'],
                    ['type' => 'text', 'name' => 'cta_label', 'label' => 'Botão'],
                    ['type' => 'text', 'name' => 'cta_href', 'label' => 'Link do botão'],
                ],
            ],
            [
                'label' => 'Texto com fotos e álbum',
                'preview' => 'folio',
                'fields' => [
                    [
                        'type' => 'repeater',
                        'name' => 'blocks',
                        'label' => 'Blocos de texto',
                        'simple' => false,
                        'fields' => [
                            ['type' => 'text', 'name' => 'heading', 'label' => 'Título do bloco'],
                            ['type' => 'select', 'name' => 'level', 'label' => 'Nível do título', 'options' => [
                                ['value' => '3', 'label' => 'Subtítulo (H3)'],
                            ], 'hint' => 'Padrão: título (H2).'],
                            ['type' => 'rich', 'name' => 'body', 'label' => 'Texto'],
                            ['type' => 'media', 'name' => 'image', 'label' => 'Foto ao lado (opcional)', 'kind' => 'image'],
                            ['type' => 'text', 'name' => 'image_alt', 'label' => 'Descrição da foto (alt)'],
                        ],
                    ],
                    ['type' => 'text', 'name' => 'cta_label', 'label' => 'Botão no fim do texto'],
                    ['type' => 'text', 'name' => 'cta_href', 'label' => 'Link do botão', 'hint' => 'Vazio usa o WhatsApp do site.'],
                    ['type' => 'text', 'name' => 'album_title', 'label' => 'Título do álbum'],
                    ['type' => 'repeater', 'name' => 'album', 'label' => 'Fotos do álbum', 'simple' => true, 'itemType' => 'media', 'hint' => 'Clique na foto para ampliar no site. Arraste para reordenar.'],
                ],
            ],
            [
                'label' => 'Escolha de galerias',
                'preview' => 'categories',
                'fields' => [
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    ...self::galleryCards(),
                ],
            ],
            [
                'label' => 'Perguntas frequentes',
                'preview' => 'faq',
                'fields' => [
                    ['type' => 'toggle', 'name' => 'leaf', 'label' => 'Exibir a ilustração da folha'],
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    [
                        'type' => 'repeater',
                        'name' => 'items',
                        'label' => 'Perguntas',
                        'simple' => false,
                        'fields' => [
                            ['type' => 'text', 'name' => 'question', 'label' => 'Pergunta'],
                            ['type' => 'textarea', 'name' => 'answer', 'label' => 'Resposta'],
                        ],
                    ],
                ],
            ],
            [
                'label' => 'Contato: foto e texto',
                'preview' => 'talk',
                'fields' => [
                    ...self::imageFields('Foto'),
                    ['type' => 'toggle', 'name' => 'leaf', 'label' => 'Exibir a ilustração da folha'],
                    ['type' => 'text', 'name' => 'kicker', 'label' => 'Linha de abertura'],
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título (H1)'],
                    ['type' => 'rich', 'name' => 'body', 'label' => 'Texto'],
                    ['type' => 'text', 'name' => 'cta_label', 'label' => 'Botão'],
                    ['type' => 'text', 'name' => 'cta_href', 'label' => 'Link do botão'],
                ],
            ],
            [
                'label' => 'Formulário de contato',
                'preview' => 'contact',
                'fields' => [
                    ['type' => 'text', 'name' => 'anchor', 'label' => 'Âncora (id da seção)', 'hint' => 'Padrão: orcamento.'],
                    ['type' => 'text', 'name' => 'kicker', 'label' => 'Linha de abertura'],
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'intro', 'label' => 'Texto'],
                    ['type' => 'text', 'name' => 'whatsapp_label', 'label' => 'Texto do link de WhatsApp', 'hint' => 'O link usa o WhatsApp do site.'],
                    ['type' => 'text', 'name' => 'sent', 'label' => 'Mensagem de envio concluído'],
                    ['type' => 'text', 'name' => 'invalid', 'label' => 'Mensagem de campos inválidos'],
                    ['type' => 'text', 'name' => 'failed', 'label' => 'Mensagem de falha no envio'],
                ],
            ],
            [
                'label' => 'Texto livre',
                'preview' => 'prose',
                'fields' => [
                    ['type' => 'text', 'name' => 'kicker', 'label' => 'Linha de abertura'],
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    ['type' => 'toggle', 'name' => 'heading', 'label' => 'Título é o H1 da página'],
                    ['type' => 'rich', 'name' => 'body', 'label' => 'Texto'],
                    ...self::buttonFields(),
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function photoFields(): array
    {
        return [
            ['type' => 'media', 'name' => 'image', 'label' => 'Foto', 'kind' => 'image'],
            ['type' => 'text', 'name' => 'alt', 'label' => 'Descrição da foto (alt)'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function imageFields(string $label): array
    {
        return [
            ['type' => 'media', 'name' => 'image', 'label' => $label, 'kind' => 'image'],
            ['type' => 'text', 'name' => 'image_alt', 'label' => 'Descrição da foto (alt)'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buttonFields(): array
    {
        return [
            ['type' => 'text', 'name' => 'primary_label', 'label' => 'Botão principal'],
            ['type' => 'text', 'name' => 'primary_href', 'label' => 'Link do botão principal', 'hint' => 'Vazio usa o WhatsApp do site.'],
            ['type' => 'text', 'name' => 'secondary_label', 'label' => 'Botão secundário'],
            ['type' => 'text', 'name' => 'secondary_href', 'label' => 'Link do botão secundário'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function galleryCards(): array
    {
        return [
            ['type' => 'text', 'name' => 'item_label', 'label' => 'Texto do link dos cards', 'hint' => 'Padrão: Ver galeria.'],
            [
                'type' => 'repeater',
                'name' => 'items',
                'label' => 'Cards',
                'simple' => false,
                'fields' => [
                    ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                    ['type' => 'text', 'name' => 'href', 'label' => 'Link'],
                    ['type' => 'media', 'name' => 'image', 'label' => 'Foto', 'kind' => 'image'],
                    ['type' => 'text', 'name' => 'image_alt', 'label' => 'Descrição da foto (alt)'],
                ],
            ],
        ];
    }
}
