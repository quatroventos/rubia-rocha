<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isRoot(), 403);

        $query = CmsAction::query()->latest('id');

        if (filled($request->string('q'))) {
            $term = '%'.$request->string('q').'%';
            $query->where(function ($builder) use ($term) {
                $builder
                    ->where('user_name', 'like', $term)
                    ->orWhere('user_email', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('path', 'like', $term)
                    ->orWhere('ip', 'like', $term)
                    ->orWhere('city', 'like', $term);
            });
        }

        $actions = $query->paginate(50)->through(fn (CmsAction $action) => [
            'id' => $action->id,
            'created_at' => $action->created_at?->toIso8601String(),
            'user_name' => $action->user_name,
            'user_email' => $action->user_email,
            'action' => $action->action,
            'description' => $action->description,
            'method' => $action->method,
            'path' => $action->path,
            'ip' => $action->ip,
            'location' => $action->locationLabel(),
            'status' => $action->status,
        ]);

        return response()->json($actions);
    }
}
