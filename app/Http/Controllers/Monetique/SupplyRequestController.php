<?php

namespace App\Http\Controllers\Monetique;

use App\Http\Controllers\Controller;
use App\Models\CoficarteSupplyRequest;
use App\Models\CoficarteTransfer;
use App\Support\CoficarteAgenceAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupplyRequestController extends Controller
{
    public function chefIndex(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('ca') || $user->agence_id === null) {
            abort(403);
        }

        $demandes = CoficarteSupplyRequest::query()
            ->where('agence_id', $user->agence_id)
            ->with(['transfers:id,supply_request_id,bon_numero,status', 'traitePar:id,name'])
            ->latest()
            ->limit(100)
            ->get()
            ->map(function (CoficarteSupplyRequest $d) {
                $pending = $d->transfers->firstWhere('status', CoficarteTransfer::STATUS_EN_ATTENTE);
                $last = $d->transfers->sortByDesc('id')->first();

                return [
                    'id' => $d->id,
                    'quantite_demandee' => $d->quantite_demandee,
                    'quantite_livree' => (int) $d->quantite_livree,
                    'cloture_partielle' => (bool) $d->cloture_partielle,
                    'commentaire' => $d->commentaire,
                    'status' => $d->status,
                    'reponse_monetique' => $d->reponse_monetique,
                    'traite_le' => $d->traite_le?->format('d/m/Y H:i'),
                    'bon_numero' => $pending?->bon_numero ?? $last?->bon_numero,
                    'transfer_statut' => $pending?->status ?? $last?->status,
                    'pending_transfer_id' => $pending?->id,
                ];
            });

        return Inertia::render('monetique/Agence/DemandesApprovisionnement', [
            'demandes' => $demandes,
        ]);
    }

    public function chefStore(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('ca') || $user->agence_id === null) {
            abort(403);
        }

        $validated = $request->validate([
            'quantite_demandee' => 'required|integer|min:1|max:100000',
            'commentaire' => 'nullable|string|max:2000',
        ]);

        CoficarteSupplyRequest::create([
            'agence_id' => $user->agence_id,
            'chef_user_id' => $user->id,
            'quantite_demandee' => $validated['quantite_demandee'],
            'commentaire' => $validated['commentaire'] ?? null,
            'status' => CoficarteSupplyRequest::STATUS_EN_ATTENTE,
        ]);

        return redirect()
            ->route('monetique.agence.demandes-approvisionnement')
            ->with('success', 'Demande envoyée à la monétique centrale.');
    }

    public function chefAnnuler(Request $request, CoficarteSupplyRequest $supplyRequest)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('ca') || (int) $supplyRequest->agence_id !== (int) $user->agence_id) {
            abort(403);
        }

        if ($supplyRequest->status !== CoficarteSupplyRequest::STATUS_EN_ATTENTE) {
            return redirect()->back()->with('error', 'Seules les demandes en attente peuvent être annulées.');
        }

        $supplyRequest->update(['status' => CoficarteSupplyRequest::STATUS_ANNULEE]);

        return redirect()->back()->with('success', 'Demande annulée.');
    }

    public function monetiqueIndex(Request $request)
    {
        if (! CoficarteAgenceAccess::canViewAll($request->user())) {
            abort(403);
        }

        $demandes = CoficarteSupplyRequest::query()
            ->with(['agence:id,nom,code', 'chef:id,name', 'transfers:id,supply_request_id,bon_numero,status'])
            ->orderByRaw("CASE
                WHEN status = 'en_attente' THEN 0
                WHEN status = 'partielle' THEN 1
                WHEN status = 'transfert_en_cours' THEN 2
                ELSE 3
            END")
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(function (CoficarteSupplyRequest $d) {
                $pending = $d->transfers->firstWhere('status', CoficarteTransfer::STATUS_EN_ATTENTE);
                $last = $d->transfers->sortByDesc('id')->first();
                $canCreate = in_array($d->status, [
                    CoficarteSupplyRequest::STATUS_EN_ATTENTE,
                    CoficarteSupplyRequest::STATUS_PARTIELLE,
                ], true) && $pending === null;

                return [
                    'id' => $d->id,
                    'agence_nom' => $d->agence?->nom ?? '—',
                    'agence_code' => $d->agence?->code ?? '',
                    'chef_nom' => $d->chef?->name ?? '—',
                    'quantite_demandee' => $d->quantite_demandee,
                    'quantite_livree' => (int) $d->quantite_livree,
                    'cloture_partielle' => (bool) $d->cloture_partielle,
                    'commentaire' => $d->commentaire,
                    'status' => $d->status,
                    'reponse_monetique' => $d->reponse_monetique,
                    'created_at' => $d->created_at?->format('d/m/Y H:i'),
                    'transfer_id' => $pending?->id ?? $last?->id,
                    'bon_numero' => $pending?->bon_numero ?? $last?->bon_numero,
                    'transfer_statut' => $pending?->status ?? $last?->status,
                    'can_create_transfer' => $canCreate,
                ];
            });

        return Inertia::render('monetique/DemandesApprovisionnement/Index', [
            'demandes' => $demandes,
        ]);
    }

    public function monetiqueRefuser(Request $request, CoficarteSupplyRequest $supplyRequest)
    {
        if (! CoficarteAgenceAccess::canViewAll($request->user())) {
            abort(403);
        }

        if ($supplyRequest->status !== CoficarteSupplyRequest::STATUS_EN_ATTENTE) {
            return redirect()->back()->with('error', 'Cette demande n’est plus modifiable.');
        }

        $validated = $request->validate([
            'reponse_monetique' => 'required|string|max:2000',
        ]);

        $supplyRequest->update([
            'status' => CoficarteSupplyRequest::STATUS_REFUSEE,
            'reponse_monetique' => $validated['reponse_monetique'],
            'traite_par_user_id' => $request->user()->id,
            'traite_le' => now(),
        ]);

        return redirect()->back()->with('success', 'Demande refusée.');
    }
}
