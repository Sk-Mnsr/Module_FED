<?php

namespace App\Support\Mails;

use App\Support\AppMail;
use App\Models\CoficarteCard;
use App\Models\CoficarteStockThreshold;
use App\Models\CoficarteSale;
use App\Models\CoficarteSupplyRequest;
use App\Models\CoficarteTransfer;
use App\Models\User;
use Illuminate\Support\Collection;

final class MonetiqueWorkflowMail
{
    public static function supplyRequestCreated(CoficarteSupplyRequest $request): void
    {
        $request->loadMissing('agence:id,nom,code', 'chef:id,name,email');
        $recipients = AppMail::usersWithAnyRole(['monetique', 'monetique_ops']);
        if ($recipients->isEmpty()) {
            return;
        }

        AppMail::notify($recipients, 'Demande d’approvisionnement agence', [
            'title' => 'Nouvelle demande d’approvisionnement',
            'content' => 'Une agence a demandé des cartes Coficarte.',
            'action_required' => 'Traiter la demande (transfert ou refus).',
            'details' => array_filter([
                'Agence' => $request->agence?->nom,
                'Chef d’agence' => $request->chef?->name,
                'Quantité' => (string) $request->quantite_demandee,
                'Commentaire' => $request->commentaire,
            ]),
            'action_url' => url('/monetique/demandes-approvisionnement'),
            'action_text' => 'Voir les demandes',
        ]);
    }

    public static function supplyRequestRefused(CoficarteSupplyRequest $request, ?string $motif = null): void
    {
        $request->loadMissing('agence:id,nom', 'chef:id,name,email');
        if ($request->chef === null) {
            return;
        }

        AppMail::rejected($request->chef, 'Demande d’approvisionnement refusée', [
            'title' => 'Demande refusée par la monétique centrale',
            'content' => 'Votre demande de cartes a été refusée.',
            'rejection_reason' => $motif ?: 'Aucun motif détaillé.',
            'details' => array_filter([
                'Agence' => $request->agence?->nom,
                'Quantité demandée' => (string) $request->quantite_demandee,
            ]),
            'action_url' => url('/monetique/agence/demandes-approvisionnement'),
            'action_text' => 'Voir mes demandes',
        ]);
    }

    public static function transferCreated(CoficarteTransfer $transfer): void
    {
        $transfer->loadMissing('receveurUser:id,name,email', 'user:id,name');
        if ($transfer->receveurUser === null) {
            return;
        }

        AppMail::notify($transfer->receveurUser, 'Transfert de cartes à réceptionner', [
            'title' => 'Cartes en attente de réception',
            'content' => 'Un transfert Coficarte vous a été adressé.',
            'action_required' => 'Valider la réception des cartes.',
            'details' => array_filter([
                'Émetteur' => $transfer->user?->name,
                'Destinataire' => $transfer->receveur,
                'Bon' => $transfer->bon_numero,
                'Commentaire' => $transfer->commentaire,
            ]),
            'action_url' => url('/monetique/transferts/en-attente'),
            'action_text' => 'Réceptionner',
        ]);
    }

    public static function transferReceived(CoficarteTransfer $transfer): void
    {
        $transfer->loadMissing('user:id,name,email', 'receveurUser:id,name');
        if ($transfer->user === null) {
            return;
        }

        AppMail::validated($transfer->user, 'Transfert de cartes reçu', [
            'title' => 'Réception confirmée',
            'content' => ($transfer->receveurUser?->name ?? 'Le destinataire').' a validé la réception du transfert.',
            'success_message' => 'Le stock agence a été mis à jour.',
            'details' => array_filter([
                'Réceptionnaire' => $transfer->receveurUser?->name ?? $transfer->receveur,
                'Bon' => $transfer->bon_numero,
            ]),
            'action_url' => url('/monetique/transferts/historique'),
            'action_text' => 'Voir l’historique',
        ]);
    }

