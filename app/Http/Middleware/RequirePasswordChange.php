<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->password_change_required) {
            return $next($request);
        }

        if ($request->routeIs('user-password.*', 'logout')) {
            return $next($request);
        }

        return redirect()
            ->route('user-password.edit')
            ->with('warning', 'Vous devez changer votre mot de passe pour continuer.');
    }
}
