<?php

namespace App\Http\Middleware;

use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Sets the current tenant from the signed-in user, before route model binding runs. */
class SetCurrentTenant
{
    public function __construct(private CurrentTenant $current)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->current->set($request->user()?->tenant_id);

        return $next($request);
    }
}
