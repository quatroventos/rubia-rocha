<?php

namespace App\Http\Controllers\Admin;

use App\Cms\TourDefinition;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TourController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        if ($request->session()->has(AuthController::IMPERSONATOR_KEY)) {
            throw ValidationException::withMessages([
                'tour' => 'Não é possível gravar o tour durante a impersonação.',
            ]);
        }

        $data = $request->validate([
            'tour' => ['required', 'string', Rule::in(TourDefinition::IDS)],
            'status' => ['required', 'string', Rule::in(['completed', 'skipped'])],
        ]);

        /** @var User $user */
        $user = $request->user();
        $progress = $user->tour_progress ?? [];

        if ($data['status'] === 'skipped') {
            foreach (TourDefinition::IDS as $tourId) {
                $progress[$tourId] = 'skipped';
            }
        } else {
            $progress[$data['tour']] = 'completed';
        }

        $user->forceFill(['tour_progress' => $progress])->save();

        return response()->json([
            'user' => AuthController::userPayload($request, $user),
        ]);
    }
}
