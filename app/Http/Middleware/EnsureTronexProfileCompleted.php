<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTronexProfileCompleted
{
    /**
     * Redirect users with tronex_migration_pending to the profile completion page.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if (!$user || !$user->tronex_migration_pending) {
            return $next($request);
        }

        // Allow access to the complete profile page and logout
        $routeName = $request->route()?->getName();
        $allowed = [
            'tronex.complete-profile',
            'tronex.complete-profile.store',
            'logout',
        ];

        if (in_array($routeName, $allowed)) {
            return $next($request);
        }

        return redirect()->route('tronex.complete-profile');
    }
}
