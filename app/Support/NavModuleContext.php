<?php

namespace App\Support;

use Illuminate\Http\Request;

final class NavModuleContext
{
    public const SESSION_KEY = 'nav_module';

    public static function resolve(Request $request): ?string
    {
        if ($request->routeIs('portal')) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        if (! $request->user()) {
            return null;
        }

        $fromPath = AppPortal::moduleKeyFromPath($request->path());
        if ($fromPath !== null && ModuleAccess::userCanAccess($request->user(), $fromPath)) {
            $request->session()->put(self::SESSION_KEY, $fromPath);

            return $fromPath;
        }

        $stored = $request->session()->get(self::SESSION_KEY);
        if (is_string($stored) && ModuleAccess::userCanAccess($request->user(), $stored)) {
            return $stored;
        }

        $request->session()->forget(self::SESSION_KEY);

        return null;
    }

    public static function enter(string $moduleKey): void
    {
        session([self::SESSION_KEY => $moduleKey]);
    }
}
