<?php

declare(strict_types=1);

namespace Sentinel\Testing\Concerns;

use Illuminate\Contracts\Auth\Authenticatable as UserContract;

trait InteractsWithRoles
{
    public function assertHasRole(string $slug, UserContract $user): static
    {
        $this->assertTrue(
            method_exists($user, 'hasRole') && call_user_func([$user, 'hasRole'], $slug),
            "Failed asserting that the user has role [{$slug}].",
        );

        return $this;
    }
}
