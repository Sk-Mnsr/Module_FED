<?php

namespace App\Http\Middleware;

use App\Support\ModuleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModule
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$modules): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        foreach ($modules as $module) {
            if (ModuleAccess::userCanAccess($user, $module)) {
                return $next($request);
            }
        }

        abort(403, 'Accès non autorisé à ce module.');
    }
}
