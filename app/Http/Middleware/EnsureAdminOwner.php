<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $ownerEmail = config('admin.owner_email');
        $userEmail = $request->user()?->email;

        abort_unless(
            is_string($ownerEmail)
                && $ownerEmail !== ''
                && is_string($userEmail)
                && hash_equals(strtolower($ownerEmail), strtolower($userEmail)),
            403
        );

        return $next($request);
    }
}
