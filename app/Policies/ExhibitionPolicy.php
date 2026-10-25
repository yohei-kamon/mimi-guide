<?php

namespace App\Policies;

use App\Models\Exhibition;
use App\Models\User;

class ExhibitionPolicy
{
    /**
     * Determine whether the user can list their museum's exhibitions.
     */
    public function viewAny(User $user): bool
    {
        return $user->isStaff() && $user->museum_id !== null;
    }

    /**
     * Determine whether the user can view the exhibition in the admin screens.
     */
    public function view(User $user, Exhibition $exhibition): bool
    {
        return $this->belongsToUsersMuseum($user, $exhibition);
    }

    /**
     * Determine whether the user can create exhibitions for their museum.
     */
    public function create(User $user): bool
    {
        return $user->isStaff() && $user->museum_id !== null;
    }

    /**
     * Determine whether the user can update the exhibition.
     */
    public function update(User $user, Exhibition $exhibition): bool
    {
        return $this->belongsToUsersMuseum($user, $exhibition);
    }

    /**
     * Determine whether the user can delete the exhibition.
     */
    public function delete(User $user, Exhibition $exhibition): bool
    {
        return $this->belongsToUsersMuseum($user, $exhibition);
    }

    /**
     * Determine whether the user is staff of the museum that holds the exhibition.
     */
    private function belongsToUsersMuseum(User $user, Exhibition $exhibition): bool
    {
        return $user->isStaff()
            && $user->museum_id !== null
            && $user->museum_id === $exhibition->museum_id;
    }
}
