<?php

namespace App\Http\Controllers\Reconciliation;

use App\Http\Controllers\Controller;
use App\Models\Partenaire;
use Inertia\Inertia;
use Inertia\Response;

class ReconciliationController extends Controller
{
    public function index(): Response
    {
        $partenaires = Partenaire::query()
            ->orderBy('nom')
            ->get()
            ->map(fn (Partenaire $p) => [
                'id' => $p->id,
                'identifiant' => $p->identifiant,
                'nom' => $p->nom,
                'icone_url' => $p->icone_url,
            ])
            ->values();

        return Inertia::render('ReconciliationFlexcube/Reconciliation/Index', [
            'partenaires' => $partenaires,
        ]);
    }

    public function show(Partenaire $partenaire): Response
    {
        return Inertia::render('ReconciliationFlexcube/Reconciliation/Show', [
            'partenaire' => [
                'id' => $partenaire->id,
                'identifiant' => $partenaire->identifiant,
                'nom' => $partenaire->nom,
                'icone_url' => $partenaire->icone_url,
            ],
        ]);
    }
}
