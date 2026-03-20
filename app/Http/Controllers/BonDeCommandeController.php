<?php

namespace App\Http\Controllers;

use App\Helpers\NumberToWords;
use App\Models\Fed;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BonDeCommandeController extends Controller
{
    public const TAX_RATE = 0.18;

    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);

        $feds = Fed::with(['requester', 'offreChoisie'])
            ->where('status', Fed::STATUS_BON_DE_COMMANDE)
            ->orderByDesc('dga_action_at')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return Inertia::render('achats/BonDeCommandeIndex', [
            'feds' => $feds,
        ]);
    }

    public function show(Request $request, Fed $fed)
    {
        if ($fed->status !== Fed::STATUS_BON_DE_COMMANDE) {
            abort(403, "Cette FED n'est pas en statut bon de commande.");
        }

        $fed->load([
            'items.budgetLine',
            'requester',
            'budgetLines',
            'fournisseurOffres',
            'offreChoisie.fournisseur_relation.banque',
        ]);

        $this->ensureNumeroBonCommande($fed);

        // Dynamically map prices from the chosen offer's supplier to items
        $offreChoisie = $fed->offreChoisie;
        if ($offreChoisie && $offreChoisie->fournisseur_id) {
            $supplierOffers = $fed->fournisseurOffres()
                ->where('fournisseur_id', $offreChoisie->fournisseur_id)
                ->get()
                ->keyBy('fed_item_id');

            foreach ($fed->items as $item) {
                $itemOffer = $supplierOffers->get($item->id);
                if ($itemOffer) {
                    $item->unit_price = $itemOffer->prix_unitaire;
                    $item->total_price = $item->unit_price * $item->quantity;
                }
            }
        }

        $sousTotal = $fed->items->sum('total_price') ?: (float) ($fed->estimated_total ?? 0);
        $taxes = round($sousTotal * self::TAX_RATE, 2);
        $totalTTC = $sousTotal + $taxes;
        $montantEnLettres = NumberToWords::toFrench($totalTTC);

        return Inertia::render('achats/BonDeCommande', [
            'fed' => $fed,
            'sousTotal' => $sousTotal,
            'taxes' => $taxes,
            'totalTTC' => $totalTTC,
            'montantEnLettres' => $montantEnLettres,
            'tauxTVA' => self::TAX_RATE,
        ]);
    }

    private function ensureNumeroBonCommande(Fed $fed): void
    {
        if ($fed->numero_bon_commande) {
            return;
        }

        $year = now()->format('Y');
        $month = now()->format('m');

        $lastNum = Fed::where('numero_bon_commande', 'like', "CFN/{$year}/{$month}/%")
            ->orderByDesc('numero_bon_commande')
            ->value('numero_bon_commande');

        $next = 1;
        if ($lastNum && preg_match('/\/\d+$/', $lastNum, $m)) {
            $next = (int) substr($m[0], 1) + 1;
        }

        $numero = sprintf('CFN/%s/%s/%05d', $year, $month, $next);

        $fed->update([
            'numero_bon_commande' => $numero,
            'date_bon_commande' => $fed->date_bon_commande ?? now(),
        ]);
        $fed->refresh();
    }
}
