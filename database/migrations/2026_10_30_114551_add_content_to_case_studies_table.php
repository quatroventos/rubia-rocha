<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_studies', function (Blueprint $table) {
            $table->json('content')->nullable()->after('testimonial');
        });

        DB::table('case_studies')->orderBy('id')->each(function (object $row) {
            DB::table('case_studies')->where('id', $row->id)->update([
                'content' => json_encode(['sections' => $this->sectionsFrom($row)], JSON_UNESCAPED_UNICODE),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('case_studies', function (Blueprint $table) {
            $table->dropColumn('content');
        });
    }

    /**
     * Converte os blocos fixos antigos (desafio, solução, prints, stack, resultados, depoimento) em seções.
     *
     * @return list<array{id: string, type: string, data: array<string, mixed>}>
     */
    private function sectionsFrom(object $row): array
    {
        $decode = fn (?string $json): array => is_array($decoded = json_decode((string) $json, true)) ? $decoded : [];
        $sections = [];
        $add = function (string $type, array $data) use (&$sections) {
            $sections[] = ['id' => strtolower((string) Str::ulid()), 'type' => $type, 'data' => $data];
        };

        if (filled($row->challenge)) {
            $add('site.prose', ['title' => 'Desafio', 'body' => $row->challenge]);
        }

        $deliverables = array_values(array_filter($decode($row->deliverables), fn ($item) => is_string($item) && trim($item) !== ''));
        if (filled($row->solution) || $deliverables !== []) {
            $list = $deliverables === []
                ? ''
                : '<ul>'.implode('', array_map(fn (string $item) => '<li>'.e($item).'</li>', $deliverables)).'</ul>';
            $add('site.prose', ['title' => 'Solução', 'body' => (string) $row->solution.$list]);
        }

        $gallery = array_values(array_filter($decode($row->gallery), fn ($item) => is_array($item) && filled($item['path'] ?? null)));
        if ($gallery !== []) {
            $add('site.gallery', [
                'title' => 'Prints',
                'items' => array_map(fn (array $item) => [
                    'image' => (string) $item['path'],
                    'caption' => (string) ($item['caption'] ?? ''),
                    'alt' => (string) ($item['alt'] ?? ''),
                ], $gallery),
            ]);
        }

        $stack = array_values(array_filter($decode($row->stack), fn ($item) => is_string($item) && trim($item) !== ''));
        if ($stack !== []) {
            $add('site.list', ['title' => 'Stack', 'items' => array_map(fn (string $item) => ['title' => $item], $stack)]);
        }

        $results = array_values(array_filter($decode($row->results), fn ($item) => is_array($item) && (filled($item['value'] ?? null) || filled($item['label'] ?? null))));
        if ($results !== []) {
            $add('site.results', [
                'title' => 'Resultados',
                'items' => array_map(fn (array $item) => [
                    'value' => (string) ($item['value'] ?? ''),
                    'label' => (string) ($item['label'] ?? ''),
                ], $results),
            ]);
        }

        $testimonial = $decode($row->testimonial);
        if (filled($testimonial['quote'] ?? null)) {
            $add('site.quotes', ['items' => [[
                'quote' => (string) $testimonial['quote'],
                'author' => (string) ($testimonial['author'] ?? ''),
                'company' => (string) ($testimonial['role'] ?? ''),
            ]]]);
        }

        return $sections;
    }
};
