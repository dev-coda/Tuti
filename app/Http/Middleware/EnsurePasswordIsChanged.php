<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    /**
     * Redirect users who must change their default password before using the app.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if (!$user || !$user->must_change_password || $user->hasRole('admin')) {
            return $next($request);
        }

        if ($user->tronex_migration_pending || $user->requiresClientEmailUpdate()) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        $allowed = [
            'password.forced-change',
            'password.forced-change.store',
            'client-data-updates.client.edit',
            'client-data-updates.client.store',
            'logout',
        ];

        if (in_array($routeName, $allowed)) {
            return $next($request);
        }

        return redirect()->route('password.forced-change');
    }
}
