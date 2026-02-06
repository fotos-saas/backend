<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLocalEnvironment
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!app()->environment('local', 'dev')) {
            abort(403, 'This endpoint is only available in local/dev environment.');
        }

        return $next($request);
    }
}
