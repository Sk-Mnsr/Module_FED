<?php

namespace App\Http\Controllers\Monetique;

use App\Http\Controllers\Controller;
use App\Models\Agence;
use App\Models\CoficarteCampaign;
use App\Support\CoficarteAgenceAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CampaignController extends Controller
{
    public function index(Request $request)
    {
        if (! CoficarteAgenceAccess::canViewAll($request->user())) {
            abort(403);
        }

        $campagnes = CoficarteCampaign::query()
            ->with('agence:id,nom,code')
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (CoficarteCampaign $c) => [
                'id' => $c->id,
                'nom' => $c->nom,
                'agence' => $c->agence ? "{$c->agence->nom} ({$c->agence->code})" : 'Toutes agences',
                'objectif_ventes' => $c->objectif_ventes,
                'objectif_montant_recharges' => $c->objectif_montant_recharges,
                'date_debut' => $c->date_debut?->format('d/m/Y'),
                'date_fin' => $c->date_fin?->format('d/m/Y'),
                'active' => $c->active,
            ]);

        return Inertia::render('monetique/Campagnes/Index', [
            'campagnes' => $campagnes,
            'agences' => Agence::query()->orderBy('nom')->get(['id', 'nom', 'code']),
        ]);
    }

    public function store(Request $request)
    {
        if (! CoficarteAgenceAccess::canViewAll($request->user())) {
            abort(403);
        }

        $validated = $request->validate([
            'nom' => 'required|string|max:191',
            'description' => 'nullable|string|max:2000',
            'agence_id' => 'nullable|exists:agences,id',
            'objectif_ventes' => 'required|integer|min:0',
            'objectif_montant_recharges' => 'required|integer|min:0',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
            'active' => 'boolean',
        ]);

        CoficarteCampaign::create([
            'nom' => $validated['nom'],
            'description' => $validated['description'] ?? null,
            'agence_id' => $validated['agence_id'] ?? null,
            'objectif_ventes' => $validated['objectif_ventes'],
            'objectif_montant_recharges' => $validated['objectif_montant_recharges'],
            'date_debut' => $validated['date_debut'],
            'date_fin' => $validated['date_fin'],
            'active' => $validated['active'] ?? true,
        ]);

        return redirect()->back()->with('success', 'Campagne créée.');
    }

    public function update(Request $request, CoficarteCampaign $campagne)
    {
        if (! CoficarteAgenceAccess::canViewAll($request->user())) {
            abort(403);
        }

        $validated = $request->validate([
            'nom' => ['sometimes', 'string', 'max:191'],
            'description' => 'nullable|string|max:2000',
            'agence_id' => 'nullable|exists:agences,id',
            'objectif_ventes' => ['sometimes', 'integer', 'min:0'],
            'objectif_montant_recharges' => ['sometimes', 'integer', 'min:0'],
            'date_debut' => ['sometimes', 'date'],
            'date_fin' => ['sometimes', 'date'],
            'active' => ['sometimes', Rule::in([true, false, 1, 0, '1', '0'])],
        ]);

        $campagne->update($validated);

        return redirect()->back()->with('success', 'Campagne mise à jour.');
    }
}
