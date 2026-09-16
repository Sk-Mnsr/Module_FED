<?php

namespace App\Support;

use App\Models\PodProduit;
use App\Models\PodProduitConstante;
use App\Models\PodProduitScript;
use InvalidArgumentException;

/**
 * Résout les constantes produit selon leur mode d’alimentation.
 */
final class PodConstanteResolver
{
    /**
     * @param  array{
     *   montant_demande?: float|null,
     *   frais_ht?: float|null,
     *   taux_taf?: float|null,
     *   champs_saisis?: array<string, mixed>
     * }  $context
     * @return array<string, array{code: string, libelle: string, type: string, mode: string, valeur: mixed, erreur: string|null}>
     */
    public static function resolveAll(PodProduit $produit, array $context = []): array
    {
        $produit->loadMissing(['constantes', 'scripts', 'lignesComptables']);
        $out = [];

        foreach ($produit->constantes as $constante) {
            $erreur = null;
            $valeur = null;
            try {
                $valeur = self::resolveOne($produit, $constante, $context);
            } catch (InvalidArgumentException $e) {
                $erreur = $e->getMessage();
                if ($constante->obligatoire) {
                    throw $e;
                }
            }

            $out[$constante->code] = [
                'code' => $constante->code,
                'libelle' => $constante->libelle,
                'type' => $constante->type,
                'mode' => $constante->mode,
                'valeur' => $valeur,
                'erreur' => $erreur,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function resolveOne(PodProduit $produit, PodProduitConstante $constante, array $context): mixed
    {
        return match ($constante->mode) {
            PodProduitConstante::MODE_DEFAUT => self::castTyped($constante, $constante->valeur_reference),
            PodProduitConstante::MODE_CHAMP => self::fromChamp($constante, $context),
            PodProduitConstante::MODE_SCHEMA => self::fromSchema($produit, $constante),
            PodProduitConstante::MODE_SCRIPT => self::fromScript($produit, $constante, $context),
            default => throw new InvalidArgumentException("Mode inconnu pour « {$constante->code} »."),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function fromChamp(PodProduitConstante $constante, array $context): mixed
    {
        $champ = strtoupper(trim((string) ($constante->valeur_reference ?? '')));
        if ($champ === '') {
            throw new InvalidArgumentException("Constante « {$constante->code} » : code champ manquant.");
        }
        $saisies = $context['champs_saisis'] ?? [];
        if (! array_key_exists($champ, $saisies) || $saisies[$champ] === null || $saisies[$champ] === '') {
            if ($constante->obligatoire) {
                throw new InvalidArgumentException("Constante « {$constante->libelle} » : champ « {$champ} » non renseigné.");
            }

            return null;
        }

        return self::castTyped($constante, $saisies[$champ]);
    }

    private static function fromSchema(PodProduit $produit, PodProduitConstante $constante): mixed
    {
        $ref = strtolower(trim((string) ($constante->valeur_reference ?? '')));

        return match ($ref) {
            'compte_produit', 'produit' => $produit->compte_produit,
            'compte_taf', 'taf' => $produit->compte_taf,
            'compte_client_mask', 'client' => $produit->compte_client_mask,
            'frais_fixe' => $produit->frais_fixe,
            'taux_frais' => $produit->taux_frais,
            'taux_taf' => $produit->tauxTafEffectif(),
            'premiere_ligne_debit' => $produit->lignesComptables->firstWhere('sens', 'D')?->compte,
            'premiere_ligne_credit' => $produit->lignesComptables->firstWhere('sens', 'C')?->compte,
            default => throw new InvalidArgumentException(
                "Référence schéma invalide pour « {$constante->code} » (ex. compte_produit, compte_taf, frais_fixe)."
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function fromScript(PodProduit $produit, PodProduitConstante $constante, array $context): mixed
    {
        $code = strtoupper(trim((string) ($constante->valeur_reference ?? '')));
        if ($code === '') {
            throw new InvalidArgumentException("Constante « {$constante->code} » : code script manquant.");
        }

        /** @var PodProduitScript|null $script */
        $script = $produit->scripts->first(
            fn (PodProduitScript $s) => strtoupper($s->code) === $code
        );

        if (! $script) {
            throw new InvalidArgumentException("Script « {$code} » introuvable sur le produit.");
        }

        return PodScriptRegistry::run($script, $produit, $context);
    }

    private static function castTyped(PodProduitConstante $constante, mixed $raw): mixed
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        return match ($constante->type) {
            'numerique', 'montant' => is_numeric($raw) ? round((float) $raw, 2) : throw new InvalidArgumentException(
                "« {$constante->libelle} » doit être numérique."
            ),
            default => is_scalar($raw) ? (string) $raw : json_encode($raw),
        };
    }
}
