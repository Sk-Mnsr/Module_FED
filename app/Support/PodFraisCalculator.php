<?php

namespace App\Support;

use App\Models\PodProduit;
use App\Models\PodTrancheFrais;
use InvalidArgumentException;

/**
 * Calcule frais HT, TAF et génère les écritures à partir du schéma produit.
 */
final class PodFraisCalculator
{
    /**
     * @param  array{
     *   compte_client: string,
     *   montant_demande?: float|null,
     *   frais_saisi?: float|null
     * }  $input
     * @return array{
     *   frais_ht: float,
     *   taux_taf: float,
     *   montant_taf: float,
     *   total_client: float,
     *   devise: string,
     *   tranche: array<string, mixed>|null,
     *   mode_frais: string,
     *   message: string|null,
     *   lignes: list<array{
     *     sens: string,
     *     compte: string,
     *     libelle_ecriture: string|null,
     *     nature_compte: string|null,
     *     type_montant: string|null,
     *     montant: float
     *   }>,
     *   equilibre: bool,
     *   total_debit: float,
     *   total_credit: float
     * }
     */
    public static function compute(PodProduit $produit, array $input): array
    {
        $produit->loadMissing(['tranches', 'lignesComptables']);

        $compteClient = trim((string) ($input['compte_client'] ?? ''));
        if ($compteClient === '') {
            throw new InvalidArgumentException('Le compte client est obligatoire.');
        }

        $montantDemande = isset($input['montant_demande']) && $input['montant_demande'] !== null && $input['montant_demande'] !== ''
            ? (float) $input['montant_demande']
            : null;
        $fraisSaisi = isset($input['frais_saisi']) && $input['frais_saisi'] !== null && $input['frais_saisi'] !== ''
            ? (float) $input['frais_saisi']
            : null;

        $trancheUsed = null;
        $message = null;
        $mode = $produit->mode_frais;

        if ($mode === PodProduit::MODE_GRATUIT) {
            $fraisHt = 0.0;
        } elseif ($mode === PodProduit::MODE_FIXE) {
            $fraisHt = (float) ($produit->frais_fixe ?? 0);
        } elseif ($mode === PodProduit::MODE_TRANCHE) {
            $fraisHt = self::fraisFromTranche($produit, $montantDemande, $trancheUsed);
        } elseif ($mode === PodProduit::MODE_TAUX) {
            $fraisHt = self::fraisFromTaux($produit, $montantDemande);
        } elseif ($mode === PodProduit::MODE_MIXTE) {
            $fraisHt = self::fraisMixte($produit, $montantDemande);
        } elseif ($mode === PodProduit::MODE_MANUEL) {
            $fraisHt = self::fraisManuel($produit, $fraisSaisi, $message);
        } else {
            $fraisHt = (float) ($produit->frais_fixe ?? 0);
        }

        $fraisHt = self::applyMinMax($produit, $fraisHt);
        $fraisHt = round($fraisHt, 2);

        $tauxTaf = $produit->tauxTafEffectif();
        $montantTaf = round($fraisHt * ($tauxTaf / 100), 2);
        $totalClient = round($fraisHt + $montantTaf, 2);

        $lignes = self::buildLignes($produit, $compteClient, $fraisHt, $montantTaf, $totalClient);

        $totalDebit = 0.0;
        $totalCredit = 0.0;
        foreach ($lignes as $ligne) {
            if ($ligne['sens'] === 'D') {
                $totalDebit += $ligne['montant'];
            } else {
                $totalCredit += $ligne['montant'];
            }
        }
        $totalDebit = round($totalDebit, 2);
        $totalCredit = round($totalCredit, 2);

        return [
            'frais_ht' => $fraisHt,
            'taux_taf' => $tauxTaf,
            'montant_taf' => $montantTaf,
            'total_client' => $totalClient,
            'devise' => $produit->devise ?: 'XOF',
            'tranche' => $trancheUsed,
            'mode_frais' => $mode,
            'message' => $message,
            'lignes' => $lignes,
            'equilibre' => abs($totalDebit - $totalCredit) < 0.01,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
        ];
    }

    private static function fraisFromTranche(PodProduit $produit, ?float $montantDemande, ?array &$trancheUsed): float
    {
        if ($montantDemande === null || $montantDemande < 0) {
            throw new InvalidArgumentException('Le montant de la demande est obligatoire pour ce produit (tranches).');
        }

        /** @var PodTrancheFrais|null $match */
        $match = null;
        foreach ($produit->tranches as $tranche) {
            $min = (float) $tranche->montant_min;
            $max = $tranche->montant_max !== null ? (float) $tranche->montant_max : null;
            if ($montantDemande >= $min && ($max === null || $montantDemande <= $max)) {
                $match = $tranche;
                break;
            }
        }

        if ($match === null) {
            throw new InvalidArgumentException('Aucune tranche de frais ne correspond au montant saisi.');
        }

        $trancheUsed = [
            'libelle' => $match->libelle,
            'montant_min' => $match->montant_min,
            'montant_max' => $match->montant_max,
            'frais_fixe' => $match->frais_fixe,
            'taux' => $match->taux,
        ];

        if ($match->frais_fixe !== null) {
            return (float) $match->frais_fixe;
        }

        if ($match->taux !== null) {
            $frais = $montantDemande * ((float) $match->taux / 100);
            if ($match->frais_min !== null) {
                $frais = max($frais, (float) $match->frais_min);
            }
            if ($match->frais_max !== null) {
                $frais = min($frais, (float) $match->frais_max);
            }

            return $frais;
        }

        throw new InvalidArgumentException('La tranche trouvée n’a ni frais fixe ni taux.');
    }

