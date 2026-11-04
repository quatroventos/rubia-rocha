<?php

namespace Database\Seeders;

use App\Cms\PageContentMigrator;
use App\Cms\SectionCatalog;
use App\Models\FormOption;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Support\Locales;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Conteúdo inicial da Rubia Rocha Fotografia. Rodar de novo não apaga o que foi editado no painel:
 * configurações, páginas e menus só são gravados enquanto ainda estiverem com o conteúdo herdado.
 */
class SiteContentSeeder extends Seeder
{
    public const SITE_NAME = 'Rubia Rocha Fotografia';

    /**
     * Páginas do site anterior (RBA/Quatro Ventos) que não existem na Rubia.
     */
    public const LEGACY_PAGES = [
        'agentes-de-ia',
        'automacao-com-ia',
        'cases',
        'consultoria-de-ia',
        'criacao-de-sites',
        'desenvolvimento-de-ecommerce',
        'desenvolvimento-de-landing-page',
        'desenvolvimento-de-saas',
        'desenvolvimento-de-sites',
        'desenvolvimento-de-sites-wordpress',
        'desenvolvimento-de-software',
        'desenvolvimento-full-stack',
        'desenvolvimento-web',
        'diagnostico-de-site',
        'erp-e-crm',
        'hospedagem-de-sites',
        'ia-com-dados-da-empresa',
        'integracao-de-llm',
        'inteligencia-artificial',
        'landing-pages',
        'manutencao-de-sites',
        'manutencao-de-sites-wordpress',
        'modernizacao-de-sistemas',
        'obrigado',
        'porque-meu-site-nao-vende',
        'qa-e-testes',
        'recuperacao-de-site-wordpress',
        'seo-e-llms',
        'servicos',
        'sistemas-com-ia',
        'sites-wordpress',
        'sobre',
        'software-sob-medida',
        'suporte-e-infraestrutura',
        'sustentacao-de-software',
        'ui-ux-design',
        'vps-e-infraestrutura',
        'blog',
    ];

    public const HEADER_MENU = [
        ['Home', '/'],
        ['Quem sou', '/quem-sou'],
        ['Ensaios', '/ensaios'],
        ['Eventos', '/eventos'],
        ['Contato', '/contato'],
    ];

    public const FOOTER_GALLERY = [
        ['/rubia/img/albuns/familia/01-2-fotografia-de-casal-curitiba1-960.webp', 'Ensaio de família', '/ensaios/familia'],
        ['/rubia/img/albuns/casal/01-ensaio-de-casal-curitiba6-960.webp', 'Ensaio de casal', '/ensaios/casal'],
        ['/rubia/img/albuns/gestante/01-fotografia-curitiba-ensaio-de-gestante-ata-felipe43-960.webp', 'Ensaio de gestante', '/ensaios/gestante'],
        ['/rubia/img/albuns/festa-infantil/01-7-fotografia-de-festa-infantil-curitiba22-960.webp', 'Festa infantil', '/eventos/festa-infantil'],
        ['/rubia/img/albuns/mini-wedding/01-mini-wedding-curitiba-capela-nossa-senhora-salette1-960.webp', 'Mini wedding', '/eventos/mini-wedding'],
        ['/rubia/img/albuns/feminino/01-1-ensaio-feminino-ao-ar-livre-curitiba5-960.webp', 'Ensaio feminino', '/ensaios/feminino'],
    ];

    private const PAGE_FIELDS = ['template', 'status', 'seo_title', 'seo_description', 'canonical', 'og_image', 'og_image_alt', 'robots', 'twitter_card'];

    public function run(): void
    {
        $this->settings();
        $this->formOptions();
        $this->removeLegacyPages();
        $this->pages();
        $this->menu();
    }

