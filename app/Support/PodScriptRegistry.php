<?php

namespace App\Support;

use App\Models\PodProduit;
use App\Models\PodProduitScript;
use InvalidArgumentException;

/**
 * Moteurs de scripts whitelistés (pas d’exécution de code arbitraire).
 */
final class PodScriptRegistry
{
    public const MOTEURS = [
        'frais_taux_demande' => [
            'label' => 'Frais = montant demande × taux %',
            'description' => 'Calcule montant_demande × (paramètre taux ou taux_frais produit) / 100.',
        ],
        'taf_sur_frais' => [
            'label' => 'TAF sur frais HT',
            'description' => 'Calcule frais_ht × taux_taf / 100.',
        ],
        'valeur_fixe' => [
            'label' => 'Valeur fixe (paramètre)',
            'description' => 'Retourne le paramètre « valeur ».',
        ],
        'copie_champ' => [
            'label' => 'Copie d’un champ saisi',
            'description' => 'Retourne champs_saisis[paramètre champ].',
        ],
        'montant_plus_frais' => [
            'label' => 'Montant demande + frais HT',
            'description' => 'Somme montant_demande + frais_ht.',
        ],
    ];

    /**
     * @return list<array{key: string, label: string, description: string}>
     */
    public static function options(): array
    {
        $out = [];
        foreach (self::MOTEURS as $key => $meta) {
            $out[] = [
                'key' => $key,
                'label' => $meta['label'],
                'description' => $meta['description'],
            ];
        }

        return $out;
    }

    /**
     * @param  array{
     *   montant_demande?: float|null,
     *   frais_ht?: float|null,
     *   taux_taf?: float|null,
     *   champs_saisis?: array<string, mixed>
     * }  $context
     */
    public static function run(PodProduitScript $script, PodProduit $produit, array $context): mixed
    {
        if (! $script->actif) {
            throw new InvalidArgumentException("Script « {$script->code} » inactif.");
        }

        $moteur = $script->moteur;
        if (! isset(self::MOTEURS[$moteur])) {
            throw new InvalidArgumentException("Moteur de script inconnu : {$moteur}.");
        }

        $params = is_array($script->parametres) ? $script->parametres : [];

        return match ($moteur) {
            'frais_taux_demande' => self::fraisTauxDemande($produit, $context, $params),
            'taf_sur_frais' => self::tafSurFrais($context, $params, $produit),
            'valeur_fixe' => $params['valeur'] ?? null,
            'copie_champ' => self::copieChamp($context, $params),
            'montant_plus_frais' => round(
                (float) ($context['montant_demande'] ?? 0) + (float) ($context['frais_ht'] ?? 0),
                2
            ),
            default => throw new InvalidArgumentException("Moteur non implémenté : {$moteur}."),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $params
     */
    private static function fraisTauxDemande(PodProduit $produit, array $context, array $params): float
    {
        $montant = (float) ($context['montant_demande'] ?? 0);
        $taux = isset($params['taux']) && $params['taux'] !== '' && $params['taux'] !== null
            ? (float) $params['taux']
            : (float) ($produit->taux_frais ?? 0);

        return round($montant * $taux / 100, 2);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $params
     */
    private static function tafSurFrais(array $context, array $params, PodProduit $produit): float
    {
        $frais = (float) ($context['frais_ht'] ?? 0);
        $taux = isset($params['taux']) && $params['taux'] !== '' && $params['taux'] !== null
            ? (float) $params['taux']
            : (float) ($context['taux_taf'] ?? $produit->tauxTafEffectif());

        return round($frais * $taux / 100, 2);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $params
     */
    private static function copieChamp(array $context, array $params): mixed
    {
        $champ = strtoupper(trim((string) ($params['champ'] ?? '')));
        if ($champ === '') {
            throw new InvalidArgumentException('Paramètre « champ » requis pour copie_champ.');
        }
        $saisies = $context['champs_saisis'] ?? [];

        return $saisies[$champ] ?? null;
    }
}
