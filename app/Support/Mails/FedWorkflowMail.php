<?php

namespace App\Support\Mails;

use App\Models\Fed;
use App\Models\User;
use App\Support\AppMail;

final class FedWorkflowMail
{
    public static function submittedToN1(Fed $fed): void
    {
        $fed->loadMissing('requester.department.manager', 'requester.nPlus1');
        $n1 = $fed->requester?->resolveNPlus1();
        if ($n1 === null) {
            return;
        }

        AppMail::notify($n1, 'FED à valider — '.$fed->referenceLabel(), [
            'title' => 'Nouvelle fiche d’engagement à valider',
            'content' => ($fed->requester?->name ?? 'Un demandeur').' a soumis une FED en attente de votre validation N+1.',
            'action_required' => 'Ouvrir la file N+1 et traiter la demande.',
            'details' => self::details($fed),
            'action_url' => url('/feds/n1/'.$fed->id),
            'action_text' => 'Traiter la FED',
        ]);
    }

    public static function notifyRole(Fed $fed, string $roleSlug, string $subject, string $title, string $content, string $url): void
    {
        $users = AppMail::usersWithRole($roleSlug);
        if ($users->isEmpty()) {
            return;
        }

        AppMail::notify($users, $subject, [
            'title' => $title,
            'content' => $content,
            'action_required' => 'Action requise dans votre file de validation.',
            'details' => self::details($fed),
            'action_url' => $url,
            'action_text' => 'Ouvrir',
        ]);
    }

    public static function toRequesterValidated(Fed $fed, string $stepLabel, ?string $comment = null): void
    {
        $requester = self::requester($fed);
        if ($requester === null) {
            return;
        }

        AppMail::validated($requester, 'FED validée ('.$stepLabel.') — '.$fed->referenceLabel(), [
            'title' => 'Votre FED a été validée',
            'content' => 'Étape : '.$stepLabel.'.',
            'success_message' => 'La demande avance dans le circuit de validation.',
            'details' => array_filter([
                ...self::details($fed),
                'Commentaire' => $comment,
            ]),
            'action_url' => url('/feds/'.$fed->id),
            'action_text' => 'Voir la FED',
        ]);
    }

    public static function toRequesterRejected(Fed $fed, string $stepLabel, ?string $comment = null): void
    {
        $requester = self::requester($fed);
        if ($requester === null) {
            return;
        }

        AppMail::rejected($requester, 'FED rejetée ('.$stepLabel.') — '.$fed->referenceLabel(), [
            'title' => 'Votre FED a été rejetée',
            'content' => 'Étape : '.$stepLabel.'.',
            'rejection_reason' => $comment ?: 'Aucun motif détaillé.',
            'details' => self::details($fed),
            'action_url' => url('/feds/'.$fed->id),
            'action_text' => 'Voir la FED',
        ]);
    }

    public static function toRequesterNeedsInfo(Fed $fed, string $stepLabel, ?string $comment = null): void
    {
        $requester = self::requester($fed);
        if ($requester === null) {
            return;
        }

        AppMail::notify($requester, 'Complément demandé ('.$stepLabel.') — '.$fed->referenceLabel(), [
            'title' => 'Complément d’information demandé',
            'content' => 'Étape : '.$stepLabel.'. Merci de compléter puis de resoumettre si nécessaire.',
            'action_required' => $comment ?: 'Compléter la demande.',
            'details' => self::details($fed),
            'action_url' => url('/feds/'.$fed->id.'/edit'),
            'action_text' => 'Compléter la FED',
        ]);
    }

    public static function readyForPurchaseOrder(Fed $fed): void
    {
        self::toRequesterValidated($fed, 'Bon de commande');
        self::notifyRole(
            $fed,
            'responsable_achats',
            'FED prête pour bon de commande — '.$fed->referenceLabel(),
            'FED prête pour bon de commande',
            'La FED a été validée et peut être transformée en bon de commande.',
            url('/bons-de-commande'),
        );
    }

    private static function requester(Fed $fed): ?User
    {
        $fed->loadMissing('requester');

        return $fed->requester;
    }

    /**
     * @return array<string, string>
     */
    private static function details(Fed $fed): array
    {
        return array_filter([
            'Référence' => $fed->referenceLabel(),
            'Demandeur' => $fed->requester?->name ?? $fed->demandeur,
            'Motif' => $fed->motive ? mb_strimwidth((string) $fed->motive, 0, 120, '…') : null,
            'Montant estimé' => $fed->estimated_total !== null
                ? number_format((float) $fed->estimated_total, 0, ',', ' ').' FCFA'
                : null,
            'Priorité' => $fed->priority,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
