<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidClientEmail
{
    /**
     * Redirect clients with placeholder or internal emails to the data update form.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if (!$user || !$user->requiresClientEmailUpdate()) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        $allowed = [
            'client-data-updates.client.edit',
            'client-data-updates.client.store',
            'logout',
        ];

        if (in_array($routeName, $allowed)) {
            return $next($request);
        }

        return redirect()->route('client-data-updates.client.edit');
    }
}
