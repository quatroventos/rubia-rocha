<?php

namespace Database\Seeders;

use App\Models\CaseStudy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Case de demonstração com todas as seções disponíveis para cases, na ordem do catálogo.
 * Usa updateOrCreate pelo slug: rodar de novo restaura o conteúdo de exemplo.
 */
class CaseComponentsSeeder extends Seeder
{
    public const SLUG = 'exemplo-componentes';

    public function run(): void
    {
        CaseStudy::query()->updateOrCreate(
            ['slug' => self::SLUG, 'locale' => 'pt-BR'],
            [
                'status' => 'published',
                'published_at' => now(),
                'client' => 'Cliente Exemplo',
                'title' => 'Vitrine de todos os componentes de case',
                'summary' => 'Case de demonstração com todas as seções disponíveis no editor de cases, para conferir layout, tons e conteúdo.',
                'sector' => 'servicos',
                'services' => [],
                'tags' => ['Demonstração', 'Componentes'],
                'year' => (int) date('Y'),
                'cover_path' => 'assets/cases/seucommerce.jpg',
                'cover_alt' => 'Capa de demonstração',
                'cover_ratio' => '16x9',
                'url' => 'https://seucommerce.com.br/',
                'featured' => false,
                'sort' => 999,
                'seo_title' => 'Vitrine de componentes de case',
                'seo_description' => 'Página de demonstração com todas as seções disponíveis para cases.',
                'content' => ['sections' => $this->sections()],
            ],
        );
    }

