<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdministrator
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->isActive() && $request->user()?->role?->slug === 'administrator',
            403,
            'Only an administrator can access account security controls.',
        );

        return $next($request);
    }
}