    private function settings(): void
    {
        $settings = SiteSetting::query()->find(1);

        if ($settings !== null && $settings->name === self::SITE_NAME) {
            return;
        }

        SiteSetting::query()->updateOrCreate(
            ['id' => 1],
            [
                'name' => self::SITE_NAME,
                'tagline' => 'Fotografia de família, gestante, casal e eventos em Curitiba',
                'logo_path' => '/rubia/img/logo-rubia-foto.svg',
                'phone' => '41 98865-8624',
                'phone_href' => 'tel:+5541988658624',
                'email' => null,
                'whatsapp_url' => 'https://wa.me/5541988658624',
                'address' => 'Curitiba - PR',
                'map_embed_url' => null,
                'instagram_url' => 'https://www.instagram.com/rubiarochafotografia/',
                'linkedin_url' => null,
                'facebook_url' => null,
                'client_logos' => null,
                'footer_gallery_title' => 'Galerias',
                'footer_gallery' => array_map(fn (array $item) => [
                    'image' => $item[0],
                    'alt' => $item[1],
                    'href' => $item[2],
                ], self::FOOTER_GALLERY),
                'form_schemas' => null,
                'og_site_name' => self::SITE_NAME,
                'og_locale' => 'pt_BR',
                'twitter_card' => 'summary_large_image',
                'seo_separator' => '|',
                'seo_title_template' => '%page% %sep% %site%',
                'seo_description' => 'Fotografia de famílias, gestantes, casais, festas e mini weddings em Curitiba. Ensaios leves, com tempo para cada momento.',
                'seo_og_image' => null,
                'seo_og_image_alt' => null,
                'seo_robots' => 'index, follow, max-image-preview:large',
            ]
        );
    }

    private function formOptions(): void
    {
        FormOption::query()->where('form', 'contact')->where('field', 'destino')->delete();
    }

    private function removeLegacyPages(): void
    {
        Page::query()->whereIn('slug', self::LEGACY_PAGES)->get()->each(function (Page $page): void {
            MenuItem::query()->where('page_id', $page->id)->delete();
            $page->translations()->delete();
            $page->delete();
        });
    }

    private function pages(): void
    {
        foreach (File::glob(database_path('data/pages/*.json')) as $file) {
            $page = json_decode(File::get($file), true, flags: JSON_THROW_ON_ERROR);
            $content = PageContentMigrator::normalize(['sections' => $page['sections']], $page['template'] ?? 'custom');

            $row = Page::query()->firstOrNew(['slug' => $page['slug'], 'locale' => Locales::default()]);

            if ($row->exists && ! $this->isLegacyContent($row)) {
                continue;
            }

            if ($row->exists) {
                $row->revisions()->create([
                    'snapshot' => $row->revisionSnapshot(),
                    'created_at' => now(),
                ]);
            }

            $fields = array_filter(array_intersect_key($page, array_flip(self::PAGE_FIELDS)), fn ($value) => $value !== null);

            $row->fill($fields + [
                'content' => $content,
                'canonical' => null,
                'og_image' => null,
                'og_image_alt' => null,
            ]);

            if ($row->status === 'published' && $row->published_at === null) {
                $row->published_at = now();
            }

            $row->save();
        }
    }

    /**
     * Conteúdo vazio ou com seções fora do catálogo atual veio do site anterior e pode ser substituído.
     */
    private function isLegacyContent(Page $page): bool
    {
        $sections = is_array($page->content['sections'] ?? null) ? $page->content['sections'] : [];

        if ($sections === []) {
            return true;
        }

        foreach ($sections as $section) {
            if (! is_array($section) || SectionCatalog::find((string) ($section['type'] ?? '')) === null) {
                return true;
            }
        }

        return false;
    }

    private function menu(): void
    {
        $seeded = MenuItem::query()
            ->where('group', 'header')
            ->where('locale', Locales::default())
            ->where('href', '/quem-sou')
            ->exists();

        if ($seeded) {
            return;
        }

        MenuItem::query()
            ->whereIn('group', ['header', 'footer', 'footer_1', 'footer_2'])
            ->orderByDesc('parent_id')
            ->get()
            ->each->delete();

        $pageIds = Page::query()->originals()->pluck('id', 'slug')->all();

        foreach (self::HEADER_MENU as $sort => [$label, $href]) {
            $slug = trim($href, '/') ?: 'home';

            MenuItem::query()->create([
                'group' => 'header',
                'locale' => Locales::default(),
                'parent_id' => null,
                'label' => $label,
                'href' => $href,
                'page_id' => $pageIds[$slug] ?? null,
                'sort' => $sort,
                'is_active' => true,
            ]);
        }
    }
}
