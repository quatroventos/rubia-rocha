<?php

namespace Tests\Feature;

use App\Jobs\PrerenderSiteJob;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MenuItemSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->putJson('/gestao/api/menu-items/sync', [
            'group' => 'header',
            'items' => [],
        ])->assertStatus(401);
    }

    public function test_sync_creates_updates_nests_and_deletes_items_for_group(): void
    {
        $page = Page::query()->create([
            'slug' => 'quem-somos',
            'template' => 'about',
            'status' => 'published',
            'seo_title' => 'Quem somos',
            'content' => [],
        ]);

        $keep = MenuItem::query()->create([
            'group' => 'header',
            'label' => 'Home',
            'href' => '/',
            'sort' => 1,
            'is_active' => true,
        ]);

        $remove = MenuItem::query()->create([
            'group' => 'header',
            'label' => 'Antigo',
            'href' => '/antigo',
            'sort' => 2,
            'is_active' => true,
        ]);

        $otherGroup = MenuItem::query()->create([
            'group' => 'footer_1',
            'label' => 'Rodapé',
            'href' => '/rodape',
            'sort' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($this->makeEditor())
            ->putJson('/gestao/api/menu-items/sync', [
                'group' => 'header',
                'items' => [
                    [
                        'id' => $keep->id,
                        'label' => 'Início',
                        'href' => '/',
                        'is_active' => true,
                        'depth' => 0,
                    ],
                    [
                        'id' => null,
                        'label' => 'Quem somos',
                        'href' => '/quem-somos',
                        'page_id' => $page->id,
                        'is_active' => true,
                        'depth' => 1,
                    ],
                    [
                        'id' => null,
                        'label' => 'Contato',
                        'href' => '/contato',
                        'is_active' => false,
                        'depth' => 0,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.label', 'Início')
            ->assertJsonPath('data.1.label', 'Quem somos')
            ->assertJsonPath('data.1.parent_id', $keep->id)
            ->assertJsonPath('data.2.label', 'Contato')
            ->assertJsonPath('data.2.is_active', false);

        $this->assertDatabaseMissing('menu_items', ['id' => $remove->id]);
        $this->assertDatabaseHas('menu_items', [
            'id' => $otherGroup->id,
            'label' => 'Rodapé',
            'group' => 'footer_1',
        ]);
        $this->assertDatabaseHas('menu_items', [
            'id' => $keep->id,
            'label' => 'Início',
            'group' => 'header',
            'parent_id' => null,
        ]);
        $this->assertDatabaseHas('menu_items', [
            'label' => 'Quem somos',
            'page_id' => $page->id,
            'parent_id' => $keep->id,
            'group' => 'header',
        ]);
    }

    public function test_removing_an_item_keeps_unchanged_children_under_their_parent(): void
    {
        $parent = MenuItem::query()->create(['group' => 'header', 'label' => 'Web', 'href' => '/web', 'sort' => 1, 'is_active' => true]);
        $child = MenuItem::query()->create(['group' => 'header', 'label' => 'Sites', 'href' => '/sites', 'parent_id' => $parent->id, 'sort' => 1, 'is_active' => true]);
        $removed = MenuItem::query()->create(['group' => 'header', 'label' => 'Contato', 'href' => '/contato', 'sort' => 2, 'is_active' => true]);

        $this->actingAs($this->makeEditor())
            ->putJson('/gestao/api/menu-items/sync', [
                'group' => 'header',
                'items' => [
                    ['id' => $parent->id, 'label' => 'Web', 'href' => '/web', 'depth' => 0],
                    ['id' => $child->id, 'label' => 'Sites', 'href' => '/sites', 'depth' => 1],
                ],
            ])
            ->assertOk();

        $this->assertSame($parent->id, $child->fresh()->parent_id);
        $this->assertDatabaseMissing('menu_items', ['id' => $removed->id, 'deleted_at' => null]);
    }

    public function test_sync_rejects_submenu_without_parent(): void
    {
        $this->actingAs($this->makeEditor())
            ->putJson('/gestao/api/menu-items/sync', [
                'group' => 'header',
                'items' => [
                    [
                        'label' => 'Filho solto',
                        'href' => '/filho',
                        'depth' => 1,
                    ],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_sync_can_clear_a_group(): void
    {
        MenuItem::query()->create([
            'group' => 'footer_2',
            'label' => 'Link',
            'href' => '/link',
            'sort' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($this->makeEditor())
            ->putJson('/gestao/api/menu-items/sync', [
                'group' => 'footer_2',
                'items' => [],
            ])
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertDatabaseMissing('menu_items', ['group' => 'footer_2']);
    }

    public function test_reorder_schedules_the_prerender(): void
    {
        $item = MenuItem::query()->create(['group' => 'header', 'label' => 'Home', 'href' => '/', 'sort' => 1, 'is_active' => true]);
        config(['cms.prerender_on_save' => true]);
        $editor = $this->makeEditor();
        Queue::fake();

        $this->actingAs($editor)
            ->putJson('/gestao/api/menu-items/reorder', ['group' => 'header', 'items' => [['id' => $item->id, 'parent_id' => null, 'sort' => 2]]])
            ->assertOk();

        Queue::assertPushed(PrerenderSiteJob::class, 1);
    }

    public function test_emptying_a_group_schedules_the_prerender(): void
    {
        config(['cms.prerender_on_save' => true]);
        $editor = $this->makeEditor();
        Queue::fake();

        $this->actingAs($editor)
            ->putJson('/gestao/api/menu-items/sync', ['group' => 'footer_1', 'items' => []])
            ->assertOk();

        Queue::assertPushed(PrerenderSiteJob::class, 1);
    }
}
