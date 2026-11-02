<?php

namespace Tests\Feature;

use App\Models\CmsAction;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class CmsAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_logged(): void
    {
        $user = $this->makeAdmin([
            'email' => 'root@example.com',
            'password' => 'password',
        ]);

        $this->postJson('/gestao/api/login', [
            'email' => 'root@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->postJson('/gestao/api/login/two-factor', [
            'code' => app(Google2FA::class)->getCurrentOtp('JBSWY3DPEHPK3PXP'),
        ])->assertOk();

        $this->assertDatabaseHas('cms_actions', [
            'user_id' => $user->id,
            'action' => 'login',
        ]);
    }

    public function test_failed_login_is_logged(): void
    {
        $this->postJson('/gestao/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong',
        ])->assertStatus(422);

        $this->assertDatabaseHas('cms_actions', [
            'action' => 'login.failed',
            'user_email' => 'nobody@example.com',
        ]);
    }

    public function test_page_update_is_logged(): void
    {
        $user = $this->makeEditor();
        Page::query()->create([
            'slug' => 'home',
            'template' => 'home',
            'status' => 'draft',
            'seo_title' => 'Home',
            'content' => [],
        ]);

        $this->actingAs($user)
            ->putJson('/gestao/api/pages/home', [
                'status' => 'published',
                'seo_title' => 'Home publicada',
                'content' => ['hero' => ['subtitle' => 'ok']],
            ])
            ->assertOk();

        $this->assertTrue(
            CmsAction::query()
                ->where('user_id', $user->id)
                ->where('action', 'put')
                ->where('path', 'like', '%/pages/home')
                ->exists()
        );
    }

    public function test_authenticated_get_is_logged(): void
    {
        $user = $this->makeEditor();
        Page::query()->create([
            'slug' => 'home',
            'template' => 'home',
            'status' => 'draft',
            'seo_title' => 'Home',
            'content' => [],
        ]);

        $this->actingAs($user)
            ->getJson('/gestao/api/pages')
            ->assertOk();

        $this->assertTrue(
            CmsAction::query()
                ->where('user_id', $user->id)
                ->where('action', 'get')
                ->where('path', 'like', '%/pages')
                ->where('description', 'like', '%acessou as páginas')
                ->exists()
        );
    }

    public function test_get_audit_is_not_logged(): void
    {
        $root = $this->makeAdmin();

        $this->actingAs($root)
            ->getJson('/gestao/api/audit')
            ->assertOk();

        $this->assertFalse(
            CmsAction::query()
                ->where('user_id', $root->id)
                ->where('path', 'like', '%/audit')
                ->exists()
        );
    }

    public function test_get_me_is_not_logged(): void
    {
        $user = $this->makeEditor();

        $this->actingAs($user)
            ->getJson('/gestao/api/me')
            ->assertOk();

        $this->assertFalse(
            CmsAction::query()
                ->where('user_id', $user->id)
                ->where('path', 'like', '%/me')
                ->exists()
        );
    }

    public function test_user_cannot_view_audit_or_forms(): void
    {
        $user = $this->makeEditor();

        $this->actingAs($user)
            ->getJson('/gestao/api/audit')
            ->assertForbidden();

        $this->actingAs($user)
            ->getJson('/gestao/api/forms')
            ->assertForbidden();
    }

    public function test_root_can_view_audit_with_location_fields(): void
    {
        $root = $this->makeAdmin();

        $this->actingAs($root)
            ->putJson('/gestao/api/profile', [
                'name' => $root->name,
            ])
            ->assertOk();

        $this->actingAs($root)
            ->getJson('/gestao/api/audit')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'created_at',
                        'user_name',
                        'user_email',
                        'action',
                        'description',
                        'method',
                        'path',
                        'ip',
                        'location',
                        'status',
                    ],
                ],
            ]);
    }
}
