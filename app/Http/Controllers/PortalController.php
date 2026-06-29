<?php

namespace App\Http\Controllers;

use App\Support\AppPortal;
use App\Support\ModuleAccess;
use App\Support\NavModuleContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PortalController extends Controller
{
    public function index(): InertiaResponse
    {
        $user = auth()->user();

        return Inertia::render('Portal/Index', [
            'modules' => AppPortal::cardsForUser($user),
            'adminLinks' => AppPortal::adminLinksForUser($user),
        ]);
    }

    public function enter(string $module): RedirectResponse
    {
        $user = auth()->user();

        abort_unless(ModuleAccess::userCanAccess($user, $module), 403, 'Module inaccessible.');

        NavModuleContext::enter($module);

        return redirect(AppPortal::entryUrl($user, $module));
    }
}