    private static function fraisFromTaux(PodProduit $produit, ?float $montantDemande): float
    {
        if ($montantDemande === null || $montantDemande <= 0) {
            throw new InvalidArgumentException('Le montant de la demande est obligatoire pour un calcul au taux.');
        }

        $taux = (float) ($produit->taux_frais ?? 0);

        return $montantDemande * ($taux / 100);
    }

    private static function fraisMixte(PodProduit $produit, ?float $montantDemande): float
    {
        $fixe = (float) ($produit->frais_fixe ?? 0);
        $variable = 0.0;
        if ($montantDemande !== null && $produit->taux_frais !== null) {
            $variable = $montantDemande * ((float) $produit->taux_frais / 100);
        }

        return $fixe + $variable;
    }

    private static function fraisManuel(PodProduit $produit, ?float $fraisSaisi, ?string &$message): float
    {
        if ($fraisSaisi === null) {
            throw new InvalidArgumentException(
                'Ce produit est en mode manuel : saisissez le montant des frais HT.'
                .($produit->base_calcul ? ' Règle : '.$produit->base_calcul : '')
            );
        }

        $message = $produit->base_calcul;

        return $fraisSaisi;
    }

    private static function applyMinMax(PodProduit $produit, float $frais): float
    {
        if ($produit->frais_min !== null) {
            $frais = max($frais, (float) $produit->frais_min);
        }
        if ($produit->frais_max !== null) {
            $frais = min($frais, (float) $produit->frais_max);
        }

        return $frais;
    }

    /**
     * @return list<array{sens: string, compte: string, libelle_ecriture: string|null, nature_compte: string|null, type_montant: string|null, montant: float}>
     */
    private static function buildLignes(
        PodProduit $produit,
        string $compteClient,
        float $fraisHt,
        float $montantTaf,
        float $totalClient,
    ): array {
        $schema = $produit->lignesComptables;
        if ($schema->isEmpty()) {
            // Schéma minimal par défaut
            return [
                [
                    'sens' => 'D',
                    'compte' => $compteClient,
                    'libelle_ecriture' => ($produit->libelle_ecriture_produit ?: $produit->libelle).' HT',
                    'nature_compte' => 'client',
                    'type_montant' => 'frais_ht',
                    'montant' => $fraisHt,
                ],
                [
                    'sens' => 'D',
                    'compte' => $compteClient,
                    'libelle_ecriture' => $produit->libelle_ecriture_taf ?: ('TAF '.$produit->libelle),
                    'nature_compte' => 'client',
                    'type_montant' => 'taf',
                    'montant' => $montantTaf,
                ],
                [
                    'sens' => 'C',
                    'compte' => (string) ($produit->compte_produit ?: '7XXXXXXX'),
                    'libelle_ecriture' => ($produit->libelle_ecriture_produit ?: $produit->libelle).' HT',
                    'nature_compte' => 'produit',
                    'type_montant' => 'frais_ht',
                    'montant' => $fraisHt,
                ],
                [
                    'sens' => 'C',
                    'compte' => (string) ($produit->compte_taf ?: '331431012'),
                    'libelle_ecriture' => $produit->libelle_ecriture_taf ?: ('TAF '.$produit->libelle),
                    'nature_compte' => 'taf',
                    'type_montant' => 'taf',
                    'montant' => $montantTaf,
                ],
            ];
        }

        $lignes = [];
        foreach ($schema as $line) {
            $montant = match ($line->type_montant) {
                'frais_ht' => $fraisHt,
                'taf' => $montantTaf,
                'frais_plus_taf' => $totalClient,
                'montant_operation' => $totalClient,
                'fixe' => (float) ($line->montant_fixe ?? 0),
                'pourcentage' => round($fraisHt * ((float) ($line->taux ?? 0) / 100), 2),
                default => $fraisHt,
            };

            if ($montant <= 0 && in_array($line->type_montant, ['frais_ht', 'taf', 'frais_plus_taf'], true)) {
                // Lignes à 0 omises (ex. produit gratuit)
                continue;
            }

            $compte = (string) $line->compte;
            if ($line->nature_compte === 'client') {
                $compte = $compteClient;
            }

            $lignes[] = [
                'sens' => strtoupper((string) $line->sens) === 'C' ? 'C' : 'D',
                'compte' => $compte,
                'libelle_ecriture' => $line->libelle_ecriture,
                'nature_compte' => $line->nature_compte,
                'type_montant' => $line->type_montant,
                'montant' => round($montant, 2),
            ];
        }

        return $lignes;
    }
}
