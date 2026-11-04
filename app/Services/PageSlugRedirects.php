<?php

namespace App\Services;

use App\Models\Redirect;

/**
 * Mantém o endereço antigo vivo quando o slug de uma página muda: cria (ou reativa) um 301
 * do caminho antigo para o novo e aponta para o novo os redirects que levavam ao antigo, evitando cadeias.
 */
class PageSlugRedirects
{
    /**
     * @return array{from: string, to: string, updated: int}|null
     */
    public function record(string $previousPath, string $newPath): ?array
    {
        $from = Redirect::normalizePath($previousPath);
        $to = Redirect::normalizePath($newPath);

        if ($from === $to || $from === '/') {
            return null;
        }

        Redirect::query()->where('from_path', $to)->get()->each->delete();

        $updated = 0;

        Redirect::query()
            ->where('to_url', '!=', '')
            ->get()
            ->filter(fn (Redirect $redirect) => str_starts_with($redirect->to_url, '/') && Redirect::normalizePath($redirect->to_url) === $from)
            ->each(function (Redirect $redirect) use ($to, &$updated): void {
                $suffix = (string) preg_replace('#^[^?\#]*#', '', $redirect->to_url);
                $redirect->update(['to_url' => $to.$suffix]);
                $updated++;
            });

        $redirect = Redirect::withTrashed()->where('from_path', $from)->first() ?? new Redirect(['from_path' => $from]);

        if ($redirect->trashed()) {
            $redirect->restore();
        }

        $redirect->fill(['to_url' => $to, 'status_code' => 301, 'is_active' => true])->save();

        return ['from' => $from, 'to' => $to, 'updated' => $updated];
    }
}
