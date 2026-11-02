<?php

namespace App\Http\Controllers;

use App\Models\ContactSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

class RubiaSiteController extends Controller
{
    /**
     * @var array<string, array{0: string, 1: string}>
     */
    private const PAGES = [
        '' => ['Rubia Rocha Fotografia', 'Fotografia de famílias, gestantes, casais, festas e mini weddings em Curitiba.'],
        'quem-sou' => ['Quem sou', 'Rubia Rocha, fotógrafa de momentos reais em Curitiba.'],
        'ensaios' => ['Ensaios fotográficos em Curitiba', 'Ensaios de família, gestante, casal e festa infantil em Curitiba.'],
        'eventos' => ['Fotógrafa mini wedding em Curitiba', 'Mini weddings, bodas e eventos intimistas em Curitiba.'],
        'blog' => ['Blog', 'Histórias fotografadas por Rubia Rocha em Curitiba.'],
        'blog/checklist-mini-wedding-curitiba' => ['Checklist do mini wedding em Curitiba', 'Perguntas para fechar o local do mini wedding em Curitiba.'],
        'contato' => ['Contato', 'Peça um orçamento para ensaio ou evento com a Rubia Rocha.'],
    ];

    public function show(?string $path = null): Response
    {
        $path = trim((string) $path, '/');

        if (! array_key_exists($path, self::PAGES)) {
            abort(404);
        }

        $index = public_path('estudio/index.html');
        abort_unless(is_file($index), 404);

        return response(File::get($index), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (filled($request->input('empresa_site'))) {
            return redirect()->route('rubia.show', ['path' => 'contato'])->with('rubia_status', 'enviado');
        }

        $data = $request->validate([
            'nome' => ['required', 'string', 'max:180'],
            'email' => ['required', 'email', 'max:180'],
            'mensagem' => ['required', 'string', 'max:5000'],
        ]);

        ContactSubmission::query()->create([
            'name' => ContactSubmission::formatName($data['nome']),
            'email' => $data['email'],
            'phone' => 'não informado',
            'destination' => 'Rubia Rocha Fotografia',
            'message' => $data['mensagem'],
            'extra' => [
                'origem' => ['label' => 'Origem', 'value' => 'Site estático'],
            ],
        ]);

        return redirect()->route('rubia.show', ['path' => 'contato'])->with('rubia_status', 'enviado');
    }
}
