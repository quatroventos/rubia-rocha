<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'tour_progress'])]
#[Hidden(['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
            'tour_progress' => 'array',
        ];
    }

    public const ROLE_ROOT = 'root';

    public const ROLE_USER = 'user';

    /**
     * @return list<string>
     */
    public static function cmsRoles(): array
    {
        return [self::ROLE_ROOT, self::ROLE_USER];
    }

    public function hasCompletedTwoFactor(): bool
    {
        return filled($this->app_authentication_secret);
    }

    public function isRoot(): bool
    {
        return $this->hasRole(self::ROLE_ROOT);
    }

    public function isCmsMember(): bool
    {
        return $this->hasAnyRole(self::cmsRoles());
    }

    public function cmsRole(): string
    {
        return $this->isRoot() ? self::ROLE_ROOT : self::ROLE_USER;
    }

    public function cmsAbilities(): array
    {
        $root = $this->isRoot();
        $member = $this->isCmsMember();

        return [
            'is_admin' => $root,
            'is_root' => $root,
            'users' => $member,
            'settings' => $member,
            'cache' => $member,
            'backup' => $member,
            'tags' => $member,
            'sitemap' => $member,
            'seo' => $member,
            'menu' => $member,
            'certifications' => $member,
            'form_options' => $member,
            'audit' => $root,
            'pages' => $member,
            'cases' => $member,
            'messages' => $member,
            'careers' => $member,
            'messages_delete' => $member,
            'careers_delete' => $member,
            'impersonate' => $root,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email'])
            ->logOnlyDirty();
    }
}
