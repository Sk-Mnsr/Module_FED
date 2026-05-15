<?php

namespace App\Http\Controllers\Monetique;

use App\Http\Controllers\Controller;
use App\Models\Agence;
use App\Models\CoficarteCard;
use App\Models\CoficarteCardMovement;
use App\Support\CoficarteAgenceAccess;
use App\Support\CoficarteCardNumberGenerator;
use App\Support\CoficarteMovementLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CarteController extends Controller
{
    public function create()
    {
        $user = auth()->user();
        if (! $user || ! CoficarteAgenceAccess::canResponsableMonetique($user)) {
            abort(403);
        }

        return Inertia::render('monetique/Cartes/Ajouter');
    }

    public function store(Request $request)
    {
        $mode = $request->input('mode', 'lot');

        $rules = [
            'mode' => 'required|in:lot,unique',
            'reference_facture' => 'required|string|max:128',
            'prix_vente' => 'required|integer|min:0',
            'prix_achat' => 'required|integer|min:0',
            'date_livraison' => 'required|date',
            'date_expiration' => 'required|date|after_or_equal:date_livraison',
            'facture' => 'required|file|mimes:pdf,jpeg,jpg,png|max:10240',
            'bon_livraison' => 'required|file|mimes:pdf,jpeg,jpg,png|max:10240',
            'reference_bon_livraison' => 'nullable|string|max:128',
        ];

        if ($mode === 'lot') {
            $rules['quantite'] = 'required|integer|min:1|max:10000';
            $rules['premiere_carte'] = 'required|string|max:64';
        } else {
            $rules['numero_carte'] = 'required|string|max:64';
        }

        $user = auth()->user();
        if (! $user || ! CoficarteAgenceAccess::canResponsableMonetique($user)) {
            abort(403);
        }

        $validated = $request->validate($rules);

        [$agenceId, $possesseur] = CoficarteAgenceAccess::resolveAgenceForNewCards($user, null);

        if (! CoficarteAgenceAccess::canViewAll($user) && $agenceId === null) {
            throw ValidationException::withMessages([
                'agence_id' => ['Votre compte n’est pas rattaché à une agence : impossible d’enregistrer des cartes.'],
            ]);
        }

        $path = $request->file('facture')->store('coficarte/factures', 'public');
        $bonLivraisonPath = $request->file('bon_livraison')->store('coficarte/bons-livraison', 'public');

        if ($validated['mode'] === 'unique') {
            $numeros = [CoficarteCardNumberGenerator::normalize($validated['numero_carte'])];
        } else {
            try {
                $numeros = CoficarteCardNumberGenerator::generateFromFirst(
                    $validated['premiere_carte'],
                    (int) $validated['quantite']
                );
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages([
                    'premiere_carte' => [$e->getMessage()],
                ]);
            }
        }

        $existing = CoficarteCard::query()->whereIn('numero_carte', $numeros)->pluck('numero_carte');
        if ($existing->isNotEmpty()) {
            $field = $validated['mode'] === 'unique' ? 'numero_carte' : 'premiere_carte';
            throw ValidationException::withMessages([
                $field => ['Numéro(s) déjà existant(s) : '.$existing->implode(', ')],
            ]);
        }

        DB::transaction(function () use ($numeros, $validated, $path, $bonLivraisonPath, $agenceId, $possesseur) {
            $refBl = isset($validated['reference_bon_livraison']) && trim((string) $validated['reference_bon_livraison']) !== ''
                ? $validated['reference_bon_livraison']
                : null;

            foreach ($numeros as $numero) {
                $card = CoficarteCard::create([
                    'created_by' => auth()->id(),
                    'agence_id' => $agenceId,
                    'numero_carte' => $numero,
                    'reference_facture' => $validated['reference_facture'],
                    'facture_path' => $path,
                    'reference_bon_livraison' => $refBl,
                    'bon_livraison_path' => $bonLivraisonPath,
                    'prix_vente' => (int) $validated['prix_vente'],
                    'prix_achat' => (int) $validated['prix_achat'],
                    'date_livraison' => $validated['date_livraison'],
                    'date_expiration' => $validated['date_expiration'],
                    'status' => CoficarteCard::STATUS_EN_STOCK,
                    'possesseur' => $possesseur,
                ]);
                CoficarteMovementLogger::log($card, 'carte_creee', [
                    'reference_facture' => $validated['reference_facture'],
                ], auth()->id());
            }
        });

        return redirect()
            ->route('monetique.cartes.en-stock')
            ->with('success', count($numeros).' carte(s) enregistrée(s).');
    }

    public function enStock(Request $request)
    {
        $perPage = min(50, max(5, (int) $request->input('per_page', 15)));

        $cards = CoficarteCard::query()
            ->whereIn('status', [CoficarteCard::STATUS_EN_STOCK, CoficarteCard::STATUS_EN_ATTENTE_ENCAISSEMENT]);
        CoficarteAgenceAccess::applyCardScope($cards, auth()->user());
        $cards = $cards
            ->with([
                'creator:id,name',
                'agence:id,nom,code,chef_agence_user_id',
                'agence.chefAgence:id,name',
            ])
            ->orderBy('numero_carte')
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (CoficarteCard $card) {
                $chefNom = $card->agence?->chefAgence?->name;

                $statutKey = match (true) {
                    $card->status === CoficarteCard::STATUS_EN_ATTENTE_ENCAISSEMENT => 'en_attente_encaissement',
                    $card->agence_id === null => 'au_siege',
                    $card->assigned_to_user_id !== null => 'en_vente',
                    default => 'en_agence',
                };

                return [
                    'id' => $card->id,
                    'numero_carte' => $card->numero_carte,
                    'prix_vente' => $card->prix_vente,
                    'reference_facture' => $card->reference_facture,
                    'possesseur' => $chefNom ?? '—',
                    'agence_nom' => $card->agence?->nom,
                    'agence_code' => $card->agence?->code,
                    'statut_key' => $statutKey,
                    'expiration' => $card->date_expiration?->format('d/m/Y'),
                    'date_expiration' => $card->date_expiration?->toDateString(),
                ];
            });

        $user = auth()->user();

        $stockPanorama = ($user && CoficarteAgenceAccess::canViewAll($user))
            ? $this->buildStockPanorama()
            : null;

        return Inertia::render('monetique/Cartes/EnStock', [
            'cards' => $cards,
            'stockPanorama' => $stockPanorama,
        ]);
    }

    /**
     * Synthèse stock pour la monétique centrale (tous périmètres).
     *
     * @return array{totals: array<string, int>, par_agence: array<int, array<string, mixed>>}
     */
    private function buildStockPanorama(): array
    {
        $stAtt = CoficarteCard::STATUS_EN_ATTENTE_ENCAISSEMENT;
        $stStock = CoficarteCard::STATUS_EN_STOCK;
        $table = (new CoficarteCard)->getTable();

        $stats = DB::table($table)
            ->whereIn('status', [$stStock, $stAtt])
            ->select('agence_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as attente_caisse', [$stAtt])
            ->selectRaw('SUM(CASE WHEN status = ? AND agence_id IS NULL AND assigned_to_user_id IS NULL THEN 1 ELSE 0 END) as au_siege', [$stStock])
            ->selectRaw('SUM(CASE WHEN status = ? AND agence_id IS NOT NULL AND assigned_to_user_id IS NOT NULL THEN 1 ELSE 0 END) as en_vente_cc', [$stStock])
            ->selectRaw('SUM(CASE WHEN status = ? AND agence_id IS NOT NULL AND assigned_to_user_id IS NULL THEN 1 ELSE 0 END) as en_agence_stock', [$stStock])
            ->selectRaw('COALESCE(SUM(prix_vente), 0) as valeur_stock_cfa')
            ->groupBy('agence_id')
            ->get();

        $agenceIds = $stats->pluck('agence_id')->filter(fn ($id) => $id !== null)->unique()->values();
        $agences = $agenceIds->isNotEmpty()
            ? Agence::query()->whereIn('id', $agenceIds)->get()->keyBy('id')
            : collect();

        $parAgence = $stats->map(function ($row) use ($agences) {
            $id = $row->agence_id;
            if ($id === null) {
                $nom = 'Siège (stock central)';
                $code = null;
            } else {
                $a = $agences->get((int) $id);
                $nom = $a?->nom ?? '—';
                $code = $a?->code ?? null;
            }

            return [
                'agence_id' => $id !== null ? (int) $id : null,
                'agence_nom' => $nom,
                'agence_code' => $code,
                'total' => (int) $row->total,
                'valeur_stock_cfa' => (int) $row->valeur_stock_cfa,
                'attente_caisse' => (int) $row->attente_caisse,
                'au_siege' => (int) $row->au_siege,
                'en_agence_stock' => (int) $row->en_agence_stock,
                'en_vente_cc' => (int) $row->en_vente_cc,
            ];
        })->sort(function (array $a, array $b) {
            if ($a['agence_id'] === null) {
                return -1;
            }
            if ($b['agence_id'] === null) {
                return 1;
            }

            return strcmp($a['agence_nom'], $b['agence_nom']);
        })->values()->all();

        $sumCol = fn (string $key) => (int) collect($parAgence)->sum(fn (array $r) => $r[$key]);

        return [
            'totals' => [
                'cartes' => $sumCol('total'),
                'valeur_stock_cfa' => $sumCol('valeur_stock_cfa'),
                'attente_caisse' => $sumCol('attente_caisse'),
                'au_siege' => $sumCol('au_siege'),
                'en_agence_stock' => $sumCol('en_agence_stock'),
                'en_vente_cc' => $sumCol('en_vente_cc'),
            ],
            'par_agence' => $parAgence,
        ];
    }

    public function vendus()
    {
        $cards = CoficarteCard::query()
            ->where('status', CoficarteCard::STATUS_VENDU);
        CoficarteAgenceAccess::applyCardScope($cards, auth()->user());
        $cards = $cards
            ->with(['creator:id,name', 'sale.user:id,name', 'agence:id,nom,code'])
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->through(function (CoficarteCard $card) {
                $vendeur = $card->sale?->user?->name ?? $card->creator?->name ?? '—';

                return [
                    'id' => $card->id,
                    'numero_carte' => $card->numero_carte,
                    'date_livraison' => $card->date_livraison?->format('d/m/Y'),
                    'prix_vente' => $card->prix_vente,
                    'vendeur' => $vendeur,
                    'expiration' => $card->date_expiration?->format('d/m/Y'),
                    'date_expiration' => $card->date_expiration?->toDateString(),
                    'expiration_ratio' => 0.85,
                ];
            });

        return Inertia::render('monetique/Cartes/Vendus', [
            'cards' => $cards,
        ]);
    }

    public function modifierPrix(Request $request)
    {
        if (! CoficarteAgenceAccess::canResponsableMonetique(auth()->user())) {
            abort(403);
        }

        $referencesQuery = CoficarteCard::query()
            ->where('status', CoficarteCard::STATUS_EN_STOCK)
            ->whereNotNull('reference_facture')
            ->where('reference_facture', '!=', '');
        CoficarteAgenceAccess::applyCardScope($referencesQuery, auth()->user());

        $references = $referencesQuery
            ->select('reference_facture')
            ->selectRaw('count(*) as cards_count')
            ->groupBy('reference_facture')
            ->orderBy('reference_facture')
            ->get()
            ->map(fn ($r) => [
                'reference_facture' => $r->reference_facture,
                'cards_count' => (int) $r->cards_count,
            ]);

        $referenceCourante = trim($request->query('reference_facture', ''));

        $cartesLot = [];
        if ($referenceCourante !== '') {
            $lotQuery = CoficarteCard::query()
                ->where('status', CoficarteCard::STATUS_EN_STOCK)
                ->where('reference_facture', $referenceCourante);
            CoficarteAgenceAccess::applyCardScope($lotQuery, auth()->user());

            $cartesLot = $lotQuery
                ->orderBy('numero_carte')
                ->get(['id', 'numero_carte', 'prix_vente', 'date_expiration'])
                ->map(fn (CoficarteCard $c) => [
                    'id' => $c->id,
                    'numero_carte' => $c->numero_carte,
                    'prix_vente' => $c->prix_vente,
                    'expiration' => $c->date_expiration?->format('d/m/Y'),
                    'date_expiration' => $c->date_expiration?->toDateString(),
                ])
                ->all();
        }

        return Inertia::render('monetique/Cartes/ModifierPrix', [
            'references' => $references,
            'cartesLot' => $cartesLot,
            'referenceCourante' => $referenceCourante !== '' ? $referenceCourante : null,
        ]);
    }

    public function mouvements(Request $request, CoficarteCard $coficarteCard)
    {
        $ok = CoficarteCard::query()
            ->whereKey($coficarteCard->id);
        CoficarteAgenceAccess::applyCardScope($ok, $request->user());
        if (! $ok->exists()) {
            abort(403);
        }

        $mouvements = $coficarteCard->movements()
            ->with('actor:id,name')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (CoficarteCardMovement $m) => [
                'id' => $m->id,
                'event_type' => $m->event_type,
                'meta' => $m->meta,
                'acteur' => $m->actor?->name ?? '—',
                'date' => $m->created_at?->format('d/m/Y H:i:s'),
            ]);

        return Inertia::render('monetique/Cartes/Mouvements', [
            'card' => [
                'id' => $coficarteCard->id,
                'numero_carte' => $coficarteCard->numero_carte,
                'expiration_plastic' => $coficarteCard->date_expiration?->format('m/y'),
            ],
            'mouvements' => $mouvements,
        ]);
    }

    public function updateBulkPrix(Request $request)
    {
        if (! CoficarteAgenceAccess::canResponsableMonetique(auth()->user())) {
            abort(403);
        }

        $validated = $request->validate([
            'reference_facture' => 'required|string|max:128',
            'prix_vente' => 'required|integer|min:0',
            'card_ids' => 'required|array|min:1',
            'card_ids.*' => 'integer|distinct',
        ]);

        $ids = collect($validated['card_ids'])->unique()->values();

        $q = CoficarteCard::query()
            ->whereIn('id', $ids)
            ->where('reference_facture', $validated['reference_facture'])
            ->where('status', CoficarteCard::STATUS_EN_STOCK);
        CoficarteAgenceAccess::applyCardScope($q, auth()->user());

        $found = $q->pluck('id');

        if ($found->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'card_ids' => 'Certaines cartes ne font pas partie de ce lot, ne sont plus en stock, ou ne sont pas dans votre périmètre.',
            ]);
        }

        $updated = CoficarteCard::query()
            ->whereIn('id', $found)
            ->update(['prix_vente' => $validated['prix_vente']]);

        return redirect()
            ->route('monetique.cartes.en-stock')
            ->with('success', $updated.' carte(s) mise(s) à jour.');
    }
}
