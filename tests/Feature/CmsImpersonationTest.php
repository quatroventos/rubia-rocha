<?php

namespace Tests\Feature;

use App\Models\CmsAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_can_impersonate_user_and_leave(): void
    {
        $root = $this->makeAdmin();
        $target = $this->makeEditor([
            'name' => 'Editor CMS',
            'email' => 'editor@example.com',
        ]);

        $this->actingAs($root)
            ->postJson('/gestao/api/users/'.$target->id.'/impersonate')
            ->assertOk()
            ->assertJsonPath('user.id', $target->id)
            ->assertJsonPath('user.impersonating', true)
            ->assertJsonPath('user.impersonator.id', $root->id)
            ->assertJsonPath('user.abilities.users', true)
            ->assertJsonPath('user.abilities.audit', false)
            ->assertJsonPath('user.abilities.form_options', true)
            ->assertJsonPath('user.abilities.impersonate', false);

        $this->assertAuthenticatedAs($target);

        $this->assertDatabaseHas('cms_actions', [
            'user_id' => $root->id,
            'action' => 'impersonate',
        ]);

        $this->postJson('/gestao/api/impersonation/leave')
            ->assertOk()
            ->assertJsonPath('user.id', $root->id)
            ->assertJsonPath('user.impersonating', false);

        $this->assertAuthenticatedAs($root);

        $this->assertTrue(
            CmsAction::query()
                ->where('user_id', $root->id)
                ->where('action', 'impersonate.leave')
                ->exists()
        );
    }

    public function test_user_cannot_impersonate(): void
    {
        $user = $this->makeEditor();
        $other = $this->makeEditor(['email' => 'other@example.com']);

        $this->actingAs($user)
            ->postJson('/gestao/api/users/'.$other->id.'/impersonate')
            ->assertForbidden();
    }

    public function test_root_cannot_impersonate_self(): void
    {
        $root = $this->makeAdmin();

        $this->actingAs($root)
            ->postJson('/gestao/api/users/'.$root->id.'/impersonate')
            ->assertForbidden();
    }

    public function test_me_reports_impersonation_state(): void
    {
        $root = $this->makeAdmin();
        $target = $this->makeEditor(['email' => 'alvo@example.com']);

        $this->actingAs($root)
            ->postJson('/gestao/api/users/'.$target->id.'/impersonate')
            ->assertOk();

        $this->getJson('/gestao/api/me')
            ->assertOk()
            ->assertJsonPath('user.id', $target->id)
            ->assertJsonPath('user.impersonating', true)
            ->assertJsonPath('user.impersonator.email', $root->email);
    }
}
