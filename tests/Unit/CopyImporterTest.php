<?php

namespace Tests\Unit;

use App\Support\SiteCopy\CopyImporter;
use PHPUnit\Framework\TestCase;

class CopyImporterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/copy-importer-'.uniqid();
        mkdir($this->dir);

        file_put_contents($this->dir.'/01-institucional.md', <<<'MD'
# Copy

## `/`

Title: Quatro Ventos | Home
Description: Descrição da home.

### hero
H1: Processos melhores com inteligência artificial.
Linha rotativa: inteligência artificial. · agentes de IA.
CTA primário: Iniciar um projeto → WhatsApp
CTA secundário: Conhecer os serviços → `/servicos`

### etapas — Método
H2: Método
1. **Diagnóstico.** Processos mapeados.
2. **Protótipo.** Ideia validada.

### prova
H2: Clientes
Logos: Digio, Hemera DTVM.
Depoimento em destaque: Ana Lima, Empresa X.

---

## `/servicos`

Title: Serviços | Quatro Ventos
Description: Serviços.

### hero
H1: Serviços

### lista — Web → `/desenvolvimento-web`
- Sites → `/desenvolvimento-de-sites`

---

## `/cases`

### prova — Depoimentos
- "Excelente trabalho." — Ana Lima, Empresa X

---

## `/obrigado` (noindex)

### hero
H1: Mensagem recebida.
MD);

        file_put_contents($this->dir.'/05-suporte.md', <<<'MD'
## `/manutencao-de-sites`

Title: Manutenção | Quatro Ventos
Description: Planos.

### hero
H1: Manutenção de sites.

### planos
H2: Planos
- **Basic — R$ 350,00/mês.** Backups semanais.
Botão de cada plano: Contratar → WhatsApp

### faq
- **Inclui backup?** Sim. Detalhes em `/servicos`.

### etapas
Mesmo bloco da home.

### cta
H2: Fale com a equipe.
Botão: Falar com um especialista → WhatsApp
Link secundário: Clientes: abrir chamado → Freshdesk
MD);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        rmdir($this->dir);

        parent::tearDown();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function import(): array
    {
        return (new CopyImporter('https://wa.me/5500'))->import(glob($this->dir.'/*.md') ?: []);
    }

    /**
     * @param  array<string, mixed>  $page
     * @return array<string, mixed>
     */
    private function section(array $page, string $type, int $nth = 0): array
    {
        $found = array_values(array_filter($page['sections'], fn (array $s) => $s['type'] === $type));

        return $found[$nth]['data'] ?? [];
    }

    public function test_hero_splits_rotating_line_and_resolves_links(): void
    {
        $home = $this->import()['/'];
        $hero = $this->section($home, 'site.hero');

        $this->assertSame('home', $home['slug']);
        $this->assertSame('Quatro Ventos | Home', $home['title']);
        $this->assertSame('Processos melhores com', $hero['title']);
        $this->assertSame(['inteligência artificial.', 'agentes de IA.'], $hero['rotating']);
        $this->assertSame('https://wa.me/5500', $hero['primary_href']);
        $this->assertSame('/servicos', $hero['secondary_href']);
    }

    public function test_proof_block_resolves_logos_and_quotes_by_author(): void
    {
        $home = $this->import()['/'];

        $logos = $this->section($home, 'site.logos');
        $this->assertSame('/assets/clientes/digio.png', $logos['items'][0]['image']);

        $quotes = $this->section($home, 'site.quotes');
        $this->assertSame('Ana Lima', $quotes['items'][0]['author']);
    }

    public function test_plans_faq_reference_and_cta(): void
    {
        $page = $this->import()['/manutencao-de-sites'];

        $plan = $this->section($page, 'site.plans')['items'][0];
        $this->assertSame(['Basic', 'R$ 350,00', '/mês'], [$plan['name'], $plan['price'], $plan['period']]);
        $this->assertSame('https://wa.me/5500', $plan['href']);

        $faq = $this->section($page, 'site.faq')['items'][0];
        $this->assertSame('Inclui backup?', $faq['question']);
        $this->assertStringContainsString('<a href="/servicos">Serviços</a>', $faq['answer']);

        $steps = $this->section($page, 'site.steps');
        $this->assertSame('Método', $steps['title']);
        $this->assertCount(2, $steps['items']);

        $cta = $this->section($page, 'site.cta');
        $this->assertSame(CopyImporter::FRESHDESK_URL, $cta['secondary_href']);
        $this->assertSame('Clientes: abrir chamado', $cta['secondary_label']);

        $types = array_column($page['sections'], 'type');
        $this->assertSame('site.cases', $types[array_search('site.faq', $types, true) - 1]);
    }

    public function test_service_tree_and_noindex(): void
    {
        $importer = new CopyImporter('https://wa.me/5500');
        $pages = $importer->import(glob($this->dir.'/*.md') ?: []);

        $this->assertTrue($pages['/obrigado']['noindex']);
        $this->assertSame('Mensagem recebida', $pages['/obrigado']['title']);
        $this->assertSame([
            ['label' => 'Web', 'href' => '/desenvolvimento-web', 'children' => [['label' => 'Sites', 'href' => '/desenvolvimento-de-sites']]],
        ], $importer->serviceTree());
    }
}
