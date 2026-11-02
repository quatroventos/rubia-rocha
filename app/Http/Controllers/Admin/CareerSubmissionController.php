<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RestoresSoftDeletedModels;
use App\Http\Controllers\Controller;
use App\Models\CareerSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CareerSubmissionController extends Controller
{
    use RestoresSoftDeletedModels;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CareerSubmission::class);

        $items = $this->scopedQuery($request, CareerSubmission::class)->latest()->get()->map(function (CareerSubmission $item) {
            return [
                ...$item->toArray(),
                'resume_path' => null,
            ];
        });

        return response()->json(['data' => $items]);
    }

    public function show(CareerSubmission $careerSubmission): JsonResponse
    {
        $this->authorize('view', $careerSubmission);

        $data = $careerSubmission->toArray();
        $data['resume_path'] = null;

        return response()->json(['data' => $data]);
    }

    public function download(CareerSubmission $careerSubmission): StreamedResponse
    {
        $this->authorize('view', $careerSubmission);

        return Storage::disk('local')->download($careerSubmission->resume_path, $careerSubmission->resume_name);
    }

    public function destroy(CareerSubmission $careerSubmission): JsonResponse
    {
        $this->authorize('delete', $careerSubmission);
        $careerSubmission->delete();

        return response()->json([], 204);
    }

    public function restore(Request $request, int $careerSubmission): JsonResponse
    {
        return $this->restoreSoftDeleted($request, CareerSubmission::class, $careerSubmission, 'delete');
    }
}
