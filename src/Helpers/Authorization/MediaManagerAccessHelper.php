<?php

namespace IlBronza\MediaManager\Helpers\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;

class MediaManagerAccessHelper
{
    public function roles(): array
    {
        $roles = config('media-manager.defaultRoles', []);

        if (!is_array($roles))
        {
            return [];
        }

        return array_values(array_filter($roles, fn ($role) => is_string($role) && $role !== ''));
    }

    public function allows(?Authenticatable $user): bool
    {
        if (!$user || !method_exists($user, 'hasAnyRole'))
        {
            return false;
        }

        $roles = $this->roles();

        return count($roles) > 0 && $user->hasAnyRole($roles);
    }

    public function allowsDisk(?Authenticatable $user, string $disk): bool
    {
        return $this->allows($user)
            && in_array($disk, config('media-manager.disks', []), true);
    }
}
