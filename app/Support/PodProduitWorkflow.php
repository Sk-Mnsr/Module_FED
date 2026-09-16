<?php

namespace App\Support;

use App\Models\PodProduit;
use Illuminate\Validation\ValidationException;

/**
 * Contrôles de bascule de statut produit.
 */
final class PodProduitWorkflow
{
    /**
     * @throws ValidationException
     */
    public static function assertCanTransition(PodProduit $produit, string $to): void
    {
        $from = $produit->statut;
        if ($from === $to) {
            return;
        }

        $allowed = match ($from) {
            PodProduit::STATUT_BROUILLON => [PodProduit::STATUT_VALIDE, PodProduit::STATUT_BROUILLON],
            PodProduit::STATUT_VALIDE => [
                PodProduit::STATUT_BROUILLON,
                PodProduit::STATUT_PRODUCTION,
                PodProduit::STATUT_VALIDE,
            ],
            PodProduit::STATUT_PRODUCTION => [
                PodProduit::STATUT_VALIDE,
                PodProduit::STATUT_PRODUCTION,
            ],
            default => [$to],
        };

        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages([
                'statut' => "Transition interdite : {$from} → {$to}.",
            ]);
        }

        if ($to === PodProduit::STATUT_VALIDE) {
            self::assertReadyForValide($produit);
        }

        if ($to === PodProduit::STATUT_PRODUCTION) {
            self::assertReadyForProduction($produit);
        }
    }

    /**
     * @throws ValidationException
     */
    public static function assertReadyForValide(PodProduit $produit): void
    {
        $produit->loadMissing(['lignesComptables', 'tranches', 'champs', 'ecrans']);
        $errors = [];

        if (trim((string) $produit->code) === '' || trim((string) $produit->libelle) === '') {
            $errors['statut'] = 'Code et libellé obligatoires pour valider.';
        }

        if ($produit->lignesComptables->isEmpty()) {
            $errors['lignes'] = 'Au moins une ligne de schéma comptable est requise.';
        }

        if (in_array($produit->mode_frais, [PodProduit::MODE_TRANCHE], true) && $produit->tranches->isEmpty()) {
            $errors['tranches'] = 'Des tranches sont requises pour le mode « tranche ».';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @throws ValidationException
     */
    public static function assertReadyForProduction(PodProduit $produit): void
    {
        self::assertReadyForValide($produit);
        $errors = [];

        if (! $produit->actif) {
            $errors['actif'] = 'Le produit doit être actif pour passer en production.';
        }

        if (empty($produit->compte_produit) && empty($produit->compte_taf)) {
            $errors['compte_produit'] = 'Renseignez au moins le compte produit ou le compte TAF.';
        }

        if (empty($produit->initiateur)) {
            $errors['initiateur'] = 'L’initiateur est obligatoire en production.';
        }

        $placeholder = $produit->lignesComptables->first(
            fn ($l) => (bool) preg_match('/X{3,}/i', (string) $l->compte)
        );
        if ($placeholder) {
            $errors['lignes'] = 'Le schéma contient un compte placeholder (ex. 7XXXXXXX).';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public static function assertUsableEnOperation(PodProduit $produit): void
    {
        if (! $produit->actif) {
            throw ValidationException::withMessages([
                'pod_produit_id' => 'Ce produit est inactif.',
            ]);
        }

        if ($produit->statut === PodProduit::STATUT_BROUILLON) {
            throw ValidationException::withMessages([
                'pod_produit_id' => 'Produit encore en brouillon — passez-le en « valide » ou « production ».',
            ]);
        }
    }
}
