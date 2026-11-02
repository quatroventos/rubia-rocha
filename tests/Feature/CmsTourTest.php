<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsTourTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_empty_tour_progress_for_new_user(): void
    {
        $user = $this->makeEditor();

        $this->actingAs($user)
            ->getJson('/gestao/api/me')
            ->assertOk()
            ->assertJsonPath('user.tour_progress', []);
    }

    public function test_user_can_persist_own_tour_progress(): void
    {
        $user = $this->makeEditor();

        $this->actingAs($user)
            ->patchJson('/gestao/api/tour', [
                'tour' => 'dashboard',
                'status' => 'completed',
            ])
            ->assertOk()
            ->assertJsonPath('user.tour_progress.dashboard', 'completed');

        $this->assertSame(
            ['dashboard' => 'completed'],
            $user->fresh()->tour_progress,
        );
    }

    public function test_user_can_skip_a_tour(): void
    {
        $user = $this->makeAdmin();

        $response = $this->actingAs($user)
            ->patchJson('/gestao/api/tour', [
                'tour' => 'shell',
                'status' => 'skipped',
            ])
            ->assertOk();

        $progress = $response->json('user.tour_progress');

        $this->assertIsArray($progress);
        $this->assertSame('skipped', $progress['shell']);
        $this->assertSame('skipped', $progress['dashboard']);
        $this->assertSame('skipped', $progress['pages']);
        $this->assertCount(count(\App\Cms\TourDefinition::IDS), $progress);
        $this->assertTrue(collect($progress)->every(fn ($status) => $status === 'skipped'));
    }

    public function test_guest_cannot_update_tour_progress(): void
    {
        $this->patchJson('/gestao/api/tour', [
            'tour' => 'dashboard',
            'status' => 'completed',
        ])->assertUnauthorized();
    }

    public function test_invalid_tour_is_rejected(): void
    {
        $user = $this->makeEditor();

        $this->actingAs($user)
            ->patchJson('/gestao/api/tour', [
                'tour' => 'nao-existe',
                'status' => 'completed',
            ])
            ->assertStatus(422);
    }

    public function test_impersonation_does_not_write_tour_progress(): void
    {
        $root = $this->makeAdmin();
        $target = $this->makeEditor([
            'email' => 'alvo-tour@example.com',
        ]);

        $this->actingAs($root)
            ->postJson('/gestao/api/users/'.$target->id.'/impersonate')
            ->assertOk();

        $this->patchJson('/gestao/api/tour', [
            'tour' => 'dashboard',
            'status' => 'completed',
        ])->assertStatus(422);

        $this->assertSame([], $target->fresh()->tour_progress ?? []);
        $this->assertSame([], $root->fresh()->tour_progress ?? []);
    }
}
