<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Support\Locales;
use Illuminate\Database\Seeder;

/**
 * Ajustes pontuais de texto nas páginas publicadas. Troca o trecho antigo pelo novo
 * só onde o antigo ainda existe: rodar de novo não altera nada e não toca no resto
 * do conteúdo editado pelo painel.
 */
class ContentAdjustmentsSeeder extends Seeder
{
    /**
     * @var array<string, list<array{0: string, 1: string}>>
     */
    private const REPLACEMENTS = [
        'home' => [
            [
                'Uma etapa manual que se repete, uma decisão que depende de alguém procurar informação, um atendimento que espera na fila. É nesse ponto que a IA entra. Partimos do processo real, encontramos onde a inteligência artificial gera ganho e construímos a solução integrada aos sistemas que já sustentam a operação.',
                'Começamos pelo seu processo, não pela ferramenta. Identificamos onde a IA economiza tempo ou acelera uma decisão e entregamos a solução ligada aos sistemas que você já usa.',
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::REPLACEMENTS as $slug => $pairs) {
            $page = Page::query()->where('slug', $slug)->where('locale', Locales::default())->first();

            if ($page === null) {
                $this->command?->warn("Página não encontrada: {$slug}");

                continue;
            }

            $content = $this->replace($page->content, $pairs);

            if ($content === $page->content) {
                $this->command?->line("Sem alterações: {$slug}");

                continue;
            }

            $page->revisions()->create([
                'snapshot' => $page->revisionSnapshot(),
                'created_at' => now(),
            ]);

            $page->content = $content;
            $page->save();

            $this->command?->info("Atualizada: {$slug}");
        }
    }

    /**
     * @param  list<array{0: string, 1: string}>  $pairs
     */
    private function replace(mixed $value, array $pairs): mixed
    {
        if (is_string($value)) {
            foreach ($pairs as [$old, $new]) {
                $value = str_replace($old, $new, $value);
            }

            return $value;
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->replace($item, $pairs), $value);
        }

        return $value;
    }
}
