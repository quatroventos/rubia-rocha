<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\PrerenderSiteJob;
use App\Models\MenuItem;
use App\Models\Page;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MenuItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MenuItem::class);

        $locale = $this->locale($request);

        $items = MenuItem::query()
            ->where('locale', $locale)
            ->orderBy('group')
            ->orderBy('sort')
            ->get();

        return response()->json([
            'data' => $items,
            'pages' => Page::query()->published()->forLocale($locale)->orderedByMenu()->get(['id', 'slug', 'locale', 'seo_title', 'template'])
                ->map(fn (Page $page) => [...$page->only(['id', 'slug', 'locale', 'seo_title', 'template']), 'path' => $page->publicPath()]),
            'locale' => $locale,
            'locales' => Locales::publicList(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', MenuItem::class);

        $data = $request->validate([
            'group' => ['required', 'string', Rule::in(['header', 'footer_1', 'footer_2'])],
            'label' => ['required', 'string', 'max:255'],
            'href' => ['required', 'string', 'max:500'],
            'page_id' => ['nullable', 'integer', 'exists:pages,id'],
            'parent_id' => ['nullable', 'integer', 'exists:menu_items,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $locale = $this->locale($request);

        if (! empty($data['parent_id'])) {
            $parent = MenuItem::query()->findOrFail($data['parent_id']);
            abort_if($parent->parent_id !== null, 422, 'Submenu só pode ter um nível.');
            abort_if($parent->group !== $data['group'] || $parent->locale !== $locale, 422, 'O pai precisa ser do mesmo grupo.');
        }

        $sort = (int) MenuItem::query()
            ->where('locale', $locale)
            ->where('group', $data['group'])
            ->where('parent_id', $data['parent_id'] ?? null)
            ->max('sort');

        $item = MenuItem::query()->create([
            'locale' => $locale,
            'group' => $data['group'],
            'label' => $data['label'],
            'href' => $data['href'],
            'page_id' => $data['page_id'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'sort' => $sort + 1,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json(['data' => $item], 201);
    }

    public function update(Request $request, MenuItem $menuItem): JsonResponse
    {
        $this->authorize('update', $menuItem);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'href' => ['required', 'string', 'max:500'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'parent_id' => ['nullable', 'integer', 'exists:menu_items,id'],
            'page_id' => ['nullable', 'integer', 'exists:pages,id'],
        ]);

        if (array_key_exists('parent_id', $data) && $data['parent_id'] !== null) {
            abort_if((int) $data['parent_id'] === $menuItem->id, 422, 'Item não pode ser pai de si mesmo.');
            $parent = MenuItem::query()->findOrFail($data['parent_id']);
            abort_if($parent->parent_id !== null, 422, 'Submenu só pode ter um nível.');
            abort_if($parent->group !== $menuItem->group, 422, 'O pai precisa ser do mesmo grupo.');
            abort_if($menuItem->children()->exists(), 422, 'Remova os filhos antes de aninhar este item.');
        }

        $menuItem->update($data);

        return response()->json(['data' => $menuItem->fresh()]);
    }

    public function destroy(MenuItem $menuItem): JsonResponse
    {
        $this->authorize('delete', $menuItem);

        DB::transaction(function () use ($menuItem): void {
            MenuItem::query()->where('parent_id', $menuItem->id)->delete();
            $menuItem->delete();
        });

        return response()->json(['ok' => true]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MenuItem::class);

        $data = $request->validate([
            'group' => ['required', 'string', Rule::in(['header', 'footer_1', 'footer_2'])],
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:menu_items,id'],
            'items.*.parent_id' => ['nullable', 'integer', 'exists:menu_items,id'],
            'items.*.sort' => ['required', 'integer', 'min:0'],
        ]);

        $locale = $this->locale($request);

        DB::transaction(function () use ($data, $locale): void {
            $ids = collect($data['items'])->pluck('id')->all();
            $owned = MenuItem::query()
                ->where('locale', $locale)
                ->where('group', $data['group'])
                ->whereIn('id', $ids)
                ->pluck('id')
                ->all();

            abort_unless(count($owned) === count($ids), 422, 'Itens inválidos para o grupo.');

            foreach ($data['items'] as $row) {
                $parentId = $row['parent_id'] ?? null;
                if ($parentId !== null) {
                    abort_if((int) $parentId === (int) $row['id'], 422);
                    $parent = collect($data['items'])->firstWhere('id', $parentId);
                    abort_if($parent === null, 422);
                    abort_if(($parent['parent_id'] ?? null) !== null, 422, 'Submenu só pode ter um nível.');
                }

                MenuItem::query()->whereKey($row['id'])->update([
                    'parent_id' => $parentId,
                    'sort' => $row['sort'],
                ]);
            }
        });

        PrerenderSiteJob::schedule();

        return response()->json(['ok' => true]);
    }

    /**
     * Replace an entire menu group from a flat ordered list with depth 0|1.
     * Depth 1 attaches to the nearest preceding depth-0 item.
     */
    public function sync(Request $request): JsonResponse
    {
        $this->authorize('create', MenuItem::class);

        $data = $request->validate([
            'group' => ['required', 'string', Rule::in(['header', 'footer_1', 'footer_2'])],
            'items' => ['present', 'array'],
            'items.*.id' => ['nullable', 'integer', 'exists:menu_items,id'],
            'items.*.label' => ['required', 'string', 'max:255'],
            'items.*.href' => ['required', 'string', 'max:500'],
            'items.*.page_id' => ['nullable', 'integer', 'exists:pages,id'],
            'items.*.is_active' => ['sometimes', 'boolean'],
            'items.*.depth' => ['required', 'integer', Rule::in([0, 1])],
        ]);

        $locale = $this->locale($request);

        $synced = DB::transaction(function () use ($data, $locale) {
            $group = $data['group'];

            MenuItem::query()->where('locale', $locale)->where('group', $group)->update(['parent_id' => null]);

            $existing = MenuItem::query()
                ->where('locale', $locale)
                ->where('group', $group)
                ->get()
                ->keyBy('id');

            $kept = [];
            $lastRootId = null;
            $rootSort = 0;
            $childSort = 0;

            foreach ($data['items'] as $row) {
                $depth = (int) $row['depth'];
                $parentId = null;
                $sort = 0;

                if ($depth === 1) {
                    abort_if($lastRootId === null, 422, 'Submenu precisa de um item pai acima.');
                    $parentId = $lastRootId;
                    $childSort++;
                    $sort = $childSort;
                } else {
                    $rootSort++;
                    $childSort = 0;
                    $sort = $rootSort;
                }

                $payload = [
                    'locale' => $locale,
                    'group' => $group,
                    'label' => $row['label'],
                    'href' => $row['href'],
                    'page_id' => $row['page_id'] ?? null,
                    'is_active' => $row['is_active'] ?? true,
                    'parent_id' => $parentId,
                    'sort' => $sort,
                ];

                $id = $row['id'] ?? null;
                if ($id !== null && $existing->has($id)) {
                    $item = $existing->get($id);
                    $item->update($payload);
                } else {
                    $item = MenuItem::query()->create($payload);
                }

                $kept[] = $item->id;

                if ($depth === 0) {
                    $lastRootId = $item->id;
                }
            }

            $query = MenuItem::query()->where('locale', $locale)->where('group', $group);
            if ($kept !== []) {
                $query->whereNotIn('id', $kept);
            }
            $query->delete();

            return MenuItem::query()
                ->where('locale', $locale)
                ->where('group', $group)
                ->orderBy('sort')
                ->get();
        });

        PrerenderSiteJob::schedule();

        return response()->json(['data' => $synced]);
    }

    private function locale(Request $request): string
    {
        return Locales::normalize($request->input('locale'));
    }
}
