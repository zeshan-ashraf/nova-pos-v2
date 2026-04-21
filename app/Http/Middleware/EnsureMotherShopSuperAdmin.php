<?php

namespace App\Http\Middleware;

use App\Support\MotherShopSuperAdmin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMotherShopSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! MotherShopSuperAdmin::allows($request->user())) {
            abort(403, 'You do not have access to the Super Dashboard.');
        }

        return $next($request);
    }
}
