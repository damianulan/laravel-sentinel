<?php

namespace Sentinel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Permission
{
    /**
     * Check permission for authenticated user.
     *
     * @param  mixed  $permission
     * @param  mixed  $context
     * @return void
     */
    public function handle(Request $request, Closure $next, $permission, $context = null)
    {
        if (! Auth::check() || Auth::user()->cannot($permission, $context)) {
            abort(403);
        }

        return $next($request);
    }
}
