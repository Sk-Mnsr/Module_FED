<?php

namespace App\Support;

use App\Models\PodProduit;
use App\Models\PodProduitAudit;
use App\Models\User;

final class PodProduitAuditor
{
    /**
     * @param  array<string, mixed>|null  $avant
     * @param  array<string, mixed>|null  $apres
     */
    public static function log(
        PodProduit $produit,
        string $action,
        ?string $resume = null,
        ?array $avant = null,
        ?array $apres = null,
        ?User $user = null,
    ): void {
        PodProduitAudit::create([
            'pod_produit_id' => $produit->id,
            'user_id' => $user?->id ?? auth()->id(),
            'action' => $action,
            'resume' => $resume,
            'avant' => $avant,
            'apres' => $apres,
        ]);
    }

    /**
     * Snapshot compact pour audit.
     *
     * @return array<string, mixed>
     */
    public static function snapshot(PodProduit $produit): array
    {
        $produit->loadMissing(['tranches', 'lignesComptables', 'champs.ecran', 'ecrans', 'constantes', 'scripts']);

        return [
            'code' => $produit->code,
            'libelle' => $produit->libelle,
            'statut' => $produit->statut,
            'actif' => $produit->actif,
            'mode_frais' => $produit->mode_frais,
            'compte_produit' => $produit->compte_produit,
            'compte_taf' => $produit->compte_taf,
            'initiateur' => $produit->initiateur,
            'tranches_count' => $produit->tranches->count(),
            'lignes_count' => $produit->lignesComptables->count(),
            'champs_count' => $produit->champs->count(),
            'ecrans_count' => $produit->ecrans->count(),
            'constantes_count' => $produit->constantes->count(),
            'scripts_count' => $produit->scripts->count(),
            'champs' => $produit->champs->map(fn ($c) => [
                'code' => $c->code,
                'type' => $c->type,
                'ecran' => $c->ecran?->code,
            ])->values()->all(),
            'constantes' => $produit->constantes->map(fn ($c) => [
                'code' => $c->code,
                'mode' => $c->mode,
                'valeur_reference' => $c->valeur_reference,
            ])->values()->all(),
            'scripts' => $produit->scripts->map(fn ($s) => [
                'code' => $s->code,
                'moteur' => $s->moteur,
            ])->values()->all(),
        ];
    }
}
