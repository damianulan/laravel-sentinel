<?php

declare(strict_types=1);

namespace Sentinel\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PermissionMiddleware
{
    /** @param Closure(Request): Response $next */
    public function handle(
        Request $request,
        Closure $next,
        string $permission,
        ?string $contextParameter = null,
    ): Response {
        $user = $request->user();
        $context = null === $contextParameter
            ? null
            : $request->route($contextParameter);

        if ( ! $user instanceof Authorizable || ! $user->can($permission, $context)) {
            abort(403);
        }

        return $next($request);
    }
}
