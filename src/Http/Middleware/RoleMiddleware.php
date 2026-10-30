<?php

declare(strict_types=1);

namespace Sentinel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RoleMiddleware
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if ( ! is_object($user) || ! method_exists($user, 'hasRole') || ! call_user_func([$user, 'hasRole'], $role)) {
            abort(403);
        }

        return $next($request);
    }
}
