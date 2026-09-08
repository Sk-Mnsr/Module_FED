<?php

namespace App\Http\Controllers;

use App\Support\ModuleAccess;
use Inertia\Inertia;
use Inertia\Response;

class ReconciliationFlexcubeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('ReconciliationFlexcube/Index', [
            'canManagePartenaires' => ModuleAccess::canAdministerSystem(auth()->user()),
        ]);
    }
}
