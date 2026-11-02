<?php

namespace App\Console\Commands;

use App\Models\MenuItem;
use App\Models\Page;
use App\Services\SystemOptions;
use App\Support\Locales;
use App\Support\SiteCopy\CopyImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportSiteCopy extends Command
{
    protected $signature = 'site:import-copy
        {--path= : Pasta com os briefs de copy (padrão: ../.impeccable/briefs/copy)}
        {--menus : Reescreve os menus header e footer a partir da página /servicos}
        {--dry-run : Mostra o que seria gravado, sem alterar o banco}';

    protected $description = 'Carrega as páginas e a copy dos briefs no CMS (upsert por slug, com backup em JSON).';

    private const DRAFT_PAGES = ['blog'];

    public function handle(SystemOptions $options): int
    {
        $dir = $this->option('path') ?: base_path('../.impeccable/briefs/copy');
        $files = glob(rtrim((string) $dir, '/').'/[0-9][0-9]-*.md') ?: [];

        if ($files === []) {
            $this->error("Nenhum brief encontrado em {$dir}");

            return self::FAILURE;
        }

        $whatsapp = (string) ($options->site()?->whatsapp_url ?: 'https://wa.me/554130399593');
        $importer = new CopyImporter($whatsapp);
        $pages = $importer->import($files);

        $this->table(
            ['Slug', 'Arquivo', 'Seções', 'Tipos'],
            array_map(fn (array $p) => [
                $p['slug'],
                $p['file'],
                count($p['sections']),
                implode(', ', array_map(fn (array $s) => substr($s['type'], 5), $p['sections'])),
            ], array_values($pages))
        );

        $retired = array_values(array_filter(array_map(
            fn (string $from) => trim($from, '/'),
            array_keys((array) config('redirects', []))
        ), fn (string $slug) => ! isset($pages['/'.$slug])));

        if ($this->option('dry-run')) {
            $this->info(count($pages).' páginas lidas. Rascunho por redirect: '.implode(', ', $retired));

            return self::SUCCESS;
        }

        $backup = $this->backup();
        $this->line("Backup: {$backup}");

        DB::transaction(function () use ($pages, $retired, $importer) {
            foreach ($pages as $page) {
                $model = Page::query()->firstOrNew(['slug' => $page['slug'], 'locale' => Locales::default()]);
                $model->forceFill([
                    'template' => $page['slug'] === 'home' ? 'home' : ($model->template ?: 'custom'),
                    'status' => in_array($page['slug'], self::DRAFT_PAGES, true) ? 'draft' : 'published',
                    'published_at' => $model->published_at ?? now(),
                    'seo_title' => $page['title'],
                    'seo_description' => $page['description'],
                    'robots' => $page['noindex'] ? 'noindex, follow' : 'index, follow, max-image-preview:large',
                    'content' => ['sections' => $page['sections']],
                ])->save();
            }

            Page::query()->where('locale', Locales::default())->whereIn('slug', $retired)->update(['status' => 'draft']);

            if ($this->option('menus')) {
                $this->writeMenus($importer->serviceTree());
            }
        });

        $options->flush();

        $this->info(count($pages).' páginas gravadas. Em rascunho: '.implode(', ', array_merge($retired, self::DRAFT_PAGES)));

        return self::SUCCESS;
    }

    private function backup(): string
    {
        $path = storage_path('app/backups/site-copy-'.now()->format('Ymd-His').'.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'pages' => Page::query()->get()->map->getAttributes()->all(),
            'menu_items' => MenuItem::query()->get()->map->getAttributes()->all(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    /**
     * @param  list<array{label: string, href: string, children: list<array{label: string, href: string}>}>  $tree
     */
    private function writeMenus(array $tree): void
    {
        $short = [
            '/inteligencia-artificial' => 'Inteligência artificial',
            '/desenvolvimento-de-software' => 'Software',
            '/desenvolvimento-web' => 'Web',
            '/suporte-e-infraestrutura' => 'Suporte e infraestrutura',
        ];

        MenuItem::query()->where('locale', Locales::default())->whereNotNull('parent_id')->delete();
        MenuItem::query()->where('locale', Locales::default())->delete();

        $sort = 0;
        foreach ($tree as $hub) {
            $parent = $this->menu('header', $short[$hub['href']] ?? $hub['label'], $hub['href'], null, $sort++);
            foreach ($hub['children'] as $i => $child) {
                $this->menu('header', $child['label'], $child['href'], $parent->id, $i);
            }
        }
        foreach ([['Cases', '/cases'], ['Sobre', '/sobre'], ['Contato', '/contato']] as [$label, $href]) {
            $this->menu('header', $label, $href, null, $sort++);
        }

        $sort = 0;
        foreach ($tree as $hub) {
            $parent = $this->menu('footer', $hub['label'], $hub['href'], null, $sort++);
            foreach ($hub['children'] as $i => $child) {
                $this->menu('footer', $child['label'], $child['href'], $parent->id, $i);
            }
        }

        $company = $this->menu('footer', 'Empresa', '/sobre', null, $sort);
        foreach ([
            ['Todos os serviços', '/servicos'],
            ['Sobre', '/sobre'],
            ['Cases', '/cases'],
            ['Contato', '/contato'],
            ['Diagnóstico gratuito de site', '/diagnostico-de-site'],
            ['Suporte ao cliente', CopyImporter::FRESHDESK_URL],
        ] as $i => [$label, $href]) {
            $this->menu('footer', $label, $href, $company->id, $i);
        }
    }

    private function menu(string $group, string $label, string $href, ?int $parentId, int $sort): MenuItem
    {
        $pageId = str_starts_with($href, '/')
            ? Page::query()->where('locale', Locales::default())->where('slug', $href === '/' ? 'home' : trim($href, '/'))->value('id')
            : null;

        return MenuItem::query()->create([
            'parent_id' => $parentId,
            'label' => $label,
            'href' => $href,
            'page_id' => $pageId,
            'group' => $group,
            'sort' => $sort,
            'is_active' => true,
        ]);
    }
}
