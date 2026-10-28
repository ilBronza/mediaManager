<?php

namespace IlBronza\MediaManager\Tests\Fixtures;

use Illuminate\Foundation\Auth\User;

class MenuUser extends User
{
    public function __construct(private array $assignedRoles = [])
    {
        parent::__construct();

        $this->setAttribute('id', 1);
    }

    public function hasAnyRole(array $roles): bool
    {
        return count(array_intersect($this->assignedRoles, $roles)) > 0;
    }

    public function isSuperAdmin(): bool
    {
        return in_array('superadmin', $this->assignedRoles, true);
    }
}
