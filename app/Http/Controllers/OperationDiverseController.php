<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class OperationDiverseController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('operations-diverses.piece-comptable');
    }

    public function pieceComptable(): InertiaResponse
    {
        return Inertia::render('OperationsDiverses/PieceComptable');
    }

    public function archivage(): InertiaResponse
    {
        return Inertia::render('OperationsDiverses/Archivage');
    }
}
