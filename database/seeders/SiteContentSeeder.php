<?php

namespace Database\Seeders;

use App\Cms\PageContentMigrator;
use App\Models\FormOption;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Support\Locales;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class SiteContentSeeder extends Seeder
{
    /**
     * Paginas do site antigo que sairam da navegacao.
     */
    public const LEGACY_PAGES = [
        'criacao-de-sites',
        'desenvolvimento-de-landing-page',
        'desenvolvimento-de-sites-wordpress',
        'desenvolvimento-full-stack',
        'manutencao-de-sites-wordpress',
        'porque-meu-site-nao-vende',
        'consultoria-de-ia',
        'blog',
    ];

    private const PAGE_FIELDS = ['template', 'status', 'seo_title', 'seo_description', 'canonical', 'og_image', 'og_image_alt', 'robots', 'twitter_card'];

    public function run(): void
    {
        $this->settings();
        $this->formOptions();
        $this->pages();
        $this->removeLegacyPages();
        $this->menu();
    }

    private function settings(): void
    {
        SiteSetting::query()->updateOrCreate(
            ['id' => 1],
            [
                'name' => 'Quatro Ventos',
                'tagline' => 'Design, Code, Test, Repeat.',
                'phone' => '(41) 3039-9593',
                'phone_href' => 'tel:+554130399593',
                'email' => 'contato@quatroventos.com.br',
                'whatsapp_url' => 'https://wa.me/554130399593?text=Gostaria%20de%20um%20or%C3%A7amento.',
                'address' => 'Curitiba - PR',
                'map_embed_url' => null,
                'instagram_url' => 'https://instagram.com/quatroventosweb',
                'linkedin_url' => 'https://www.linkedin.com/company/10092951',
                'facebook_url' => 'https://www.facebook.com/quatroventosinternet',
                'og_site_name' => 'Quatro Ventos',
                'og_locale' => 'pt_BR',
                'twitter_card' => 'summary_large_image',
                'seo_separator' => '|',
                'seo_title_template' => '%page% %sep% %site%',
                'seo_description' => 'Melhoramos processos com soluções criativas de IA: agentes, automações e sistemas sob medida, integrados à operação. Web, infraestrutura e sustentação no mesmo time.',
                'seo_og_image' => '/assets/og/home.jpg',
                'seo_og_image_alt' => 'Processos melhores com inteligência artificial. Quatro Ventos.',
                'seo_robots' => 'index, follow, max-image-preview:large',
            ]
        );

        $settings = SiteSetting::query()->find(1);
        if ($settings !== null && empty($settings->client_logos)) {
            $settings->update(['client_logos' => $this->defaultClientLogos()]);
        }
    }

    /**
     * Biblioteca inicial de logos de clientes: a lista da seção Logos da home.
     *
     * @return list<array{name: string, image: string, image_alt: string}>
     */
    private function defaultClientLogos(): array
    {
        $home = json_decode((string) File::get(database_path('data/pages/home.json')), true);
        $section = collect($home['sections'] ?? [])->firstWhere('type', 'site.logos');

        return collect($section['data']['items'] ?? [])
            ->filter(fn ($item) => is_array($item) && is_string($item['image'] ?? null))
            ->map(fn (array $item) => [
                'name' => (string) ($item['name'] ?? ''),
                'image' => $item['image'],
                'image_alt' => (string) ($item['image_alt'] ?? ''),
            ])
            ->values()
            ->all();
    }

    private function formOptions(): void
    {
        FormOption::query()->updateOrCreate(
            ['form' => 'contact', 'field' => 'destino', 'value' => 'Proposta'],
            ['label' => 'Proposta', 'email' => 'contato@quatroventos.com.br', 'sort' => 1]
        );

        FormOption::query()
            ->where('form', 'contact')
            ->where('field', 'destino')
            ->where('value', '!=', 'Proposta')
            ->delete();
    }

    private function pages(): void
    {
        foreach (File::glob(database_path('data/pages/*.json')) as $file) {
            $page = json_decode(File::get($file), true, flags: JSON_THROW_ON_ERROR);
            $content = PageContentMigrator::normalize(['sections' => $page['sections']], $page['template'] ?? 'custom');

            $row = Page::query()->firstOrNew(['slug' => $page['slug'], 'locale' => Locales::default()]);
            $data = array_intersect_key($page, array_flip(self::PAGE_FIELDS)) + ['content' => $content];

            if ($row->exists && $row->content !== $content) {
                $row->revisions()->create([
                    'snapshot' => $row->revisionSnapshot(),
                    'created_at' => now(),
                ]);
            }

            $row->fill($data);

            if ($row->status === 'published' && $row->published_at === null) {
                $row->published_at = now();
            }

            $row->save();
        }
    }

    private function removeLegacyPages(): void
    {
        Page::query()->whereIn('slug', self::LEGACY_PAGES)->get()->each(function (Page $page): void {
            MenuItem::query()->where('page_id', $page->id)->delete();
            $page->translations()->delete();
            $page->delete();
        });
    }

    private function menu(): void
    {
        $services = [
            ['Inteligência artificial', '/inteligencia-artificial', [
                ['Agentes de IA', '/agentes-de-ia'],
                ['Automação com IA e n8n', '/automacao-com-ia'],
                ['IA com os dados da empresa', '/ia-com-dados-da-empresa'],
                ['Integração de LLMs', '/integracao-de-llm'],
                ['Sistemas com IA', '/sistemas-com-ia'],
            ]],
            ['Software', '/desenvolvimento-de-software', [
                ['Software sob medida', '/software-sob-medida'],
                ['SaaS', '/desenvolvimento-de-saas'],
                ['Modernização de sistemas', '/modernizacao-de-sistemas'],
                ['ERP e CRM', '/erp-e-crm'],
                ['UI/UX design', '/ui-ux-design'],
                ['QA e testes', '/qa-e-testes'],
            ]],
            ['Web', '/desenvolvimento-web', [
                ['Sites', '/desenvolvimento-de-sites'],
                ['Landing pages', '/landing-pages'],
                ['E-commerce', '/desenvolvimento-de-ecommerce'],
                ['WordPress', '/sites-wordpress'],
                ['SEO e LLMs', '/seo-e-llms'],
            ]],
            ['Suporte e infraestrutura', '/suporte-e-infraestrutura', [
                ['Sustentação de software', '/sustentacao-de-software'],
                ['Manutenção de sites', '/manutencao-de-sites'],
                ['Recuperação de WordPress', '/recuperacao-de-site-wordpress'],
                ['Hospedagem', '/hospedagem-de-sites'],
                ['VPS e infraestrutura', '/vps-e-infraestrutura'],
            ]],
        ];

        $header = [
            ...$services,
            ['Cases', '/cases', []],
            ['Sobre', '/sobre', []],
            ['Contato', '/contato', []],
        ];

        $footerServices = $services;
        $footerServices[1][0] = 'Engenharia de software';
        $footerServices[2][0] = 'Desenvolvimento web';

        $footer = [
            ...$footerServices,
            ['Empresa', '/sobre', [
                ['Todos os serviços', '/servicos'],
                ['Sobre', '/sobre'],
                ['Cases', '/cases'],
                ['Contato', '/contato'],
                ['Diagnóstico gratuito de site', '/diagnostico-de-site'],
                ['Suporte ao cliente', 'https://quatroventos.freshdesk.com/support/home'],
            ]],
        ];

        $pageIds = Page::query()->originals()->pluck('id', 'slug');

        foreach (['header' => $header, 'footer' => $footer] as $group => $tree) {
            $kept = [];

            foreach ($tree as $sort => [$label, $href, $children]) {
                $parent = $this->menuItem($group, null, $label, $href, $sort, $pageIds->all());
                $kept[] = $parent->id;

                foreach ($children as $childSort => [$childLabel, $childHref]) {
                    $kept[] = $this->menuItem($group, $parent->id, $childLabel, $childHref, $childSort, $pageIds->all())->id;
                }
            }

            MenuItem::query()
                ->where('group', $group)
                ->where('locale', Locales::default())
                ->whereNotIn('id', $kept)
                ->orderByDesc('parent_id')
                ->get()
                ->each->delete();
        }
    }

    /**
     * @param  array<string, int>  $pageIds
     */
    private function menuItem(string $group, ?int $parentId, string $label, string $href, int $sort, array $pageIds): MenuItem
    {
        $slug = str_starts_with($href, '/') ? (trim($href, '/') ?: 'home') : null;

        return MenuItem::query()->updateOrCreate(
            ['group' => $group, 'locale' => Locales::default(), 'parent_id' => $parentId, 'href' => $href],
            [
                'label' => $label,
                'page_id' => $slug !== null ? ($pageIds[$slug] ?? null) : null,
                'sort' => $sort,
                'is_active' => true,
            ]
        );
    }
}
