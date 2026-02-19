<?php

namespace Sentinel\Testing\Concerns;

use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Sentinel\Traits\HasRolesAndPermissions;

trait InteractsWithRoles
{
    public function assertHasRole(string $slug, UserContract $user): static
    {
        $this->assertTrue(class_uses_trait(HasRolesAndPermissions::class, $user) && $user->hasRole($slug));

        return $this;
    }
}
