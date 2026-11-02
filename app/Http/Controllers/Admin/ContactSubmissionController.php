<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RestoresSoftDeletedModels;
use App\Http\Controllers\Controller;
use App\Models\ContactSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactSubmissionController extends Controller
{
    use RestoresSoftDeletedModels;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ContactSubmission::class);

        $items = $this->scopedQuery($request, ContactSubmission::class)->latest()->get();

        return response()->json(['data' => $items]);
    }

    public function show(ContactSubmission $contactSubmission): JsonResponse
    {
        $this->authorize('view', $contactSubmission);

        return response()->json(['data' => $contactSubmission]);
    }

    public function destroy(ContactSubmission $contactSubmission): JsonResponse
    {
        $this->authorize('delete', $contactSubmission);
        $contactSubmission->delete();

        return response()->json([], 204);
    }

    public function restore(Request $request, int $contactSubmission): JsonResponse
    {
        return $this->restoreSoftDeleted($request, ContactSubmission::class, $contactSubmission, 'delete');
    }
}
