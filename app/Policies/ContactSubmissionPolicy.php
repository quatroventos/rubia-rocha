<?php

namespace App\Policies;

use App\Models\User;

class ContactSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isCmsMember();
    }

    public function view(User $user, $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, $model): bool
    {
        return false;
    }

    public function delete(User $user, $model): bool
    {
        return $user->isCmsMember();
    }
}
