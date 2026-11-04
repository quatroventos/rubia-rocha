<?php

namespace App\Services;

use App\Models\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Resolve redirects cadastrados no CMS. Só roda quando a URL daria 404,
 * então uma página publicada nunca é sobreposta e as páginas normais não consultam a tabela.
 */
class LegacyRedirects
{
    public function respond(Request $request): ?RedirectResponse
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return null;
        }

        try {
            $match = Redirect::match($request->getPathInfo());
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if ($match === null) {
            return null;
        }

        try {
            Redirect::query()->whereKey($match['id'])->increment('hits', 1, ['last_hit_at' => now()]);
        } catch (Throwable $e) {
            report($e);
        }

        return redirect()->to($this->destination($match['to'], $request), $match['status']);
    }

    private function destination(string $to, Request $request): string
    {
        $query = $request->getQueryString();
        $target = str_starts_with($to, '/') ? url($to) : $to;

        if ($query === null || $query === '' || str_contains($target, '?')) {
            return $target;
        }

        return $target.'?'.$query;
    }
}
