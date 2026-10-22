<?php

namespace App\Policies;

use App\Models\Museum;
use App\Models\User;

class MuseumPolicy
{
    /**
     * Determine whether the user can list all museums.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can register a museum.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the museum, including stopping and resuming it.
     */
    public function update(User $user, Museum $museum): bool
    {
        return $user->isAdmin();
    }
}
