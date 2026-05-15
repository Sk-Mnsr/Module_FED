<?php

namespace App\Http\Controllers\Monetique;

use App\Http\Controllers\Controller;
use App\Models\CoficarteApporteur;
use App\Models\CoficarteCampaign;
use App\Models\CoficarteCard;
use App\Models\CoficarteSale;
use App\Support\CoficarteAgenceAccess;
use App\Support\CoficarteEncaissementCode;
use App\Support\CoficarteEncaissementRows;
use App\Support\CoficarteMovementLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class VenteController extends Controller
{
    public function create(Request $request)
    {
        $user = $request->user();
        if (! CoficarteAgenceAccess::canInitiateCoficarteVente($user)) {
            abort(403);
        }

        $cartes = CoficarteCard::query()
            ->where('status', CoficarteCard::STATUS_EN_STOCK)
            ->with(['agence:id,code,nom']);
        CoficarteAgenceAccess::applyCardScope($cartes, $user);
        $cartes = $cartes
            ->orderBy('numero_carte')
            ->get([
                'id',
                'numero_carte',
                'prix_vente',
                'prix_achat',
                'date_livraison',
                'date_expiration',
                'reference_facture',
                'reference_bon_livraison',
                'possesseur',
                'agence_id',
                'status',
            ]);

        $cartesDisponibles = $cartes->map(fn (CoficarteCard $c) => [
            'id' => $c->id,
            'numero_carte' => $c->numero_carte,
            'prix_vente' => $c->prix_vente,
            'prix_achat' => $c->prix_achat,
            'date_livraison' => $c->date_livraison?->format('Y-m-d'),
            'date_expiration' => $c->date_expiration?->format('Y-m-d'),
            'reference_facture' => $c->reference_facture,
            'reference_bon_livraison' => $c->reference_bon_livraison,
            'possesseur' => $c->possesseur,
            'status' => $c->status,
            'agence_nom' => $c->agence?->nom,
            'agence_code' => $c->agence?->code,
        ]);

        $apporteurs = CoficarteApporteur::query()
            ->where('actif', true)
            ->where(function ($q) use ($user) {
                $q->whereNull('agence_id');
                if ($user->agence_id) {
                    $q->orWhere('agence_id', $user->agence_id);
                }
            })
            ->orderBy('nom')
            ->get(['id', 'nom', 'code']);

        $campagnes = collect();
        if ($user) {
            $campagnes = CoficarteCampaign::query()
                ->activeForDate()
                ->when($user->agence_id, fn ($q) => $q->where(function ($w) use ($user) {
                    $w->whereNull('agence_id')->orWhere('agence_id', $user->agence_id);
                }))
                ->orderBy('nom')
                ->get(['id', 'nom']);
        }

        return Inertia::render('monetique/Ventes/Nouveau', [
            'cartesDisponibles' => $cartesDisponibles,
            'apporteurs' => $apporteurs,
            'campagnes' => $campagnes,
            'apporteurRequis' => $apporteurs->isNotEmpty(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }
        if (! CoficarteAgenceAccess::canInitiateCoficarteVente($user)) {
            abort(403);
        }

        $nbApporteurs = CoficarteApporteur::query()
            ->where('actif', true)
            ->where(function ($q) use ($user) {
                $q->whereNull('agence_id');
                if ($user->agence_id) {
                    $q->orWhere('agence_id', $user->agence_id);
                }
            })
            ->count();

        $apporteurRules = $nbApporteurs > 0
            ? [
                'required',
                'integer',
                Rule::exists('coficarte_apporteurs', 'id')->where(function ($q) use ($user) {
                    $q->where('actif', true)
                        ->where(function ($w) use ($user) {
                            $w->where('agence_id', $user->agence_id)
                                ->orWhereNull('agence_id');
                        });
                }),
            ]
            : ['nullable', 'integer', Rule::exists('coficarte_apporteurs', 'id')];

        $validated = $request->validate([
            'coficarte_card_id' => 'required|exists:coficarte_cards,id',
            'date_vente' => 'required|date',
            'derniers_4' => 'required|string|size:4|regex:/^[0-9]{4}$/',
            'type_acheteur' => 'required|string|max:64',
            'nom_client' => 'required|string|max:255',
            'compte_client_pack' => 'required|in:in_pack,hors_pack',
            'numero_compte_client' => [
                'nullable',
                'string',
                'max:64',
                Rule::requiredIf(fn () => $request->input('compte_client_pack') === 'in_pack'),
            ],
            'telephone_client' => 'required|string|max:64',
            'email_client' => 'nullable|email|max:191',
            'adresse_client' => 'required|string|max:2000',
            'montant_premiere_recharge' => 'required|integer|min:0',
            'coficarte_apporteur_id' => $apporteurRules,
            'coficarte_campaign_id' => 'nullable|exists:coficarte_campaigns,id',
            'kyc_type_piece' => 'required|string|max:64',
            'kyc_numero_piece' => 'required|string|max:128',
            'kyc_date_emission' => 'required|date',
            'fiche_enrolement' => 'required|file|mimes:pdf,jpeg,jpg,png|max:10240',
            'gpt_id' => 'nullable|string|max:128',
        ]);

        $sale = DB::transaction(function () use ($validated, $request, $user) {
            $cardQuery = CoficarteCard::query()->whereKey($validated['coficarte_card_id'])->lockForUpdate();
            CoficarteAgenceAccess::applyCardScope($cardQuery, $user);
            $card = $cardQuery->first();
            if (! $card) {
                throw ValidationException::withMessages([
                    'coficarte_card_id' => ['Carte introuvable ou non disponible dans votre périmètre.'],
                ]);
            }
            if ($card->status !== CoficarteCard::STATUS_EN_STOCK) {
                throw ValidationException::withMessages([
                    'coficarte_card_id' => ['Cette carte n’est plus disponible à la vente.'],
                ]);
            }

            $fichePath = $request->file('fiche_enrolement')->store('coficarte/fiches-enrolement', 'public');

            $numeroCompte = $validated['compte_client_pack'] === CoficarteSale::COMPTE_PACK_IN
                ? ($validated['numero_compte_client'] ?? null)
                : null;

            $sale = CoficarteSale::create([
                'user_id' => $user->id,
                'coficarte_card_id' => $card->id,
                'date_vente' => $validated['date_vente'],
                'derniers_4' => $validated['derniers_4'],
                'type_acheteur' => $validated['type_acheteur'],
                'nom_client' => $validated['nom_client'],
                'numero_compte_client' => $numeroCompte,
                'compte_client_pack' => $validated['compte_client_pack'],
                'telephone_client' => $validated['telephone_client'],
                'email_client' => $validated['email_client'] ?? null,
                'adresse_client' => $validated['adresse_client'],
                'montant_premiere_recharge' => $validated['montant_premiere_recharge'],
                'fiche_enrolement_path' => $fichePath,
                'gpt_id' => filled($validated['gpt_id'] ?? null) ? trim((string) $validated['gpt_id']) : null,
                'locked' => true,
                'payment_status' => CoficarteSale::PAYMENT_EN_ATTENTE,
                'encaissement_code' => CoficarteEncaissementCode::generateForVente(),
                'coficarte_apporteur_id' => $validated['coficarte_apporteur_id'] ?? null,
                'coficarte_campaign_id' => $validated['coficarte_campaign_id'] ?? null,
                'kyc_type_piece' => $validated['kyc_type_piece'],
                'kyc_numero_piece' => $validated['kyc_numero_piece'],
                'kyc_date_emission' => $validated['kyc_date_emission'],
            ]);

            $card->update(['status' => CoficarteCard::STATUS_EN_ATTENTE_ENCAISSEMENT]);

            CoficarteMovementLogger::log($card, 'vente_initiee', [
                'sale_id' => $sale->id,
            ], $user->id);

            return $sale;
        });

        $sale->load(['user:id,name', 'card:id,numero_carte,agence_id,prix_vente', 'card.agence:id,nom']);

        return redirect()
            ->route('monetique.ventes.historique')
            ->with('success', 'Vente saisie — en attente d’encaissement et d’activation à la caisse.')
            ->with('bordereau_cc', [
                'kind' => 'vente',
                'row' => CoficarteEncaissementRows::sale($sale),
            ]);
    }

    public function historique()
    {
        $sales = CoficarteSale::query()
            ->with(['user:id,name', 'card.agence:id,nom', 'card', 'apporteur:id,nom', 'campaign:id,nom'])
            ->whereHas('card', function ($q) {
                CoficarteAgenceAccess::applyCardScope($q, auth()->user());
            })
            ->latest()
            ->paginate(15)
            ->through(function (CoficarteSale $sale) {
                $paiement = match ($sale->payment_status) {
                    CoficarteSale::PAYMENT_EN_ATTENTE => 'En attente caisse',
                    CoficarteSale::PAYMENT_ENCAISSE => 'Encaissé / activé',
                    default => $sale->payment_status,
                };

                return [
                    'id' => $sale->id,
                    'card_id' => $sale->coficarte_card_id,
                    'numero_carte' => $sale->card?->numero_carte ?? '—',
                    'prix_vente' => $sale->card?->prix_vente ?? 0,
                    'vendeur' => $sale->user?->name ?? '—',
                    'date_vente' => $sale->date_vente?->format('d/m/Y'),
                    'acheteur' => $sale->nom_client,
                    'type_acheteur' => $sale->type_acheteur,
                    'apporteur' => $sale->apporteur?->nom ?? '—',
                    'campagne' => $sale->campaign?->nom,
                    'paiement' => $paiement,
                    'statut' => $sale->locked ? 'Verrouillé' : 'Ouvert',
                    'encaissement_code' => $sale->encaissement_code,
                    'bordereau_caisse_url' => $sale->bordereau_caisse_path
                        ? Storage::disk('public')->url($sale->bordereau_caisse_path)
                        : null,
                    'bordereau_cc_payload' => [
                        'kind' => 'vente',
                        'row' => CoficarteEncaissementRows::sale($sale),
                    ],
                ];
            });

        return Inertia::render('monetique/Ventes/Historique', [
            'sales' => $sales,
        ]);
    }
}
