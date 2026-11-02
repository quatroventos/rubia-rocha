<?php

namespace Database\Seeders;

use App\Models\CaseStudy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Cases listados em /cases. Usa firstOrCreate pelo slug: rodar de novo não sobrescreve o que foi editado no painel.
 * Cases sem texto entram como rascunho; as seções do conteúdo são completadas pelo painel.
 * Etiquetas e proporção da capa só são preenchidas em cases que ainda não as têm.
 */
class CaseStudySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->cases() as $sort => $case) {
            $published = $case['status'] === 'published';
            $content = $this->content($case['solution'] ?? null, $case['stack'] ?? []);
            unset($case['solution'], $case['stack']);

            $row = CaseStudy::query()->firstOrCreate(
                ['slug' => $case['slug']],
                array_merge([
                    'locale' => 'pt-BR',
                    'summary' => null,
                    'year' => null,
                    'cover_alt' => $case['client'],
                    'content' => $content,
                    'url' => null,
                    'featured' => false,
                    'sort' => $sort + 1,
                    'published_at' => $published ? now() : null,
                ], $case),
            );

            $this->fillCardFields($row, $case);
        }

        $fiscalHub = CaseStudy::query()->originals()->where('slug', 'fiscal-hub')->first();
        if ($fiscalHub !== null) {
            $this->fillCardFields($fiscalHub, ['tags' => ['Finanças', '2026'], 'cover_ratio' => '16x10']);
        }
    }

    /**
     * @param  list<string>  $stack
     * @return array{sections: list<array{id: string, type: string, data: array<string, mixed>}>}
     */
    private function content(?string $solution, array $stack): array
    {
        $sections = [];

        if (filled($solution)) {
            $sections[] = ['id' => strtolower((string) Str::ulid()), 'type' => 'site.prose', 'data' => ['title' => 'Solução', 'body' => $solution]];
        }

        if ($stack !== []) {
            $sections[] = ['id' => strtolower((string) Str::ulid()), 'type' => 'site.list', 'data' => [
                'title' => 'Stack',
                'items' => array_map(fn (string $item) => ['title' => $item], $stack),
            ]];
        }

        return ['sections' => $sections];
    }

    /**
     * @param  array<string, mixed>  $case
     */
    private function fillCardFields(CaseStudy $row, array $case): void
    {
        $fill = [];

        if (empty($row->tags) && ! empty($case['tags'])) {
            $fill['tags'] = $case['tags'];
        }

        if ($row->cover_ratio === null && isset($case['cover_ratio'])) {
            $fill['cover_ratio'] = $case['cover_ratio'];
        }

        if ($fill !== []) {
            $row->update($fill);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cases(): array
    {
        return [
            [
                'slug' => 'firefly-webmaker',
                'status' => 'published',
                'client' => 'Firefly Host',
                'title' => 'Landing page criada e publicada por agentes de IA',
                'summary' => 'Plataforma em que o cliente descreve o negócio em uma frase e um time de agentes escreve, cria as imagens, monta e publica a landing page.',
                'sector' => 'servicos',
                'services' => ['agentes-de-ia', 'desenvolvimento-de-saas'],
                'tags' => ['Produto SaaS com IA', '2026'],
                'year' => 2026,
                'cover_path' => 'assets/cases/firefly-webmaker.png',
                'cover_alt' => 'Tela do Firefly Webmaker',
                'solution' => '<p>Plataforma em que o cliente descreve o negócio em uma frase e um time de agentes escreve, cria as imagens, monta e publica a landing page, com domínio, hospedagem e SSL. Os ajustes são feitos conversando com o agente no Studio.</p>',
                'url' => 'https://webmaker.fireflyhost.com.br/',
            ],
            [
                'slug' => 'bia',
                'status' => 'published',
                'client' => 'Hive Hub',
                'title' => 'B.I.A, SDR com IA que atende e qualifica leads no WhatsApp',
                'summary' => 'SDR com inteligência artificial que atende leads no WhatsApp e no site a qualquer hora, qualifica a conversa e entrega o lead quente ao comercial.',
                'sector' => 'servicos',
                'services' => ['agentes-de-ia'],
                'tags' => ['SDR com IA', 'Hive Hub'],
                'cover_ratio' => '16x9',
                'cover_path' => 'assets/cases/bia.jpg',
                'cover_alt' => 'Tela da B.I.A, da Hive Hub',
                'solution' => '<p>SDR com inteligência artificial que atende leads no WhatsApp e no site a qualquer hora, entende e responde áudio, qualifica a conversa e entrega o lead quente ao comercial com todo o contexto. Painel de gestão e integração com o CRM.</p>',
                'url' => 'https://bia.hhmkt.com.br/',
            ],
            [
                'slug' => 'seucommerce',
                'status' => 'published',
                'client' => 'SeuCommerce',
                'title' => 'Plataforma de e-commerce por assinatura',
                'summary' => 'Plataforma em que o lojista cria, gerencia e escala a própria loja virtual, com planos, pagamentos, logística e integração com marketplaces.',
                'sector' => 'varejo',
                'services' => ['desenvolvimento-de-saas', 'desenvolvimento-de-ecommerce'],
                'tags' => ['Plataforma de e-commerce', 'SaaS'],
                'cover_ratio' => '16x9',
                'cover_path' => 'assets/cases/seucommerce.jpg',
                'cover_alt' => 'Site da SeuCommerce',
                'solution' => '<p>Plataforma por assinatura em que o lojista cria, gerencia e escala a própria loja virtual: cadastro e escolha de plano pelo site, planos mensais e anuais, meios de pagamento, logística e integração com marketplaces como Mercado Livre, Amazon e Magalu.</p>',
                'url' => 'https://seucommerce.com.br/',
            ],
            [
                'slug' => 'moltec',
                'status' => 'published',
                'client' => 'Moltec',
                'title' => 'Calculadora de molas reconstruída na web',
                'summary' => 'Calculadora de molas de compressão e tração refeita a partir do sistema antigo, com visualização em 2D e 3D, folha de dados em PDF e pedido de orçamento.',
                'sector' => 'industria',
                'services' => ['modernizacao-de-sistemas', 'software-sob-medida'],
                'tags' => ['Indústria', 'Modernização'],
                'cover_ratio' => '16x9',
                'cover_path' => 'assets/cases/moltec.jpg',
                'cover_alt' => 'Moltec Spring Designer',
                'solution' => '<p>Calculadora de molas de compressão e tração reconstruída na web a partir do sistema antigo do cliente: dimensionamento com curva força-comprimento, visualização em 2D e 3D, verificação de fadiga e flambagem, folha de dados em PDF e pedido de orçamento, em português, inglês e espanhol.</p>',
                'url' => 'https://moltec-spring-designer.fpix6w.easypanel.host/#/compressao',
            ],
            [
                'slug' => 'cyclolux',
                'status' => 'published',
                'client' => 'Cyclolux',
                'title' => 'Calculadora industrial de exaustores eólicos',
                'summary' => 'Dimensionamento de exaustores eólicos para galpões industriais, refeito a partir da calculadora antiga, com modelo 3D do galpão e índice de eficiência.',
                'sector' => 'industria',
                'services' => ['modernizacao-de-sistemas', 'software-sob-medida'],
                'tags' => ['Indústria', 'Modernização'],
                'cover_ratio' => '16x9',
                'cover_path' => 'assets/cases/cyclolux.jpg',
                'cover_alt' => 'Calculadora industrial da Cyclolux',
                'solution' => '<p>Dimensionamento de exaustores eólicos para galpões industriais, refeito a partir da calculadora antiga: ambiente, clima, cobertura e dimensões preenchidos em etapas, modelo 3D do galpão atualizado a cada campo e índice de eficiência do projeto.</p>',
                'url' => 'https://cyclolux.com.br/calculadora-industrial/',
            ],
            [
                'slug' => 'ramos-benedetti',
                'status' => 'published',
                'client' => 'Ramos Benedetti Advogados',
                'title' => 'Site institucional sobre CMS próprio em Laravel',
                'summary' => 'Site construído a partir do design aprovado, sobre um CMS próprio em Laravel com painel administrativo e controle de acesso.',
                'sector' => 'servicos',
                'services' => ['desenvolvimento-de-sites'],
                'tags' => ['Jurídico', 'Laravel'],
                'cover_ratio' => '16x9',
                'cover_path' => 'assets/cases/ramos-benedetti.jpg',
                'cover_alt' => 'Site da Ramos Benedetti Advogados',
                'solution' => '<p>Recebemos o design aprovado e construímos o site sobre um CMS próprio em Laravel, com foco em segurança: painel administrativo com controle de acesso, conteúdo editável pelo escritório e imagens otimizadas na publicação.</p>',
                'stack' => ['Laravel'],
                'url' => 'https://ramosbenedetti.adv.br/',
            ],
            [
                'slug' => 'produtive',
                'status' => 'published',
                'client' => 'Produtive',
                'title' => 'Site em WordPress em três idiomas',
                'summary' => 'Site em WordPress fiel ao layout recebido, com conteúdo editável pela Produtive e versões em português, inglês e espanhol.',
                'sector' => 'servicos',
                'services' => ['sites-wordpress'],
                'tags' => ['Carreira e outplacement', 'WordPress'],
                'cover_ratio' => '16x9',
                'cover_path' => 'assets/cases/produtive.jpg',
                'cover_alt' => 'Site da Produtive',
                'solution' => '<p>Recebemos o design e montamos o site em WordPress, fiel ao layout, com páginas de soluções, equipe e conteúdos editáveis pela própria Produtive e versões em português, inglês e espanhol.</p>',
                'stack' => ['WordPress'],
                'url' => 'https://produtive.com.br/',
            ],
            [
                'slug' => 'assessment-produtive',
                'status' => 'draft',
                'client' => 'Produtive',
                'title' => 'Assessment Produtive',
                'sector' => 'servicos',
                'services' => ['software-sob-medida'],
                'tags' => ['Webapp', '2025'],
                'year' => 2025,
                'cover_path' => 'assets/cases/assessment.jpg',
            ],
            [
                'slug' => 'alianca-francesa-brasilia',
                'status' => 'draft',
                'client' => 'Aliança Francesa Brasília',
                'title' => 'Aliança Francesa Brasília',
                'sector' => 'educacao',
                'services' => ['desenvolvimento-de-sites'],
                'tags' => ['Criação de site', '2025'],
                'year' => 2025,
                'cover_path' => 'assets/cases/afbrasilia.jpg',
            ],
            [
                'slug' => 'conexme',
                'status' => 'draft',
                'client' => 'Conexme',
                'title' => 'Conexme',
                'sector' => null,
                'services' => ['software-sob-medida'],
                'tags' => ['Webapp', '2024'],
                'year' => 2024,
                'cover_path' => 'assets/cases/conexme.jpg',
            ],
            [
                'slug' => 'tripfy',
                'status' => 'draft',
                'client' => 'Tripfy',
                'title' => 'Tripfy',
                'sector' => null,
                'services' => ['software-sob-medida', 'desenvolvimento-de-sites'],
                'tags' => ['Site e webapp', '2023'],
                'year' => 2023,
                'cover_path' => 'assets/cases/tripfy.jpg',
            ],
            [
                'slug' => 'alianca-francesa-curitiba',
                'status' => 'draft',
                'client' => 'Aliança Francesa Curitiba',
                'title' => 'Aliança Francesa Curitiba',
                'sector' => 'educacao',
                'services' => ['desenvolvimento-de-sites'],
                'tags' => ['Criação de site', '2022'],
                'year' => 2022,
                'cover_path' => 'assets/cases/afcuritiba.jpg',
            ],
            [
                'slug' => 'multiversidade-pucpr',
                'status' => 'draft',
                'client' => 'Multiversidade PUCPR',
                'title' => 'Multiversidade PUCPR',
                'sector' => 'educacao',
                'services' => ['desenvolvimento-de-sites'],
                'tags' => ['Criação de site', '2021'],
                'year' => 2021,
                'cover_path' => 'assets/cases/puc.jpg',
            ],
        ];
    }
}