    public static function saleAwaitingCash(CoficarteSale $sale): void
    {
        $sale->loadMissing('card.agence', 'user:id,name');
        $agenceId = $sale->card?->agence_id;
        $recipients = self::caissiersForAgence($agenceId);
        if ($recipients->isEmpty()) {
            $recipients = AppMail::usersWithAnyRole(['caissier', 'monetique']);
        }
        if ($recipients->isEmpty()) {
            return;
        }

        AppMail::notify($recipients, 'Vente en attente d’encaissement', [
            'title' => 'Encaissement à effectuer',
            'content' => 'Une vente Coficarte est en attente à la caisse.',
            'action_required' => 'Encaisser et activer la carte.',
            'details' => array_filter([
                'Vendeur' => $sale->user?->name,
                'Agence' => $sale->card?->agence?->nom,
                'Code encaissement' => $sale->encaissement_code,
                'Client' => $sale->nom_client ?? null,
            ]),
            'action_url' => url('/monetique/encaissements'),
            'action_text' => 'Ouvrir la caisse',
        ]);
    }

    public static function stockBelowThreshold(string $scopeLabel, int $count, int $min, Collection $recipients): void
    {
        if ($recipients->isEmpty()) {
            return;
        }

        AppMail::alert($recipients, 'Alerte stock Coficarte — '.$scopeLabel, [
            'title' => 'Stock sous le seuil',
            'content' => "Le stock « {$scopeLabel} » est passé sous le seuil configuré.",
            'alert_level' => 'Élevée',
            'action_required' => 'Réapprovisionner ou ajuster le seuil.',
            'details' => [
                'Stock actuel' => (string) $count,
                'Seuil minimum' => (string) $min,
            ],
            'action_url' => url('/monetique/pilotage'),
            'action_text' => 'Voir le pilotage',
        ]);
    }

    /**
     * @return Collection<int, User>
     */
    private static function caissiersForAgence(?int $agenceId): Collection
    {
        $query = User::query()
            ->where('activated', true)
            ->whereHas('roles', fn ($q) => $q->where('slug', 'caissier'));

        if ($agenceId !== null) {
            $query->where('agence_id', $agenceId);
        }

        return $query->get(['id', 'name', 'email']);
    }

    public static function notifyIfStockLow(?int $agenceId = null): void
    {
        if ($agenceId === null) {
            $threshold = CoficarteStockThreshold::query()
                ->where('cible', CoficarteStockThreshold::CIBLE_CENTRAL)
                ->whereNull('agence_id')
                ->first();
            if ($threshold === null || $threshold->min_cards <= 0) {
                return;
            }
            $count = CoficarteCard::query()
                ->whereNull('agence_id')
                ->where('status', CoficarteCard::STATUS_EN_STOCK)
                ->count();
            if ($count >= $threshold->min_cards) {
                return;
            }
            self::stockBelowThreshold(
                'Stock central',
                $count,
                (int) $threshold->min_cards,
                AppMail::usersWithAnyRole(['monetique', 'monetique_ops']),
            );

            return;
        }

        $threshold = CoficarteStockThreshold::query()
            ->where('cible', CoficarteStockThreshold::CIBLE_AGENCE)
            ->where('agence_id', $agenceId)
            ->first();
        if ($threshold === null || $threshold->min_cards <= 0) {
            return;
        }
        $count = CoficarteCard::query()
            ->where('agence_id', $agenceId)
            ->whereIn('status', [
                CoficarteCard::STATUS_EN_STOCK,
                CoficarteCard::STATUS_EN_ATTENTE_ENCAISSEMENT,
            ])
            ->count();
        if ($count >= $threshold->min_cards) {
            return;
        }

        $recipients = User::query()
            ->where('activated', true)
            ->where(function ($q) use ($agenceId) {
                $q->where(function ($inner) use ($agenceId) {
                    $inner->where('agence_id', $agenceId)
                        ->whereHas('roles', fn ($r) => $r->whereIn('slug', ['ca', 'caissier']));
                })->orWhereHas('roles', fn ($r) => $r->whereIn('slug', ['monetique', 'monetique_ops']));
            })
            ->get(['id', 'name', 'email']);

        self::stockBelowThreshold('Agence #'.$agenceId, $count, (int) $threshold->min_cards, $recipients);
    }
}
