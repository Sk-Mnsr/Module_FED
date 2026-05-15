<?php

namespace App\Http\Controllers\Monetique;

use App\Http\Controllers\Controller;
use App\Models\Agence;
use App\Models\CoficarteCard;
use App\Models\CoficarteSupplyRequest;
use App\Models\CoficarteTransfer;
use App\Models\User;
use App\Support\CoficarteAgenceAccess;
use App\Support\CoficarteBonNumeroGenerator;
use App\Support\CoficarteMovementLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TransfertController extends Controller
{
    public function create(Request $request)
    {
        $user = auth()->user();

        $chefsReceveurs = Agence::query()
            ->whereNotNull('chef_agence_user_id')
            ->where('code', '!=', Agence::CODE_SIEGE)
            ->with(['chefAgence:id,name,email'])
            ->orderBy('nom')
            ->get()
            ->map(fn (Agence $a) => [
                'user_id' => (int) $a->chef_agence_user_id,
                'chef_nom' => $a->chefAgence?->name ?? '—',
                'agence_nom' => $a->nom,
                'agence_code' => $a->code,
            ])
            ->values()
            ->all();

        $supplyRequestPayload = null;
        if ($request->filled('supply_request_id') && CoficarteAgenceAccess::canViewAll($user)) {
            $sr = CoficarteSupplyRequest::query()
                ->whereKey($request->integer('supply_request_id'))
                ->whereIn('status', [
                    CoficarteSupplyRequest::STATUS_EN_ATTENTE,
                    CoficarteSupplyRequest::STATUS_PARTIELLE,
                ])
                ->whereDoesntHave('transfers', function (Builder $q) {
                    $q->where('status', CoficarteTransfer::STATUS_EN_ATTENTE);
                })
                ->first();
            if ($sr !== null) {
                $agenceDem = Agence::query()->whereKey($sr->agence_id)->first();
                if ($agenceDem && $agenceDem->chef_agence_user_id) {
                    $supplyRequestPayload = [
                        'id' => $sr->id,
                        'quantite_demandee' => $sr->quantite_demandee,
                        'quantite_livree' => (int) $sr->quantite_livree,
                        'commentaire' => $sr->commentaire,
                        'chef_receveur_user_id' => (int) $agenceDem->chef_agence_user_id,
                    ];
                }
            }
        }

        $referencesQuery = CoficarteCard::query()
            ->where('status', CoficarteCard::STATUS_EN_STOCK)
            ->whereNotNull('reference_facture')
            ->where('reference_facture', '!=', '');
        static::applyTransfertCartesEligiblesScope($referencesQuery, $user);

        $references = $referencesQuery->clone()
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
            static::applyTransfertCartesEligiblesScope($lotQuery, $user);

            $cartesLot = $lotQuery
                ->orderBy('numero_carte')
                ->get(['id', 'numero_carte', 'reference_facture', 'prix_vente', 'date_expiration'])
                ->map(fn (CoficarteCard $c) => [
                    'id' => $c->id,
                    'numero_carte' => $c->numero_carte,
                    'reference_facture' => $c->reference_facture,
                    'prix_vente' => $c->prix_vente,
                    'expiration' => $c->date_expiration?->format('d/m/Y'),
                    'date_expiration' => $c->date_expiration?->toDateString(),
                ])
                ->all();
        }

        return Inertia::render('monetique/Transferts/Nouveau', [
            'references' => $references,
            'cartesLot' => $cartesLot,
            'referenceCourante' => $referenceCourante !== '' ? $referenceCourante : null,
            'chefsReceveurs' => $chefsReceveurs,
            'supplyRequest' => $supplyRequestPayload,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'receveur_user_id' => [
                'required',
                'integer',
                Rule::exists('agences', 'chef_agence_user_id')->where(fn ($q) => $q->where('code', '!=', Agence::CODE_SIEGE)),
            ],
            'card_ids' => 'required|array|min:1',
            'card_ids.*' => 'integer',
            'prix_par_carte' => 'nullable|array',
            'prix_par_carte.*' => 'integer|min:0',
            'commentaire' => 'nullable|string|max:2000',
            'supply_request_id' => 'nullable|exists:coficarte_supply_requests,id',
            'supply_request_completion' => 'nullable|in:continue,close',
        ]);

        if (! empty($validated['supply_request_id']) && empty($validated['supply_request_completion'])) {
            throw ValidationException::withMessages([
                'supply_request_completion' => ['Indiquez si la demande reste ouverte après réception ou doit être clôturée.'],
            ]);
        }

        if (empty($validated['supply_request_id']) && ! empty($validated['supply_request_completion'])) {
            throw ValidationException::withMessages([
                'supply_request_completion' => ['Ce choix ne s’applique qu’aux transferts liés à une demande d’approvisionnement.'],
            ]);
        }

        $agence = Agence::query()
            ->where('chef_agence_user_id', $validated['receveur_user_id'])
            ->where('code', '!=', Agence::CODE_SIEGE)
            ->with('chefAgence:id,name')
            ->firstOrFail();

        $user = $request->user();
        $cardIds = collect($validated['card_ids'])->map(fn ($id) => (int) $id)->unique()->values()->all();

        if (count($cardIds) !== count($validated['card_ids'])) {
            throw ValidationException::withMessages([
                'card_ids' => ['Les cartes ne doivent pas être indiquées en double.'],
            ]);
        }

        $prixParCarte = $validated['prix_par_carte'] ?? [];
        if ($prixParCarte !== []) {
            if (! CoficarteAgenceAccess::canResponsableMonetique($user)) {
                throw ValidationException::withMessages([
                    'prix_par_carte' => ['Seul le responsable monétique peut ajuster les prix depuis un transfert.'],
                ]);
            }
            foreach (array_keys($prixParCarte) as $key) {
                $kid = (int) $key;
                if (! in_array($kid, $cardIds, true)) {
                    throw ValidationException::withMessages([
                        'prix_par_carte' => ['Un prix a été indiqué pour une carte hors sélection du transfert.'],
                    ]);
                }
            }
        }

        static::assertCartesTransfertAutorisees($cardIds, $user);

        $supplyRequest = null;
        if (! empty($validated['supply_request_id'])) {
            $supplyRequest = CoficarteSupplyRequest::query()->findOrFail($validated['supply_request_id']);
            if (! in_array($supplyRequest->status, [
                CoficarteSupplyRequest::STATUS_EN_ATTENTE,
                CoficarteSupplyRequest::STATUS_PARTIELLE,
            ], true)) {
                throw ValidationException::withMessages([
                    'supply_request_id' => ['Cette demande ne peut plus recevoir de transfert (statut : '.$supplyRequest->status.').'],
                ]);
            }
            $pendingExists = CoficarteTransfer::query()
                ->where('supply_request_id', $supplyRequest->id)
                ->where('status', CoficarteTransfer::STATUS_EN_ATTENTE)
                ->exists();
            if ($pendingExists) {
                throw ValidationException::withMessages([
                    'supply_request_id' => ['Un transfert est déjà en attente de réception pour cette demande.'],
                ]);
            }
            if ((int) $supplyRequest->agence_id !== (int) $agence->id) {
                throw ValidationException::withMessages([
                    'supply_request_id' => ['Le destinataire du transfert doit correspondre à l’agence de la demande.'],
                ]);
            }
        }

        $chef = $agence->chefAgence;
        $receveurLabel = $chef
            ? "{$chef->name} — {$agence->nom} ({$agence->code})"
            : $agence->nom;

        DB::transaction(function () use ($validated, $receveurLabel, $user, $supplyRequest, $cardIds, $prixParCarte) {
            $bon = CoficarteBonNumeroGenerator::next();

            $cards = CoficarteCard::query()
                ->whereIn('id', $cardIds)
                ->orderBy('numero_carte')
                ->get();

            if ($prixParCarte !== [] && CoficarteAgenceAccess::canResponsableMonetique($user)) {
                foreach ($cards as $card) {
                    $key = (string) $card->id;
                    if (! array_key_exists($key, $prixParCarte)) {
                        continue;
                    }
                    $nouveau = (int) $prixParCarte[$key];
                    if ($nouveau === (int) $card->prix_vente) {
                        continue;
                    }
                    $ancien = (int) $card->prix_vente;
                    $card->update(['prix_vente' => $nouveau]);
                    CoficarteMovementLogger::log($card, 'prix_vente_maj', [
                        'ancien' => $ancien,
                        'nouveau' => $nouveau,
                        'contexte' => 'transfert',
                    ], $user->id);
                }
                $cards = CoficarteCard::query()
                    ->whereIn('id', $cardIds)
                    ->orderBy('numero_carte')
                    ->get();
            }

            $numeros = $cards->pluck('numero_carte')->values();
            $debutPlage = $numeros->first();
            $finPlage = $numeros->last();

            $transfer = CoficarteTransfer::create([
                'user_id' => auth()->id(),
                'receveur_user_id' => $validated['receveur_user_id'],
                'receveur' => $receveurLabel,
                'debut_plage' => $debutPlage,
                'fin_plage' => $finPlage,
                'card_ids' => $cardIds,
                'supply_request_id' => $supplyRequest?->id,
                'supply_request_completion' => $supplyRequest !== null ? $validated['supply_request_completion'] : null,
                'bon_numero' => $bon,
                'commentaire' => $validated['commentaire'] ?? null,
                'status' => CoficarteTransfer::STATUS_EN_ATTENTE,
            ]);

            if ($supplyRequest) {
                $supplyRequest->update([
                    'status' => CoficarteSupplyRequest::STATUS_TRANSFERT_EN_COURS,
                ]);
            }

            foreach ($cards as $card) {
                CoficarteMovementLogger::log($card, 'transfert_en_attente', [
                    'transfer_id' => $transfer->id,
                    'bon_numero' => $bon,
                ], $user->id);
            }
        });

        return redirect()
            ->route('monetique.transferts.en-attente')
            ->with('success', 'Transfert enregistré avec bon d’approvisionnement.');
    }

    public function annuler(Request $request, CoficarteTransfer $coficarteTransfer)
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        if ($coficarteTransfer->status !== CoficarteTransfer::STATUS_EN_ATTENTE) {
            return redirect()->back()->with('error', 'Ce transfert ne peut plus être annulé.');
        }

        if (! CoficarteAgenceAccess::canViewAll($user) && (int) $coficarteTransfer->user_id !== (int) $user->id) {
            abort(403);
        }

        $supplyRequestId = $coficarteTransfer->supply_request_id;

        try {
            DB::transaction(function () use ($coficarteTransfer, $supplyRequestId) {
                $t = CoficarteTransfer::query()
                    ->whereKey($coficarteTransfer->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($t->status !== CoficarteTransfer::STATUS_EN_ATTENTE) {
                    throw ValidationException::withMessages([
                        'transfer' => ['Ce transfert ne peut plus être annulé.'],
                    ]);
                }

                $t->update([
                    'status' => CoficarteTransfer::STATUS_ANNULE,
                ]);

                if ($supplyRequestId !== null) {
                    static::syncSupplyRequestStatusAfterTransferMutation((int) $supplyRequestId);
                }
            });
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', collect($e->errors())->flatten()->first() ?? 'Annulation impossible.');
        }

        return redirect()->back()->with('success', 'Transfert annulé.');
    }

    public function validerReception(Request $request, CoficarteTransfer $coficarteTransfer)
    {
        $user = $request->user();
        if (! $user || (int) $coficarteTransfer->receveur_user_id !== (int) $user->id) {
            abort(403);
        }

        if ($coficarteTransfer->status !== CoficarteTransfer::STATUS_EN_ATTENTE) {
            return redirect()->back()->with('error', 'Ce transfert n’est pas en attente de réception.');
        }

        if ($user->agence_id === null) {
            return redirect()->back()->with('error', 'Votre compte doit être rattaché à une agence pour réceptionner des cartes.');
        }

        try {
            DB::transaction(function () use ($coficarteTransfer, $user) {
                $transfer = CoficarteTransfer::query()
                    ->whereKey($coficarteTransfer->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($transfer->status !== CoficarteTransfer::STATUS_EN_ATTENTE) {
                    throw ValidationException::withMessages([
                        'transfer' => ['Statut du transfert modifié entre-temps.'],
                    ]);
                }

                $agenceId = (int) $user->agence_id;
                $cardIds = $transfer->card_ids;
                if (is_array($cardIds) && count($cardIds) > 0) {
                    $cards = CoficarteCard::query()
                        ->whereIn('id', $cardIds)
                        ->where('status', CoficarteCard::STATUS_EN_STOCK)
                        ->lockForUpdate()
                        ->orderBy('numero_carte')
                        ->get();
                    if ($cards->count() !== count(array_unique(array_map('intval', $cardIds)))) {
                        throw ValidationException::withMessages([
                            'transfer' => ['Certaines cartes du transfert ne sont plus disponibles au siège.'],
                        ]);
                    }
                } else {
                    $cards = CoficarteCard::query()
                        ->whereBetween('numero_carte', [$transfer->debut_plage, $transfer->fin_plage])
                        ->where('status', CoficarteCard::STATUS_EN_STOCK)
                        ->lockForUpdate()
                        ->get();
                }

                if ($cards->isEmpty()) {
                    throw ValidationException::withMessages([
                        'transfer' => ['Aucune carte en stock pour ce transfert (vérifiez que le stock siège correspond).'],
                    ]);
                }

                foreach ($cards as $card) {
                    if ($card->agence_id !== null && (int) $card->agence_id !== $agenceId) {
                        throw ValidationException::withMessages([
                            'transfer' => ['La carte '.$card->numero_carte.' n’est pas disponible au siège pour ce transfert.'],
                        ]);
                    }
                }

                $label = CoficarteAgenceAccess::possesseurLabel($agenceId);
                foreach ($cards as $card) {
                    $card->update([
                        'agence_id' => $agenceId,
                        'possesseur' => $label,
                        'assigned_to_user_id' => null,
                    ]);
                    CoficarteMovementLogger::log($card, 'transfert_recu_agence', [
                        'transfer_id' => $transfer->id,
                        'agence_id' => $agenceId,
                    ], $user->id);
                }

                $transfer->update([
                    'status' => CoficarteTransfer::STATUS_VALIDE,
                    'validated_at' => now(),
                ]);

                if ($transfer->supply_request_id !== null) {
                    static::applySupplyRequestProgressAfterReception($transfer, $user, $cards->count());
                }
            });
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', collect($e->errors())->flatten()->first() ?? 'Réception impossible.');
        }

        return redirect()->back()->with('success', 'Réception validée : les cartes sont affectées à votre agence.');
    }

    public function bonPdf(Request $request, CoficarteTransfer $coficarteTransfer)
    {
        $this->authorizeTransferView($coficarteTransfer, $request->user());

        if ($coficarteTransfer->bon_numero === null) {
            abort(404);
        }

        $coficarteTransfer->load(['user', 'receveurUser', 'supplyRequest.agence']);

        $pdf = Pdf::loadView('monetique.bon-transfert', [
            'transfer' => $coficarteTransfer,
        ]);

        $filename = $coficarteTransfer->bon_numero.'.pdf';

        return $pdf->download($filename);
    }

    public function show(Request $request, CoficarteTransfer $coficarteTransfer)
    {
        $this->authorizeTransferView($coficarteTransfer, $request->user());

        $coficarteTransfer->load([
            'user.roles',
            'receveurUser.roles',
            'supplyRequest.agence',
        ]);

        $cardIdsList = $coficarteTransfer->card_ids;
        if (is_array($cardIdsList) && count($cardIdsList) > 0) {
            $cardsQuery = CoficarteCard::query()
                ->whereIn('id', $cardIdsList)
                ->orderBy('numero_carte');
        } else {
            $cardsQuery = CoficarteCard::query()
                ->whereBetween('numero_carte', [$coficarteTransfer->debut_plage, $coficarteTransfer->fin_plage])
                ->orderBy('numero_carte');
        }
        CoficarteAgenceAccess::applyCardScope($cardsQuery, $request->user());
        $cards = $cardsQuery->get();

        $envoyeur = $coficarteTransfer->user;
        $receveur = $coficarteTransfer->receveurUser;

        $supplyRequestPayload = null;
        if ($coficarteTransfer->supplyRequest !== null) {
            $sr = $coficarteTransfer->supplyRequest;
            $supplyRequestPayload = [
                'id' => $sr->id,
                'quantite_demandee' => (int) $sr->quantite_demandee,
                'quantite_livree' => (int) $sr->quantite_livree,
                'agence_nom' => $sr->agence?->nom ?? '—',
            ];
        }

        $completionLabel = match ($coficarteTransfer->supply_request_completion) {
            'close' => 'cloturer_apres_reception',
            'continue' => 'poursuivre_demande',
            default => null,
        };

        return Inertia::render('monetique/Transferts/Show', [
            'back_to' => $request->query('from') === 'historique'
                ? '/monetique/transferts/historique'
                : '/monetique/transferts/en-attente',
            'transfer' => [
                'id' => $coficarteTransfer->id,
                'status' => $coficarteTransfer->status,
                'debut_plage' => $coficarteTransfer->debut_plage,
                'fin_plage' => $coficarteTransfer->fin_plage,
                'bon_numero' => $coficarteTransfer->bon_numero,
                'commentaire' => $coficarteTransfer->commentaire ?? 'RAS',
                'date_transfert' => $coficarteTransfer->created_at?->format('d/m/Y H:i'),
                'nb_cartes' => $cards->count(),
                'supply_request_completion' => $completionLabel,
            ],
            'supply_request' => $supplyRequestPayload,
            'envoyeur' => $this->serializeIntervenant($envoyeur, true),
            'receveur' => $this->serializeIntervenant($receveur, false),
            'cartes' => $cards->map(fn (CoficarteCard $c) => [
                'numero_carte' => $c->numero_carte,
                'reference_facture' => $c->reference_facture,
                'date_livraison' => $c->date_livraison?->format('d/m/Y'),
                'date_expiration' => $c->date_expiration?->format('Y-m-d'),
            ])->values()->all(),
            'receptionniste_nom' => $envoyeur?->name ?? '—',
        ]);
    }

    public function enAttente()
    {
        $transfers = CoficarteTransfer::query()
            ->where('status', CoficarteTransfer::STATUS_EN_ATTENTE);
        CoficarteAgenceAccess::applyTransferScope($transfers, auth()->user());
        $transfers = $transfers
            ->with('user:id,name')
            ->latest()
            ->paginate(15)
            ->through(function (CoficarteTransfer $t) {
                $user = auth()->user();

                return [
                    'id' => $t->id,
                    'initiateur' => $t->user?->name ?? '—',
                    'receptionniste' => $t->receveur,
                    'commentaire' => $t->commentaire ?? 'RAS',
                    'date_transfert' => $t->created_at?->format('d/m/Y H:i:s'),
                    'can_annuler' => $user
                        && (CoficarteAgenceAccess::canViewAll($user)
                            || (int) $t->user_id === (int) $user->id),
                    'can_valider_reception' => $user
                        && $t->receveur_user_id !== null
                        && (int) $t->receveur_user_id === (int) $user->id
                        && $t->status === CoficarteTransfer::STATUS_EN_ATTENTE,
                ];
            });

        return Inertia::render('monetique/Transferts/EnAttente', [
            'transfers' => $transfers,
        ]);
    }

    public function historique()
    {
        $transfers = CoficarteTransfer::query();
        CoficarteAgenceAccess::applyTransferScope($transfers, auth()->user());
        $transfers = $transfers
            ->with('user:id,name')
            ->latest()
            ->paginate(15)
            ->through(function (CoficarteTransfer $t) {
                return [
                    'id' => $t->id,
                    'initiateur' => $t->user?->name ?? '—',
                    'receptionniste' => $t->receveur,
                    'commentaire' => $t->commentaire ?? 'RAS',
                    'date_transfert' => $t->created_at?->format('d/m/Y H:i:s'),
                    'statut' => match ($t->status) {
                        CoficarteTransfer::STATUS_VALIDE => 'validé',
                        CoficarteTransfer::STATUS_REJETE => 'rejeté',
                        CoficarteTransfer::STATUS_ANNULE => 'annulé',
                        CoficarteTransfer::STATUS_EN_ATTENTE => 'en attente',
                        default => $t->status,
                    },
                ];
            });

        return Inertia::render('monetique/Transferts/Historique', [
            'transfers' => $transfers,
        ]);
    }

    /**
     * Après réception validée : met à jour la quantité livrée et le statut de la demande (complet / partiel / clôture).
     */
    private static function applySupplyRequestProgressAfterReception(CoficarteTransfer $transfer, User $user, int $nombreCartesValidees): void
    {
        $sr = CoficarteSupplyRequest::query()
            ->whereKey((int) $transfer->supply_request_id)
            ->lockForUpdate()
            ->first();
        if ($sr === null) {
            return;
        }

        $newLivree = (int) $sr->quantite_livree + $nombreCartesValidees;
        $completion = $transfer->supply_request_completion ?? 'continue';
        $demandee = (int) $sr->quantite_demandee;
        $closeNow = ($completion === 'close') || ($newLivree >= $demandee);

        $sr->quantite_livree = $newLivree;
        if ($closeNow) {
            $sr->status = CoficarteSupplyRequest::STATUS_ACCEPTEE;
            $sr->cloture_partielle = $newLivree < $demandee;
            $sr->traite_par_user_id = $user->id;
            $sr->traite_le = now();
        } else {
            $sr->status = CoficarteSupplyRequest::STATUS_PARTIELLE;
            $sr->cloture_partielle = false;
        }
        $sr->save();
    }

    /**
     * Recalcule le statut de la demande après annulation (ou autre mutation) d’un transfert.
     */
    private static function syncSupplyRequestStatusAfterTransferMutation(int $supplyRequestId): void
    {
        $sr = CoficarteSupplyRequest::query()
            ->whereKey($supplyRequestId)
            ->lockForUpdate()
            ->first();
        if ($sr === null) {
            return;
        }

        if (in_array($sr->status, [
            CoficarteSupplyRequest::STATUS_ACCEPTEE,
            CoficarteSupplyRequest::STATUS_REFUSEE,
            CoficarteSupplyRequest::STATUS_ANNULEE,
        ], true)) {
            return;
        }

        $hasPending = CoficarteTransfer::query()
            ->where('supply_request_id', $supplyRequestId)
            ->where('status', CoficarteTransfer::STATUS_EN_ATTENTE)
            ->exists();

        if ($hasPending) {
            $sr->update(['status' => CoficarteSupplyRequest::STATUS_TRANSFERT_EN_COURS]);

            return;
        }

        $livree = (int) $sr->quantite_livree;
        $demandee = (int) $sr->quantite_demandee;

        if ($livree >= $demandee) {
            $sr->update([
                'status' => CoficarteSupplyRequest::STATUS_ACCEPTEE,
                'cloture_partielle' => false,
            ]);

            return;
        }

        if ($livree > 0) {
            $sr->update([
                'status' => CoficarteSupplyRequest::STATUS_PARTIELLE,
                'cloture_partielle' => false,
            ]);

            return;
        }

        $sr->update([
            'status' => CoficarteSupplyRequest::STATUS_EN_ATTENTE,
            'cloture_partielle' => false,
        ]);
    }

    /**
     * Cartes éligibles à un nouveau transfert : visibles pour l’utilisateur, encore au siège si central,
     * et non couvertes par une plage de transfert en attente.
     */
    private static function applyTransfertCartesEligiblesScope(Builder $query, ?User $user): void
    {
        CoficarteAgenceAccess::applyCardScope($query, $user);

        if (CoficarteAgenceAccess::canViewAll($user)) {
            $query->whereNull('agence_id');
        }

        $cardTable = (new CoficarteCard)->getTable();
        $transferTable = (new CoficarteTransfer)->getTable();

        $query->whereNotExists(function ($sub) use ($cardTable, $transferTable) {
            $sub->select(DB::raw(1))
                ->from($transferTable)
                ->where("{$transferTable}.status", CoficarteTransfer::STATUS_EN_ATTENTE)
                ->whereNull("{$transferTable}.card_ids")
                ->whereRaw("{$cardTable}.numero_carte >= {$transferTable}.debut_plage")
                ->whereRaw("{$cardTable}.numero_carte <= {$transferTable}.fin_plage");
        });

        $reservedIds = CoficarteTransfer::query()
            ->where('status', CoficarteTransfer::STATUS_EN_ATTENTE)
            ->whereNotNull('card_ids')
            ->pluck('card_ids')
            ->flatten()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->filter()
            ->values()
            ->all();

        if ($reservedIds !== []) {
            $query->whereNotIn("{$cardTable}.id", $reservedIds);
        }
    }

    /**
     * @param  list<int>  $cardIds
     */
    private static function assertCartesTransfertAutorisees(array $cardIds, ?User $user): void
    {
        $reserved = static::reservedCardIdsForPendingTransfers();
        $conflict = array_intersect($cardIds, $reserved);
        if ($conflict !== []) {
            throw ValidationException::withMessages([
                'card_ids' => ['Une ou plusieurs cartes sont déjà réservées par un autre transfert en attente.'],
            ]);
        }

        $cardsQuery = CoficarteCard::query()->whereIn('id', $cardIds);
        CoficarteAgenceAccess::applyCardScope($cardsQuery, $user);
        if (CoficarteAgenceAccess::canViewAll($user)) {
            $cardsQuery->whereNull('agence_id');
        }

        $cards = $cardsQuery->get();

        if ($cards->count() !== count($cardIds)) {
            throw ValidationException::withMessages([
                'card_ids' => ['Certaines cartes sont introuvables, hors périmètre, ou déjà affectées à une agence.'],
            ]);
        }

        foreach ($cards as $card) {
            if ($card->status !== CoficarteCard::STATUS_EN_STOCK) {
                throw ValidationException::withMessages([
                    'card_ids' => ["La carte {$card->numero_carte} n’est pas en stock et ne peut pas être transférée."],
                ]);
            }
        }
    }

    /**
     * Identifiants de cartes déjà couverts par un transfert en attente (sélection explicite ou ancienne plage).
     *
     * @return list<int>
     */
    private static function reservedCardIdsForPendingTransfers(): array
    {
        $pending = CoficarteTransfer::query()
            ->where('status', CoficarteTransfer::STATUS_EN_ATTENTE)
            ->get(['debut_plage', 'fin_plage', 'card_ids']);

        $ids = [];
        foreach ($pending as $t) {
            $fromJson = $t->card_ids;
            if (is_array($fromJson) && count($fromJson) > 0) {
                foreach ($fromJson as $id) {
                    $ids[] = (int) $id;
                }
            } else {
                $chunk = CoficarteCard::query()
                    ->whereBetween('numero_carte', [$t->debut_plage, $t->fin_plage])
                    ->pluck('id');
                foreach ($chunk as $id) {
                    $ids[] = (int) $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    private function authorizeTransferView(CoficarteTransfer $transfer, ?User $user): void
    {
        if (! $user) {
            abort(403);
        }

        if (CoficarteAgenceAccess::canViewAll($user)) {
            return;
        }

        $transfer->loadMissing('user');

        if ((int) $transfer->user?->agence_id === (int) $user->agence_id) {
            return;
        }

        if ($transfer->receveur_user_id !== null && (int) $transfer->receveur_user_id === (int) $user->id) {
            return;
        }

        abort(403);
    }

    /**
     * @return array{name: string, email: string|null, badge: string, stats: array{nb_cartes: int, nb_transferts: int}, commentaire?: string}|null
     */
    private function serializeIntervenant(?User $user, bool $isEnvoyeur): ?array
    {
        if (! $user) {
            return null;
        }

        $user->loadMissing('roles');

        if ($user->isSuperAdmin()) {
            $badge = 'Super Admin';
        } elseif ($user->hasRole('chef_agence_ca')) {
            $badge = "Chef d'agence";
        } else {
            $first = $user->roles->first();
            $badge = $first !== null ? (string) ($first->nom ?: $first->slug) : 'Utilisateur';
        }

        if ($isEnvoyeur) {
            $nbCartes = CoficarteCard::query()->where('created_by', $user->id)->count();
            $nbTransferts = CoficarteTransfer::query()->where('user_id', $user->id)->count();
        } else {
            $nbCartes = $user->agence_id !== null
                ? CoficarteCard::query()->where('agence_id', $user->agence_id)->count()
                : 0;
            $nbTransferts = CoficarteTransfer::query()->where('receveur_user_id', $user->id)->count();
        }

        return [
            'name' => (string) $user->name,
            'email' => $user->email,
            'badge' => $badge,
            'stats' => [
                'nb_cartes' => $nbCartes,
                'nb_transferts' => $nbTransferts,
            ],
        ];
    }
}
