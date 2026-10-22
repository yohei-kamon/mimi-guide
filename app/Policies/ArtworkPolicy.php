<?php

namespace App\Policies;

use App\Models\Artwork;
use App\Models\Exhibition;
use App\Models\User;

class ArtworkPolicy
{
    /**
     * Determine whether the user can list the artworks of the exhibition.
     */
    public function viewAny(User $user, Exhibition $exhibition): bool
    {
        return $user->can('update', $exhibition);
    }

    /**
     * Determine whether the user can view the artwork in the admin screens.
     */
    public function view(User $user, Artwork $artwork): bool
    {
        return $this->belongsToUsersMuseum($user, $artwork);
    }

    /**
     * Determine whether the user can add an artwork to the exhibition.
     */
    public function create(User $user, Exhibition $exhibition): bool
    {
        return $user->can('update', $exhibition);
    }

    /**
     * Determine whether the user can update the artwork.
     */
    public function update(User $user, Artwork $artwork): bool
    {
        return $this->belongsToUsersMuseum($user, $artwork);
    }

    /**
     * Determine whether the user can delete the artwork.
     */
    public function delete(User $user, Artwork $artwork): bool
    {
        return $this->belongsToUsersMuseum($user, $artwork);
    }

    /**
     * Determine whether the user is staff of the museum that holds the artwork's exhibition.
     */
    private function belongsToUsersMuseum(User $user, Artwork $artwork): bool
    {
        return $user->isStaff()
            && $user->museum_id !== null
            && $user->museum_id === $artwork->exhibition->museum_id;
    }
}
