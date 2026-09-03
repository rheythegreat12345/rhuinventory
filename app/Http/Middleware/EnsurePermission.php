<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        abort_unless($request->user()?->isActive(), 403, 'Your account is inactive.');
        abort_unless($request->user()?->hasPermission($permission), 403, 'You do not have permission to perform this action.');

        return $next($request);
    }
}
