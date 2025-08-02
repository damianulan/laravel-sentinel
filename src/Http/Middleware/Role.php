<?php

namespace Sentinel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Role
{
    /**
     * Check role for authenticated user.
     *
     * @param  mixed  $role
     * @return void
     */
    public function handle(Request $request, Closure $next, $role)
    {
        if (! Auth::check() || ! Auth::user()->hasRole($role)) {
            abort(403);
        }

        return $next($request);
    }
}
