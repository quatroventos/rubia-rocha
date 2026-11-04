<?php

namespace Database\Seeders;

use App\Models\Redirect;
use Illuminate\Database\Seeder;

class RedirectSeeder extends Seeder
{
    /**
     * Páginas do site antigo (fotografacuritiba.com.br, sitemap do Yoast) que mudaram de endereço.
     * Posts, categorias, tags e autores do blog ficam de fora até o novo blog ter endereço definido.
     *
     * @var array<string, string>
     */
    public const LEGACY_PAGES = [
        '/fotografa-em-curitiba' => '/',
        '/sobre-2' => '/quem-sou',
        '/portfolio' => '/ensaios',
        '/retrato-de-familia-curitiba' => '/ensaios/familia',
        '/fotografia-para-formatura-curitiba' => '/ensaios/formatura',
        '/fotografa-de-casamento-curitiba' => '/eventos/mini-wedding',
        '/portfolio/mini-wedding-e-casais-em-curitiba' => '/eventos/mini-wedding',
        '/ensaios/ensaio-de-formatura' => '/ensaios/formatura',
        '/ensaios/ensaio-de-gestante-curitiba' => '/ensaios/gestante',
        '/ensaios/ensaio-de-familia-em-curitiba' => '/ensaios/familia',
        '/ensaios/acompanhamento-bebe-lifestyle-curitiba' => '/ensaios/bebes',
        '/ensaios/ensaio-casal-pre-wedding-curitiba' => '/ensaios/casal',
        '/ensaios/ensaio-feminino-curitiba' => '/ensaios/feminino',
        '/eventos/fotografia-de-festa-infantil-e-aniversarios-em-curitiba' => '/eventos/festa-infantil',
        '/eventos/fotografia-corporativa-curitiba' => '/eventos/corporativo',
        '/eventos/mini-wedding-bodas' => '/eventos/mini-wedding',
    ];

    /**
     * Só cria o que falta: um redirect editado, pausado ou apagado no CMS não volta.
     */
    public function run(): void
    {
        foreach (self::LEGACY_PAGES as $from => $to) {
            $exists = Redirect::withTrashed()->where('from_path', $from)->exists();

            if (! $exists) {
                Redirect::query()->create(['from_path' => $from, 'to_url' => $to, 'status_code' => 301, 'is_active' => true]);
            }
        }
    }
}