    /**
     * @return list<array{id: string, type: string, data: array<string, mixed>}>
     */
    private function sections(): array
    {
        $sections = [
            'site.prose' => [
                'title' => 'Desafio',
                'body' => '<p>O cliente operava com planilhas e um sistema antigo que não conversava com o financeiro. Cada pedido passava por três pessoas antes de ser faturado.</p><p>O objetivo era reduzir o retrabalho e dar visibilidade do processo para a diretoria.</p>',
            ],
            'site.list' => [
                'title' => 'Stack',
                'intro' => '<p>Tecnologias usadas no projeto.</p>',
                'layout' => 'stack',
                'items' => [
                    ['title' => 'React', 'body' => '<p>Painel administrativo e portal do cliente.</p>', 'layer' => 'interface'],
                    ['title' => 'Laravel', 'body' => '<p>API e regras de negócio.</p>', 'layer' => 'aplicacao'],
                    ['title' => 'Workers de fila', 'body' => '<p>Faturamento e envio de notas em segundo plano.</p>', 'layer' => 'aplicacao'],
                    ['title' => 'MySQL', 'body' => '<p>Pedidos, clientes e faturamento.</p>', 'layer' => 'dados'],
                    ['title' => 'Redis', 'body' => '<p>Cache e filas.</p>', 'layer' => 'dados'],
                    ['title' => 'Docker', 'body' => '<p>Mesmo ambiente em desenvolvimento e produção.</p>', 'layer' => 'infra'],
                    ['title' => 'VPS', 'body' => '<p>Servidor dedicado ao projeto.</p>', 'layer' => 'infra'],
                    ['title' => 'ERP', 'body' => '<p>Emissão de nota fiscal.</p>', 'layer' => 'integracoes'],
                    ['title' => 'Gateway de pagamento', 'body' => '<p>Boleto e Pix.</p>', 'layer' => 'integracoes'],
                    ['title' => 'WhatsApp', 'body' => '<p>Avisos de pedido ao cliente.</p>', 'layer' => 'integracoes'],
                ],
                'tone' => 'white',
            ],
            'site.cards' => [
                'title' => 'Módulos',
                'intro' => '<p>O sistema foi dividido em módulos independentes.</p>',
                'items' => [
                    ['title' => 'Pedidos', 'body' => '<p>Entrada de pedidos com validação automática de estoque.</p>'],
                    ['title' => 'Faturamento', 'body' => '<p>Emissão de nota e cobrança integradas ao pedido.</p>'],
                    ['title' => 'Relatórios', 'body' => '<p>Painel com indicadores por período, vendedor e região.</p>'],
                ],
                'columns' => '3',
            ],
            'site.steps' => [
                'title' => 'Como foi feito',
                'intro' => '<p>Projeto entregue em quatro etapas.</p>',
                'items' => [
                    ['title' => 'Diagnóstico', 'body' => '<p>Mapeamento do processo atual e dos gargalos.</p>'],
                    ['title' => 'Protótipo', 'body' => '<p>Telas validadas com a equipe antes do desenvolvimento.</p>'],
                    ['title' => 'Desenvolvimento', 'body' => '<p>Entregas quinzenais com homologação do cliente.</p>'],
                    ['title' => 'Implantação', 'body' => '<p>Migração dos dados e treinamento da equipe.</p>'],
                ],
                'timeline' => true,
                'tone' => 'dark',
            ],
            'site.portfolio' => [
                'title' => 'Entregas do projeto',
                'intro' => '<p>O que foi construído em cada frente.</p>',
                'items' => [
                    ['title' => 'Painel administrativo', 'body' => '<p>Gestão de pedidos, clientes e usuários.</p>'],
                    ['title' => 'Portal do cliente', 'body' => '<p>Acompanhamento de pedidos e segunda via de boleto.</p>'],
                    ['title' => 'Integrações', 'body' => '<p>ERP, gateway de pagamento e transportadoras.</p>'],
                ],
            ],
            'site.logos' => [
                'title' => 'Integrações',
                'intro' => '<p>Sistemas conectados ao projeto.</p>',
                'mono' => true,
                'items' => [
                    ['name' => 'ERP'],
                    ['name' => 'Gateway de pagamento'],
                    ['name' => 'Transportadora'],
                    ['name' => 'WhatsApp'],
                    ['name' => 'E-mail'],
                ],
                'tone' => 'white',
            ],
            'site.quotes' => [
                'title' => 'Depoimento',
                'items' => [
                    ['quote' => '<p>Texto de exemplo de um depoimento literal do cliente sobre o projeto.</p>', 'author' => 'Nome do cliente', 'company' => 'Cargo, Empresa'],
                    ['quote' => '<p>Segundo depoimento de exemplo para conferir o layout com dois itens.</p>', 'author' => 'Outra pessoa', 'company' => 'Cargo, Empresa'],
                ],
            ],
            'site.faq' => [
                'title' => 'Perguntas frequentes',
                'items' => [
                    ['question' => 'Quanto tempo levou o projeto?', 'answer' => '<p>Quatro meses, do diagnóstico à implantação.</p>'],
                    ['question' => 'O sistema antigo foi desligado?', 'answer' => '<p>Sim, depois da migração completa dos dados.</p>'],
                    ['question' => 'Há suporte depois da entrega?', 'answer' => '<p>Sim, com contrato de sustentação mensal.</p>'],
                ],
            ],
            'site.plans' => [
                'title' => 'Planos do produto',
                'intro' => '<p>Planos oferecidos pelo cliente aos usuários finais.</p>',
                'items' => [
                    ['name' => 'Essencial', 'price' => 'R$ 99', 'period' => '/mês', 'body' => '<p>Até 3 usuários.</p>', 'cta' => 'Assinar', 'href' => '/contato'],
                    ['name' => 'Profissional', 'price' => 'R$ 199', 'period' => '/mês', 'body' => '<p>Até 10 usuários e relatórios.</p>', 'cta' => 'Assinar', 'href' => '/contato'],
                    ['name' => 'Empresa', 'price' => 'Sob consulta', 'period' => '', 'body' => '<p>Usuários ilimitados e integrações.</p>', 'cta' => 'Falar com vendas', 'href' => '/contato'],
                ],
                'tone' => 'white',
            ],
            'site.gallery' => [
                'title' => 'Telas',
                'intro' => '<p>Prints do sistema em produção.</p>',
                'items' => [
                    ['image' => '/assets/cases/seucommerce.jpg', 'caption' => 'Página inicial', 'alt' => 'Página inicial do sistema'],
                    ['image' => '/assets/cases/moltec.jpg', 'caption' => 'Calculadora', 'alt' => 'Tela da calculadora'],
                    ['image' => '/assets/cases/cyclolux.jpg', 'caption' => 'Dimensionamento', 'alt' => 'Tela de dimensionamento'],
                ],
            ],
            'site.results' => [
                'title' => 'Resultados',
                'items' => [
                    ['value' => '-60%', 'label' => 'Tempo entre pedido e faturamento'],
                    ['value' => '3x', 'label' => 'Pedidos processados por pessoa'],
                    ['value' => '24h', 'label' => 'Atendimento automatizado'],
                ],
                'tone' => 'dark',
            ],
            'site.cases' => [
                'title' => 'Cases selecionados',
                'items' => ['seucommerce', 'bia'],
                'layout' => 'par',
            ],
            'site.showcase' => [
                'title' => 'Cases em destaque',
                'intro' => '<p>Outros projetos parecidos.</p>',
                'items' => ['firefly-webmaker', 'moltec', 'cyclolux'],
                'placeholders' => [],
            ],
            'site.case-grid' => [
                'title' => 'Grade de cases',
                'intro' => '<p>Mais projetos da Quatro Ventos.</p>',
                'featured' => ['bia'],
                'items' => ['seucommerce', 'moltec', 'ramos-benedetti', 'produtive'],
            ],
            'site.cta' => [
                'title' => 'Quer um projeto como este?',
                'body' => '<p>Conte o processo que precisa melhorar e retornamos com um diagnóstico.</p>',
                'label' => 'Falar com a Quatro Ventos',
                'href' => '/contato',
                'secondary_label' => 'Ver todos os cases',
                'secondary_href' => '/cases',
                'tone' => 'dark',
            ],
            'site.panel' => [
                'title' => 'Painel de exemplo',
                'body' => '<p>Bloco de destaque com preço e chamada.</p>',
                'variant' => 'light',
                'logo_place' => 'brand',
                'logo_caption' => 'Cliente Exemplo',
                'price_from' => 'a partir de',
                'price_value' => 'R$ 1.900',
                'price_term' => '/mês',
                'price_note' => 'Valor ilustrativo.',
                'cta_label' => 'Pedir proposta',
                'cta_href' => '/contato',
                'cta_line' => true,
            ],
            'site.figures' => [
                'items' => [
                    ['prefix' => '+', 'value' => '1.200', 'caption' => 'pedidos por mês'],
                    ['prefix' => '', 'value' => '98%', 'caption' => 'de disponibilidade'],
                    ['prefix' => '', 'value' => '4', 'caption' => 'meses de projeto'],
                ],
                'body' => 'Números ilustrativos do case de demonstração.',
                'cta_label' => 'Ver outros cases',
                'cta_href' => '/cases',
                'tone' => 'white',
            ],
            'site.uses' => [
                'title' => 'Onde o sistema é usado',
                'intro' => '<p>Áreas da empresa atendidas.</p>',
                'items' => [
                    ['title' => 'Comercial', 'text' => 'Entrada e acompanhamento de pedidos.'],
                    ['title' => 'Financeiro', 'text' => 'Faturamento e conciliação.'],
                    ['title' => 'Logística', 'text' => 'Separação e expedição.'],
                    ['title' => 'Diretoria', 'text' => 'Indicadores em tempo real.'],
                ],
            ],
            'site.examples' => [
                'title' => 'Exemplos de uso',
                'intro' => '<p>Situações do dia a dia resolvidas pelo sistema.</p>',
                'items' => [
                    ['title' => 'Pedido urgente', 'text' => 'Prioridade automática na separação.'],
                    ['title' => 'Cliente inadimplente', 'text' => 'Bloqueio de novos pedidos até a regularização.'],
                    ['title' => 'Estoque baixo', 'text' => 'Alerta para compras antes de faltar.'],
                ],
                'more_title' => 'Tem outro processo?',
                'more_text' => 'Mapeamos e automatizamos.',
                'more_label' => 'Falar com a equipe',
                'more_href' => '/contato',
            ],
            'site.glyphs' => [
                'title' => 'Recursos',
                'intro' => '<p>Grade com ícones.</p>',
                'items' => [
                    ['glyph' => 'search', 'title' => 'Busca', 'text' => 'Encontre qualquer pedido.', 'href' => ''],
                    ['glyph' => 'shield', 'title' => 'Segurança', 'text' => 'Perfis e permissões.', 'href' => ''],
                    ['glyph' => 'whatsapp', 'title' => 'WhatsApp', 'text' => 'Avisos ao cliente.', 'href' => ''],
                    ['glyph' => 'calendar', 'title' => 'Agenda', 'text' => 'Entregas programadas.', 'href' => ''],
                    ['glyph' => 'loop', 'title' => 'Automação', 'text' => 'Tarefas recorrentes.', 'href' => ''],
                    ['glyph' => 'support', 'title' => 'Suporte', 'text' => 'Atendimento dedicado.', 'href' => ''],
                ],
                'tone' => 'white',
            ],
            'site.checklist' => [
                'title' => 'O que entregamos',
                'intro' => '<p>Escopo fechado do projeto.</p>',
                'items' => [
                    'Painel administrativo com perfis de acesso',
                    'Portal do cliente',
                    'Integração com ERP e gateway de pagamento',
                    'Migração dos dados do sistema antigo',
                    'Treinamento da equipe',
                ],
            ],
            'site.adhoc' => [
                'title' => 'Sustentação',
                'intro' => '<p>Atendimentos depois da entrega.</p>',
                'left' => [
                    ['icon' => 'update', 'title' => 'Atualizações', 'text' => 'Versões do framework e dependências.'],
                    ['icon' => 'speed', 'title' => 'Desempenho', 'text' => 'Monitoramento e otimização.'],
                    ['icon' => 'shield', 'title' => 'Segurança', 'text' => 'Correções e auditoria.'],
                ],
                'right' => [
                    ['icon' => 'move', 'title' => 'Migração', 'text' => 'Troca de servidor sem parada.'],
                    ['icon' => 'power', 'title' => 'Disponibilidade', 'text' => 'Alertas de queda.'],
                    ['icon' => 'search', 'title' => 'Diagnóstico', 'text' => 'Investigação de erros.'],
                ],
                'screen_url' => 'cliente-exemplo.com.br',
                'log' => ['Backup concluído', 'Dependências atualizadas', 'Certificado renovado'],
                'status' => 'Tudo em ordem',
                'cta_label' => 'Contratar sustentação',
                'cta_href' => '/contato',
            ],
            'site.history' => [
                'title' => 'Linha do tempo',
                'items' => [
                    ['year' => '2024', 'title' => 'Diagnóstico', 'text' => 'Mapeamento do processo.'],
                    ['year' => '2025', 'title' => 'Lançamento', 'text' => 'Sistema em produção.'],
                    ['year' => '2026', 'title' => 'Expansão', 'text' => 'Portal do cliente e novas integrações.'],
                ],
            ],
            'site.method' => [
                'title' => 'Método',
                'intro' => '<p>Ciclo contínuo de melhoria.</p>',
                'center' => 'Operação do cliente',
                'items' => [
                    ['title' => 'Entender', 'text' => 'Processo e gargalos.'],
                    ['title' => 'Desenhar', 'text' => 'Fluxo e telas.'],
                    ['title' => 'Construir', 'text' => 'Entregas curtas.'],
                    ['title' => 'Medir', 'text' => 'Indicadores de uso.'],
                ],
                'tone' => 'dark',
            ],
        ];

        $list = [];
        foreach ($sections as $type => $data) {
            $list[] = ['id' => strtolower((string) Str::ulid()), 'type' => $type, 'data' => $data];
        }

        return $list;
    }
}
