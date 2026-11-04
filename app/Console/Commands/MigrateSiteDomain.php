<?php

namespace App\Console\Commands;

use App\Services\SystemOptions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Troca links absolutos do domínio antigo (local, túnel ou o domínio anterior) pelo domínio de publicação
 * em todo o conteúdo do CMS. Só casa o host exato: subdomínios externos (ex.: freshdesk) não são tocados.
 * Atualiza direto no banco, sem eventos de model, e roda o prerender uma vez no fim.
 */
class MigrateSiteDomain extends Command
{
    protected $signature = 'site:migrate-domain
        {to? : Domínio de publicação, ex.: https://www.rubiarochafotografia.com.br}
        {--from=* : Domínio antigo (repita para vários). Sem a opção: localhost, 127.0.0.1, host.docker.internal e túneis ngrok}
        {--relative : Converte os links do domínio antigo em caminhos relativos (/servicos) em vez de trocar o domínio}
        {--revisions : Inclui o histórico de revisões das páginas}
        {--skip-prerender : Não regenera o HTML pré-renderizado no fim}
        {--dry-run : Só lista o que mudaria}';

    protected $description = 'Migra os links internos gravados no CMS para o domínio de publicação.';

    /**
     * Colunas com conteúdo editorial que pode carregar links. Campos criptografados ficam de fora.
     *
     * @var array<string, list<string>>
     */
    private const COLUMNS = [
        'pages' => ['content', 'canonical', 'og_image'],
        'menu_items' => ['href'],
        'certifications' => ['image_path', 'url'],
        'site_settings' => [
            'logo_path', 'favicon_path', 'seo_og_image', 'tag_header', 'tag_body', 'tag_footer',
            'sitemap_extra', 'form_schemas', 'translations', 'footer_gallery',
        ],
    ];

    /**
     * Hosts de desenvolvimento usados quando --from não é informado.
     *
     * @var list<string>
     */
    private const LOCAL_HOSTS = [
        'localhost',
        '127\.0\.0\.1',
        '0\.0\.0\.0',
        'host\.docker\.internal',
        '[a-z0-9-]+\.ngrok(?:-free)?\.(?:app|io|dev)',
    ];

    public function handle(SystemOptions $options): int
    {
        $relative = (bool) $this->option('relative');
        $target = rtrim(trim((string) $this->argument('to')), '/');

        if (! $relative && preg_match('#^https?://[a-z0-9.-]+(:\d+)?$#i', $target) !== 1) {
            $this->error('Informe o domínio de publicação (ex.: https://www.rubiarochafotografia.com.br) ou use --relative.');

            return self::FAILURE;
        }

        $pattern = $this->pattern();
        if ($pattern === null) {
            $this->error('Algum --from não é um domínio válido (ex.: https://rubiarochafotografia.com.br).');

            return self::FAILURE;
        }

        $columns = self::COLUMNS;
        if ($this->option('revisions')) {
            $columns['page_revisions'] = ['snapshot'];
        }

        $dryRun = (bool) $this->option('dry-run');
        $report = [];

        foreach ($columns as $table => $fields) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $fields = array_values(array_filter($fields, fn (string $field) => Schema::hasColumn($table, $field)));

            DB::table($table)->select(['id', ...$fields])->orderBy('id')->chunkById(200, function ($rows) use ($table, $fields, $pattern, $target, $relative, $dryRun, &$report): void {
                foreach ($rows as $row) {
                    $changes = [];
                    foreach ($fields as $field) {
                        $value = $row->{$field};
                        if (! is_string($value) || $value === '') {
                            continue;
                        }
                        $count = 0;
                        $next = preg_replace_callback(
                            $pattern,
                            fn (array $match) => $this->replacement($match[0][0], substr($value, $match[0][1] + strlen($match[0][0]), 2), $target, $relative),
                            $value,
                            -1,
                            $count,
                            PREG_OFFSET_CAPTURE,
                        );
                        if ($count > 0 && is_string($next) && $next !== $value) {
                            $changes[$field] = $next;
                            $key = $table.'.'.$field;
                            $report[$key] = [
                                'rows' => ($report[$key]['rows'] ?? 0) + 1,
                                'links' => ($report[$key]['links'] ?? 0) + $count,
                            ];
                        }
                    }
                    if ($changes !== [] && ! $dryRun) {
                        DB::table($table)->where('id', $row->id)->update($changes);
                    }
                }
            });
        }

        if ($report === []) {
            $this->info('Nenhum link do domínio antigo encontrado.');

            return self::SUCCESS;
        }

        $this->table(['Campo', 'Registros', 'Links'], collect($report)->map(fn (array $item, string $key) => [$key, $item['rows'], $item['links']])->values()->all());

        if ($dryRun) {
            $this->warn('Simulação: nada foi gravado.');

            return self::SUCCESS;
        }

        $options->flush();
        $this->info('Links migrados'.($relative ? ' para caminhos relativos.' : ' para '.$target.'.'));

        if (! $this->option('skip-prerender')) {
            $this->call('site:prerender');
        }

        return self::SUCCESS;
    }

    /**
     * Regex com os domínios antigos, aceitando barras escapadas de JSON (https:\/\/host) e porta opcional.
     */
    private function pattern(): ?string
    {
        $from = array_filter(array_map(fn ($value) => trim((string) $value), (array) $this->option('from')));
        $hosts = [];

        if ($from === []) {
            $hosts = self::LOCAL_HOSTS;
        }

        foreach ($from as $value) {
            $host = parse_url(str_contains($value, '://') ? $value : 'https://'.$value, PHP_URL_HOST);
            if (! is_string($host) || preg_match('#^[a-z0-9.-]+$#i', $host) !== 1) {
                return null;
            }
            $hosts[] = preg_quote($host, '#');
        }

        return '#https?:(?:\\\\?/){2}(?:'.implode('|', $hosts).')(?::\d+)?(?=$|[/"\'?\#\s<>)\\\\])#i';
    }

    /**
     * No modo relativo, o domínio sem caminho vira "/" para o link não ficar vazio.
     */
    private function replacement(string $match, string $following, string $target, bool $relative): string
    {
        $escaped = str_contains($match, '\\/');

        if (! $relative) {
            return $escaped ? str_replace('/', '\\/', $target) : $target;
        }

        if (str_starts_with($following, '/') || $following === '\\/') {
            return '';
        }

        return $escaped ? '\\/' : '/';
    }
}
