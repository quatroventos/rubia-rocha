<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait RestoresSoftDeletedModels
{
    /**
     * @param  class-string<Model&SoftDeletes>  $modelClass
     */
    protected function scopedQuery(Request $request, string $modelClass): Builder
    {
        return $request->boolean('trashed')
            ? $modelClass::onlyTrashed()
            : $modelClass::query();
    }

    /**
     * @param  class-string<Model&SoftDeletes>  $modelClass
     */
    protected function restoreSoftDeleted(Request $request, string $modelClass, int $id, string $ability = 'update'): JsonResponse
    {
        /** @var Model&SoftDeletes $model */
        $model = $modelClass::onlyTrashed()->findOrFail($id);
        $this->authorize($ability, $model);
        $model->restore();

        return response()->json(['data' => $model->fresh()]);
    }
}
