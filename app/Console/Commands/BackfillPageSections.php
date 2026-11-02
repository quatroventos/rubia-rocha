<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Support\Locales;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Completa as páginas do CMS com a estrutura de database/data/pages sem sobrescrever conteúdo:
 * chaves ausentes nas seções existentes são preenchidas e seções ausentes são inseridas na posição equivalente.
 */
class BackfillPageSections extends Command
{
    protected $signature = 'cms:backfill-sections {slug?* : Slugs das páginas; vazio processa todas} {--dry-run : Só lista as mudanças}';

    protected $description = 'Completa as seções das páginas do CMS com a estrutura das páginas de referência, sem sobrescrever conteúdo.';

    /**
     * Campos usados para confirmar que duas linhas de repeater são o mesmo item antes de completar chaves.
     *
     * @var list<string>
     */
    private const ROW_IDENTITY = ['name', 'title', 'question', 'id'];

    public function handle(): int
    {
        $only = array_filter((array) $this->argument('slug'));
        $dryRun = (bool) $this->option('dry-run');
        $changedPages = 0;

        foreach (File::glob(database_path('data/pages/*.json')) as $file) {
            $reference = json_decode(File::get($file), true, flags: JSON_THROW_ON_ERROR);
            $slug = (string) ($reference['slug'] ?? '');

            if ($slug === '' || ($only !== [] && ! in_array($slug, $only, true))) {
                continue;
            }

            $page = Page::query()->where('slug', $slug)->where('locale', Locales::default())->first();
            if ($page === null) {
                $this->warn($slug.': página não existe no CMS, ignorada.');

                continue;
            }

            $content = is_array($page->content) ? $page->content : [];
            $current = is_array($content['sections'] ?? null) ? array_values($content['sections']) : [];
            [$sections, $log] = $this->merge($current, is_array($reference['sections'] ?? null) ? $reference['sections'] : []);

            if ($log === []) {
                continue;
            }

            $changedPages++;
            $this->line('<info>'.$slug.'</info>');
            foreach ($log as $entry) {
                $this->line('  '.$entry);
            }

            if ($dryRun) {
                continue;
            }

            $page->revisions()->create([
                'snapshot' => $page->revisionSnapshot(),
                'created_at' => now(),
            ]);
            $page->content = [...$content, 'sections' => $sections];
            $page->save();
        }

        $this->info($changedPages === 0
            ? 'Nenhuma página precisa de ajuste.'
            : ($dryRun ? $changedPages.' página(s) seriam ajustadas (dry-run).' : $changedPages.' página(s) ajustadas.'));

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $current
     * @param  list<array<string, mixed>>  $reference
     * @return array{0: list<array<string, mixed>>, 1: list<string>}
     */
    private function merge(array $current, array $reference): array
    {
        $log = [];
        $used = [];
        $lastIndex = -1;

        foreach ($reference as $position => $section) {
            $type = (string) ($section['type'] ?? '');
            $index = $this->findSection($current, $section, $reference, $position, $used);

            if ($index === null) {
                $insertAt = $lastIndex + 1;
                array_splice($current, $insertAt, 0, [$section]);
                $used = array_map(fn (int $item) => $item >= $insertAt ? $item + 1 : $item, $used);
                $used[] = $insertAt;
                $lastIndex = $insertAt;
                $log[] = '+ seção '.$type.' ('.($section['id'] ?? '').') na posição '.($insertAt + 1);

                continue;
            }

            $used[] = $index;
            $lastIndex = max($lastIndex, $index);
            $data = is_array($current[$index]['data'] ?? null) ? $current[$index]['data'] : [];
            $paths = [];
            $current[$index]['data'] = $this->fill($data, is_array($section['data'] ?? null) ? $section['data'] : [], '', $paths);

            foreach ($paths as $path) {
                $log[] = '~ '.$type.' ('.($current[$index]['id'] ?? '').'): '.$path;
            }
        }

        return [array_values($current), $log];
    }

    /**
     * Mesmo id primeiro; senão, a n-ésima seção do mesmo tipo ainda não usada.
     *
     * @param  list<array<string, mixed>>  $current
     * @param  array<string, mixed>  $section
     * @param  list<array<string, mixed>>  $reference
     * @param  list<int>  $used
     */
    private function findSection(array $current, array $section, array $reference, int $position, array $used): ?int
    {
        $id = (string) ($section['id'] ?? '');
        $type = (string) ($section['type'] ?? '');

        foreach ($current as $index => $item) {
            if ($id !== '' && ($item['id'] ?? null) === $id && ($item['type'] ?? null) === $type && ! in_array($index, $used, true)) {
                return $index;
            }
        }

        $ordinal = count(array_filter(array_slice($reference, 0, $position), fn ($item) => ($item['type'] ?? null) === $type));
        $seen = 0;

        foreach ($current as $index => $item) {
            if (($item['type'] ?? null) !== $type) {
                continue;
            }
            if ($seen === $ordinal) {
                return in_array($index, $used, true) ? null : $index;
            }
            $seen++;
        }

        return null;
    }

    /**
     * Acrescenta só as chaves ausentes; listas são completadas item a item quando o item é o mesmo.
     *
     * @param  array<string|int, mixed>  $current
     * @param  array<string|int, mixed>  $reference
     * @param  list<string>  $paths
     * @return array<string|int, mixed>
     */
    private function fill(array $current, array $reference, string $prefix, array &$paths): array
    {
        if (array_is_list($reference) && array_is_list($current)) {
            foreach ($reference as $index => $row) {
                if (! is_array($row) || array_is_list($row) || ! is_array($current[$index] ?? null) || ! $this->sameRow($current[$index], $row)) {
                    continue;
                }
                $current[$index] = $this->fill($current[$index], $row, $prefix.'['.$index.']', $paths);
            }

            return $current;
        }

        foreach ($reference as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (! array_key_exists($key, $current)) {
                $current[$key] = $value;
                $paths[] = $path;

                continue;
            }

            if (is_array($value) && is_array($current[$key])) {
                $current[$key] = $this->fill($current[$key], $value, $path, $paths);
            }
        }

        return $current;
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $reference
     */
    private function sameRow(array $current, array $reference): bool
    {
        foreach (self::ROW_IDENTITY as $key) {
            if (isset($current[$key], $reference[$key]) && is_string($current[$key]) && is_string($reference[$key])) {
                return trim($current[$key]) === trim($reference[$key]);
            }
        }

        return true;
    }
}
