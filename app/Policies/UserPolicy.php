<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isCmsMember();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isCmsMember();
    }

    public function create(User $user): bool
    {
        return $user->isCmsMember();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isCmsMember();
    }

    public function delete(User $user, User $model): bool
    {
        return $user->isCmsMember() && $user->isNot($model);
    }

    public function impersonate(User $user, User $model): bool
    {
        return $user->isRoot() && $user->isNot($model);
    }

    public function resetTwoFactor(User $user, User $model): bool
    {
        return $user->isRoot() && $user->isNot($model);
    }
}
