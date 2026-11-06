<?php

namespace App\Support;

use App\Models\PodOperation;
use App\Models\PodOperationEtape;
use App\Models\PodProduit;
use App\Models\PodProduitChamp;
use App\Models\PodProduitEcran;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Circuit d’une opération : un écran actif = une étape, dans l’ordre des écrans.
 * L’étape 0 est la saisie initiale ; les suivantes sont traitées par d’autres utilisateurs.
 */
final class PodOperationCircuit
{
    /**
     * @return list<array{index: int, code: string|null, libelle: string, ecran: PodProduitEcran|null}>
     */
    public static function steps(PodProduit $produit): array
    {
        $produit->loadMissing('ecrans');

        $steps = $produit->ecrans
            ->filter(fn (PodProduitEcran $e) => $e->actif)
            ->sortBy('sort_order')
            ->values()
            ->map(fn (PodProduitEcran $e, int $i) => [
                'index' => $i,
                'code' => $e->code,
                'libelle' => $e->libelle,
                'ecran' => $e,
            ])
            ->all();

        if ($steps === []) {
            $steps[] = ['index' => 0, 'code' => null, 'libelle' => 'Saisie', 'ecran' => null];
        }

        if (count($steps) === 1) {
            $steps[] = ['index' => 1, 'code' => null, 'libelle' => 'Validation', 'ecran' => null];
        }

        return $steps;
    }

    /**
     * @param  list<array{index: int, code: string|null, libelle: string, ecran: PodProduitEcran|null}>  $steps
     */
    public static function currentIndex(PodOperation $operation, array $steps): int
    {
        if ($operation->etape_code) {
            foreach ($steps as $step) {
                if ($step['code'] !== null && strtoupper($step['code']) === strtoupper($operation->etape_code)) {
                    return $step['index'];
                }
            }
        }

        return min(max((int) $operation->etape_index, 0), count($steps) - 1);
    }

    /**
     * @param  array{index: int, code: string|null, libelle: string, ecran: PodProduitEcran|null}  $step
     * @return Collection<int, PodProduitChamp>
     */
    public static function champsForStep(PodProduit $produit, array $step): Collection
    {
        $produit->loadMissing('champs.ecran');
        $ecranId = $step['ecran']?->id;

        return $produit->champs
            ->filter(fn (PodProduitChamp $c) => $c->visible)
            ->filter(function (PodProduitChamp $c) use ($step, $ecranId) {
                if ($ecranId !== null && (int) $c->pod_produit_ecran_id === (int) $ecranId) {
                    return true;
                }

                return $step['index'] === 0 && $c->pod_produit_ecran_id === null;
            })
            ->values();
    }

    /**
     * @param  array{index: int, code: string|null, libelle: string, ecran: PodProduitEcran|null}  $step
     */
    public static function userCanActOnStep(?User $user, array $step): bool
    {
        if ($user === null) {
            return false;
        }

        return $step['ecran'] === null || PodEcranAccess::userCanAccess($user, $step['ecran']);
    }

    /**
     * @return list<int>
     */
    public static function participantIds(PodOperation $operation): array
    {
        $operation->loadMissing('etapes');

        return collect([$operation->user_id])
            ->merge(
                $operation->etapes
                    ->whereIn('action', [
                        PodOperationEtape::ACTION_SOUMISE,
                        PodOperationEtape::ACTION_VALIDEE,
                        PodOperationEtape::ACTION_REPRISE,
                    ])
                    ->pluck('user_id')
            )
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Motif pour lequel l’utilisateur ne peut pas traiter l’étape courante, ou null s’il le peut.
     *
     * @param  list<array{index: int, code: string|null, libelle: string, ecran: PodProduitEcran|null}>  $steps
     */
    public static function blockReason(?User $user, PodOperation $operation, array $steps): ?string
    {
        if ($user === null) {
            return 'Utilisateur non connecté.';
        }

        if ($operation->statut !== PodOperation::STATUT_EN_COURS) {
            return 'Le dossier n’est pas en attente de traitement.';
        }

        $index = self::currentIndex($operation, $steps);
        $step = $steps[$index] ?? null;
        if ($step === null || $index === 0) {
            return 'Aucune étape à traiter.';
        }

        if (! self::userCanActOnStep($user, $step)) {
            return 'Étape réservée à : '.self::roleLabel($step['ecran']).'.';
        }

        if (! ModuleAccess::isAdminUser($user) && in_array((int) $user->id, self::participantIds($operation), true)) {
            return 'Vous avez déjà agi sur ce dossier : un autre utilisateur doit traiter cette étape.';
        }

        return null;
    }

    public static function roleLabel(?PodProduitEcran $ecran): string
    {
        if ($ecran === null) {
            return 'tout utilisateur du module';
        }

        $profils = is_array($ecran->profils) ? $ecran->profils : [];
        if ($profils === []) {
            return 'tout utilisateur du module';
        }

        $noms = Role::query()->whereIn('slug', $profils)->pluck('nom', 'slug');

        return collect($profils)
            ->map(fn (string $p) => PodProduitEcran::PROFILS[$p]['label'] ?? ($noms[$p] ?? $p))
            ->implode(', ');
    }

    /**
     * @param  list<array{index: int, code: string|null, libelle: string, ecran: PodProduitEcran|null}>  $steps
     */
    public static function moveTo(PodOperation $operation, array $steps, int $index): void
    {
        $step = $steps[$index] ?? null;
        $operation->etape_index = $index;
        $operation->etape_code = $step['code'] ?? null;
        $operation->etape_libelle = $step['libelle'] ?? null;
    }

    public static function log(
        PodOperation $operation,
        string $action,
        ?array $step,
        ?User $user,
        ?string $commentaire = null,
    ): PodOperationEtape {
        return PodOperationEtape::create([
            'pod_operation_id' => $operation->id,
            'etape_index' => $step['index'] ?? (int) $operation->etape_index,
            'ecran_code' => $step['code'] ?? null,
            'ecran_libelle' => $step['libelle'] ?? null,
            'action' => $action,
            'user_id' => $user?->id,
            'commentaire' => $commentaire !== null && trim($commentaire) !== '' ? trim($commentaire) : null,
        ]);
    }
}
