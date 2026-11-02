<?php

namespace App\Policies;

use App\Models\User;

class AdminOnlyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isCmsMember();
    }

    public function view(User $user, $model): bool
    {
        return $user->isCmsMember();
    }

    public function create(User $user): bool
    {
        return $user->isCmsMember();
    }

    public function update(User $user, $model): bool
    {
        return $user->isCmsMember();
    }

    public function delete(User $user, $model): bool
    {
        return $user->isCmsMember();
    }
}
