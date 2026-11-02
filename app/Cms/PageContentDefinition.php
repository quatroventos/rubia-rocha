<?php

namespace App\Cms;

class PageContentDefinition
{
    public static function for(?string $template): array
    {
        return match ($template) {
            'home' => self::home(),
            'about' => self::about(),
            'expertise' => self::expertise(),
            'structure' => self::structure(),
            'ai' => self::ai(),
            'esg' => self::esg(),
            'careers' => self::careers(),
            'contact' => self::contact(),
            default => [],
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function cardFields(bool $withIcon = false, bool $withList = false, bool $withNumber = false): array
    {
        $fields = [
            ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
            ['type' => 'text', 'name' => 'lead', 'label' => 'Abertura'],
            ['type' => 'rich', 'name' => 'body', 'label' => 'Texto'],
        ];

        if ($withNumber) {
            array_unshift($fields, ['type' => 'text', 'name' => 'number', 'label' => 'Número']);
        }

        if ($withIcon) {
            $fields[] = ['type' => 'media', 'name' => 'icon', 'label' => 'Ícone', 'kind' => 'image'];
            $fields[] = ['type' => 'text', 'name' => 'icon_alt', 'label' => 'Alt do ícone'];
        }

        if ($withList) {
            $fields[] = ['type' => 'repeater', 'name' => 'list', 'label' => 'Lista', 'simple' => true, 'itemType' => 'text'];
        }

        return $fields;
    }

    private static function cardRepeater(string $name, string $label, bool $withIcon = false, bool $withList = false, bool $withNumber = false): array
    {
        return [
            'type' => 'repeater',
            'name' => $name,
            'label' => $label,
            'simple' => false,
            'fields' => self::cardFields($withIcon, $withList, $withNumber),
        ];
    }

    private static function home(): array
    {
        return [
            [
                'label' => 'Hero',
                'preview' => 'hero',
                'fields' => [
                    ['type' => 'repeater', 'name' => 'hero.title_lines', 'label' => 'Linhas do título', 'simple' => true, 'itemType' => 'text'],
                    ['type' => 'text', 'name' => 'hero.subtitle', 'label' => 'Subtítulo'],
                    ['type' => 'text', 'name' => 'hero.cta_label', 'label' => 'Botão'],
                    ['type' => 'text', 'name' => 'hero.cta_href', 'label' => 'Link do botão'],
                    ['type' => 'media', 'name' => 'hero.image', 'label' => 'Imagem', 'kind' => 'image'],
                    ['type' => 'text', 'name' => 'hero.image_alt', 'label' => 'Alt da imagem'],
                    [
                        'type' => 'repeater',
                        'name' => 'hero.word_cloud',
                        'label' => 'Nuvem de palavras',
                        'simple' => false,
                        'fields' => [
                            ['type' => 'text', 'name' => 'text', 'label' => 'Texto'],
                            ['type' => 'text', 'name' => 'size', 'label' => 'Tamanho'],
                            ['type' => 'toggle', 'name' => 'accent', 'label' => 'Destaque'],
                            ['type' => 'text', 'name' => 'x', 'label' => 'Posição X'],
                            ['type' => 'text', 'name' => 'y', 'label' => 'Posição Y'],
                        ],
                    ],
                ],
            ],
            [
                'label' => 'Pilares',
                'preview' => 'pillars',
                'fields' => [
                    ['type' => 'text', 'name' => 'pillars.eyebrow', 'label' => 'Eyebrow'],
                    ['type' => 'repeater', 'name' => 'pillars.titles', 'label' => 'Títulos', 'simple' => true, 'itemType' => 'text'],
                    ['type' => 'rich', 'name' => 'pillars.intro', 'label' => 'Introdução'],
                    self::cardRepeater('pillars.items', 'Itens'),
                ],
            ],
            [
                'label' => 'Vídeo',
                'preview' => 'video',
                'fields' => [
                    ['type' => 'text', 'name' => 'video.title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'video.body', 'label' => 'Texto'],
                    ['type' => 'media', 'name' => 'video.src', 'label' => 'Arquivo', 'kind' => 'video'],
                ],
            ],
            [
                'label' => 'Soluções',
                'preview' => 'solutions',
                'fields' => [
                    ['type' => 'text', 'name' => 'solutions.title', 'label' => 'Título'],
                    ['type' => 'text', 'name' => 'solutions.subtitle', 'label' => 'Subtítulo'],
                    ['type' => 'media', 'name' => 'solutions.image', 'label' => 'Imagem', 'kind' => 'image'],
                    ['type' => 'text', 'name' => 'solutions.image_alt', 'label' => 'Alt da imagem'],
                    self::cardRepeater('solutions.cards', 'Cards'),
                ],
            ],
            [
                'label' => 'Faixa',
                'preview' => 'strip',
                'fields' => [
                    ['type' => 'media', 'name' => 'strip.image', 'label' => 'Imagem', 'kind' => 'image'],
                    ['type' => 'text', 'name' => 'strip.alt', 'label' => 'Alt'],
                ],
            ],
            [
                'label' => 'Gestão',
                'preview' => 'management',
                'fields' => [
                    ['type' => 'text', 'name' => 'management.title', 'label' => 'Título'],
                    ['type' => 'repeater', 'name' => 'management.paragraphs', 'label' => 'Parágrafos', 'simple' => true, 'itemType' => 'rich'],
                    ['type' => 'media', 'name' => 'management.image', 'label' => 'Imagem', 'kind' => 'image'],
                    ['type' => 'text', 'name' => 'management.image_alt', 'label' => 'Alt da imagem'],
                ],
            ],
            [
                'label' => 'Excelência',
                'preview' => 'excellence',
                'fields' => [
                    ['type' => 'text', 'name' => 'excellence.title', 'label' => 'Título'],
                    ['type' => 'repeater', 'name' => 'excellence.paragraphs', 'label' => 'Parágrafos', 'simple' => true, 'itemType' => 'rich'],
                    ['type' => 'media', 'name' => 'excellence.image', 'label' => 'Imagem', 'kind' => 'image'],
                    ['type' => 'text', 'name' => 'excellence.image_alt', 'label' => 'Alt da imagem'],
                ],
            ],
            [
                'label' => 'Cobertura',
                'preview' => 'coverage',
                'fields' => [
                    ['type' => 'text', 'name' => 'coverage.title', 'label' => 'Título'],
                    ['type' => 'repeater', 'name' => 'coverage.paragraphs', 'label' => 'Parágrafos', 'simple' => true, 'itemType' => 'rich'],
                    ['type' => 'repeater', 'name' => 'coverage.items', 'label' => 'Itens', 'simple' => true, 'itemType' => 'text'],
                    ['type' => 'media', 'name' => 'coverage.image', 'label' => 'Imagem', 'kind' => 'image'],
                    ['type' => 'text', 'name' => 'coverage.image_alt', 'label' => 'Alt da imagem'],
                ],
            ],
            [
                'label' => 'Chamada',
                'preview' => 'cta',
                'fields' => [
                    ['type' => 'text', 'name' => 'cta.title', 'label' => 'Título'],
                    ['type' => 'text', 'name' => 'cta.highlight', 'label' => 'Destaque'],
                    ['type' => 'text', 'name' => 'cta.button_label', 'label' => 'Botão'],
                    ['type' => 'text', 'name' => 'cta.button_href', 'label' => 'Link do botão'],
                ],
            ],
        ];
    }

    private static function about(): array
    {
        return [
            [
                'label' => 'Hero',
                'preview' => 'hero',
                'fields' => [
                    ['type' => 'text', 'name' => 'hero.title', 'label' => 'Título'],
                    ['type' => 'repeater', 'name' => 'hero.paragraphs', 'label' => 'Parágrafos', 'simple' => true, 'itemType' => 'rich'],
                    ['type' => 'media', 'name' => 'hero.image', 'label' => 'Imagem', 'kind' => 'image'],
                    ['type' => 'text', 'name' => 'hero.image_alt', 'label' => 'Alt da imagem'],
                ],
            ],
            [
                'label' => 'Faixa',
                'preview' => 'strip',
                'fields' => [
                    ['type' => 'media', 'name' => 'strip.image', 'label' => 'Imagem', 'kind' => 'image'],
                    ['type' => 'text', 'name' => 'strip.alt', 'label' => 'Alt'],
                ],
            ],
            [
                'label' => 'Cultura',
                'preview' => 'culture',
                'fields' => [
                    ['type' => 'text', 'name' => 'culture.title', 'label' => 'Título'],
                    ['type' => 'repeater', 'name' => 'culture.paragraphs', 'label' => 'Parágrafos', 'simple' => true, 'itemType' => 'rich'],
                    ['type' => 'media', 'name' => 'culture.image', 'label' => 'Imagem', 'kind' => 'image'],
                    ['type' => 'text', 'name' => 'culture.image_alt', 'label' => 'Alt da imagem'],
                ],
            ],
            [
                'label' => 'Missão e visão',
                'preview' => 'mission',
                'fields' => [
                    ['type' => 'text', 'name' => 'mission.title', 'label' => 'Título da missão'],
                    ['type' => 'rich', 'name' => 'mission.body', 'label' => 'Texto da missão'],
                    ['type' => 'text', 'name' => 'vision.title', 'label' => 'Título da visão'],
                    ['type' => 'rich', 'name' => 'vision.body', 'label' => 'Texto da visão'],
                ],
            ],
            [
                'label' => 'Valores',
                'preview' => 'values',
                'fields' => [
                    ['type' => 'text', 'name' => 'values_title', 'label' => 'Título dos valores'],
                    self::cardRepeater('values', 'Valores'),
                    ['type' => 'text', 'name' => 'leaders_title', 'label' => 'Título da liderança'],
                ],
            ],
        ];
    }

    private static function expertise(): array
    {
        return [
            [
                'label' => 'Hero',
                'preview' => 'hero',
                'fields' => [
                    ['type' => 'text', 'name' => 'hero.title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'hero.body', 'label' => 'Texto'],
                    ['type' => 'media', 'name' => 'hero.image', 'label' => 'Imagem', 'kind' => 'image'],
                    ['type' => 'text', 'name' => 'hero.image_alt', 'label' => 'Alt da imagem'],
                ],
            ],
            [
                'label' => 'Cards',
                'preview' => 'cards',
                'fields' => [self::cardRepeater('cards', 'Cards', withIcon: true)],
            ],
        ];
    }

    private static function structure(): array
    {
        return [
            [
                'label' => 'Hero',
                'preview' => 'hero',
                'fields' => [
                    ['type' => 'text', 'name' => 'hero.title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'hero.body', 'label' => 'Texto'],
                    ['type' => 'media', 'name' => 'hero.image', 'label' => 'Imagem', 'kind' => 'image'],
                    ['type' => 'text', 'name' => 'hero.image_alt', 'label' => 'Alt da imagem'],
                    self::cardRepeater('hero.features', 'Destaques'),
                ],
            ],
            [
                'label' => 'Núcleos',
                'preview' => 'specialties',
                'fields' => [
                    ['type' => 'text', 'name' => 'specialties.title', 'label' => 'Título'],
                    ['type' => 'text', 'name' => 'specialties.subtitle', 'label' => 'Subtítulo'],
                    self::cardRepeater('specialties.cards', 'Cards'),
                    ['type' => 'textarea', 'name' => 'specialties.note', 'label' => 'Nota'],
                ],
            ],
            [
                'label' => 'Unidades',
                'preview' => 'units',
                'fields' => [
                    ['type' => 'text', 'name' => 'units.title', 'label' => 'Título'],
                    ['type' => 'text', 'name' => 'units.subtitle', 'label' => 'Subtítulo'],
                    ['type' => 'text', 'name' => 'units.select_label', 'label' => 'Label do seletor'],
                ],
            ],
            [
                'label' => 'Tecnologia',
                'preview' => 'technology',
                'fields' => [
                    ['type' => 'text', 'name' => 'technology.title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'technology.body', 'label' => 'Texto'],
                    self::cardRepeater('technology.mini_cards', 'Cards menores'),
                    ['type' => 'media', 'name' => 'technology.image', 'label' => 'Imagem', 'kind' => 'image'],
                    ['type' => 'text', 'name' => 'technology.image_alt', 'label' => 'Alt da imagem'],
                    self::cardRepeater('technology.wide_cards', 'Cards largos'),
                ],
            ],
            [
                'label' => 'Segurança',
                'preview' => 'security',
                'fields' => [
                    ['type' => 'text', 'name' => 'security.title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'security.body', 'label' => 'Texto'],
                    ['type' => 'rich', 'name' => 'security.statement', 'label' => 'Declaração'],
                    self::cardRepeater('security.items', 'Itens'),
                ],
            ],
            [
                'label' => 'Cultura',
                'preview' => 'culture',
                'fields' => [
                    ['type' => 'text', 'name' => 'culture.title', 'label' => 'Título'],
                    ['type' => 'repeater', 'name' => 'culture.paragraphs', 'label' => 'Parágrafos', 'simple' => true, 'itemType' => 'rich'],
                    ['type' => 'media', 'name' => 'culture.image', 'label' => 'Imagem', 'kind' => 'image'],
                    ['type' => 'text', 'name' => 'culture.image_alt', 'label' => 'Alt da imagem'],
                    self::cardRepeater('culture.items', 'Itens'),
                ],
            ],
        ];
    }

    private static function ai(): array
    {
        return [
            [
                'label' => 'Hero',
                'preview' => 'hero',
                'fields' => [
                    ['type' => 'text', 'name' => 'hero.title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'hero.body', 'label' => 'Texto'],
                    ['type' => 'media', 'name' => 'hero.image', 'label' => 'Imagem', 'kind' => 'image'],
                    ['type' => 'text', 'name' => 'hero.image_alt', 'label' => 'Alt da imagem'],
                ],
            ],
            [
                'label' => 'Propósito',
                'preview' => 'purpose',
                'fields' => [
                    ['type' => 'text', 'name' => 'purpose.title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'purpose.body', 'label' => 'Texto'],
                    self::cardRepeater('purpose.cards', 'Cards'),
                    ['type' => 'media', 'name' => 'purpose.image', 'label' => 'Imagem', 'kind' => 'image'],
                    ['type' => 'text', 'name' => 'purpose.image_alt', 'label' => 'Alt da imagem'],
                ],
            ],
            [
                'label' => 'Princípios',
                'preview' => 'principles',
                'fields' => [
                    ['type' => 'text', 'name' => 'principles.title', 'label' => 'Título'],
                    self::cardRepeater('principles.items', 'Itens'),
                ],
            ],
            [
                'label' => 'Objetivos',
                'preview' => 'goals',
                'fields' => [
                    ['type' => 'text', 'name' => 'goals.title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'goals.body', 'label' => 'Texto'],
                    ['type' => 'media', 'name' => 'goals.image', 'label' => 'Imagem', 'kind' => 'image'],
                    ['type' => 'text', 'name' => 'goals.image_alt', 'label' => 'Alt da imagem'],
                    self::cardRepeater('goals.items', 'Itens', withNumber: true),
                ],
            ],
        ];
    }

    private static function esg(): array
    {
        return [
            [
                'label' => 'Hero',
                'preview' => 'hero',
                'fields' => [
                    ['type' => 'text', 'name' => 'hero.title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'hero.body', 'label' => 'Texto'],
                    ['type' => 'media', 'name' => 'hero.image', 'label' => 'Imagem', 'kind' => 'image'],
                    ['type' => 'text', 'name' => 'hero.image_alt', 'label' => 'Alt da imagem'],
                ],
            ],
            [
                'label' => 'Pilares',
                'preview' => 'pillars',
                'fields' => [
                    ['type' => 'text', 'name' => 'pillars.title', 'label' => 'Título'],
                    ['type' => 'text', 'name' => 'pillars.subtitle', 'label' => 'Subtítulo'],
                    self::cardRepeater('pillars.cards', 'Cards', withList: true, withNumber: true),
                ],
            ],
            [
                'label' => 'Documentos',
                'preview' => 'documents',
                'fields' => [
                    ['type' => 'text', 'name' => 'documents.title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'documents.body', 'label' => 'Texto'],
                    [
                        'type' => 'repeater',
                        'name' => 'documents.cards',
                        'label' => 'Cards',
                        'simple' => false,
                        'fields' => [
                            ['type' => 'text', 'name' => 'id', 'label' => 'ID âncora'],
                            ['type' => 'text', 'name' => 'eyebrow', 'label' => 'Eyebrow'],
                            ['type' => 'text', 'name' => 'title', 'label' => 'Título'],
                            ['type' => 'rich', 'name' => 'body', 'label' => 'Texto'],
                            ['type' => 'text', 'name' => 'href', 'label' => 'Link'],
                            ['type' => 'text', 'name' => 'link_label', 'label' => 'Label do link'],
                            ['type' => 'toggle', 'name' => 'channel', 'label' => 'Canal'],
                        ],
                    ],
                ],
            ],
            [
                'label' => 'Canal de denúncias',
                'preview' => 'ethics',
                'fields' => [
                    ['type' => 'text', 'name' => 'ethics.title', 'label' => 'Título'],
                    ['type' => 'repeater', 'name' => 'ethics.paragraphs', 'label' => 'Parágrafos', 'simple' => true, 'itemType' => 'rich'],
                    ['type' => 'media', 'name' => 'ethics.image', 'label' => 'Imagem', 'kind' => 'image'],
                    ['type' => 'text', 'name' => 'ethics.image_alt', 'label' => 'Alt da imagem'],
                    ['type' => 'media', 'name' => 'ethics.qr_image', 'label' => 'QR Code', 'kind' => 'image'],
                    ['type' => 'text', 'name' => 'ethics.qr_title', 'label' => 'Título do QR'],
                    ['type' => 'rich', 'name' => 'ethics.qr_body', 'label' => 'Texto do QR'],
                ],
            ],
            [
                'label' => 'Conclusão',
                'preview' => 'conclusion',
                'fields' => [
                    ['type' => 'text', 'name' => 'conclusion.title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'conclusion.body', 'label' => 'Texto'],
                ],
            ],
        ];
    }

    private static function careers(): array
    {
        return [
            [
                'label' => 'Hero',
                'preview' => 'hero',
                'fields' => [
                    ['type' => 'text', 'name' => 'hero.title', 'label' => 'Título (aria)'],
                    ['type' => 'media', 'name' => 'hero.image', 'label' => 'Imagem', 'kind' => 'image'],
                    ['type' => 'text', 'name' => 'hero.image_alt', 'label' => 'Alt da imagem'],
                ],
            ],
            [
                'label' => 'Oportunidades',
                'preview' => 'opportunity',
                'fields' => [
                    ['type' => 'text', 'name' => 'opportunity.title', 'label' => 'Título'],
                    ['type' => 'text', 'name' => 'opportunity.lead', 'label' => 'Lead'],
                    ['type' => 'repeater', 'name' => 'opportunity.paragraphs', 'label' => 'Parágrafos', 'simple' => true, 'itemType' => 'rich'],
                    ['type' => 'text', 'name' => 'opportunity.cta_label', 'label' => 'Botão'],
                    ['type' => 'text', 'name' => 'opportunity.cta_href', 'label' => 'Link do botão'],
                ],
            ],
        ];
    }

    private static function contact(): array
    {
        return [
            [
                'label' => 'Hero',
                'preview' => 'hero',
                'fields' => [
                    ['type' => 'text', 'name' => 'hero.title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'hero.body', 'label' => 'Texto'],
                ],
            ],
            [
                'label' => 'Canais',
                'preview' => 'channels',
                'fields' => [self::cardRepeater('channels', 'Canais')],
            ],
            [
                'label' => 'Formulário',
                'preview' => 'form',
                'fields' => [
                    ['type' => 'text', 'name' => 'form.title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'form.body', 'label' => 'Texto'],
                    ['type' => 'text', 'name' => 'form.submit_label', 'label' => 'Botão enviar'],
                    ['type' => 'rich', 'name' => 'form.lgpd_label', 'label' => 'Texto LGPD'],
                ],
            ],
            [
                'label' => 'Mapa',
                'preview' => 'map',
                'fields' => [
                    ['type' => 'text', 'name' => 'map.title', 'label' => 'Título'],
                    ['type' => 'rich', 'name' => 'map.body', 'label' => 'Texto'],
                ],
            ],
        ];
    }
}
