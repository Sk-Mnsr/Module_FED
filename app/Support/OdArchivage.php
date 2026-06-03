<?php

namespace App\Support;

use App\Models\OdClasseur;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Classement électronique OD : année › mois › journée › agent › classeurs.
 */
final class OdArchivage
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public static function applySearchFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['q'])) {
            $term = '%'.trim((string) $filters['q']).'%';
            $query->where(function (Builder $w) use ($term) {
                $w->where('nom_classeur', 'like', $term)
                    ->orWhere('numero_batch', 'like', $term)
                    ->orWhere('numero_piece', 'like', $term)
                    ->orWhere('fichier_integration_original_name', 'like', $term)
                    ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $term));
            });
        }

        if (! empty($filters['nom_classeur'])) {
            $query->where('nom_classeur', 'like', '%'.trim((string) $filters['nom_classeur']).'%');
        }

        if (! empty($filters['numero_batch'])) {
            $query->where('numero_batch', 'like', '%'.trim((string) $filters['numero_batch']).'%');
        }

        if (! empty($filters['statut']) && in_array($filters['statut'], ['brouillon', 'integre'], true)) {
            $query->where('statut', $filters['statut']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['annee'])) {
            $query->whereYear('archive_date', (int) $filters['annee']);
        }

        if (! empty($filters['mois'])) {
            $query->whereMonth('archive_date', (int) $filters['mois']);
        }

        if (! empty($filters['jour'])) {
            $query->whereDay('archive_date', (int) $filters['jour']);
        }

        if (! empty($filters['archive_du'])) {
            $query->whereDate('archive_date', '>=', $filters['archive_du']);
        }

        if (! empty($filters['archive_au'])) {
            $query->whereDate('archive_date', '<=', $filters['archive_au']);
        }

        if (! empty($filters['date_valeur_du'])) {
            $query->whereDate('date_valeur', '>=', $filters['date_valeur_du']);
        }

        if (! empty($filters['date_valeur_au'])) {
            $query->whereDate('date_valeur', '<=', $filters['date_valeur_au']);
        }

        return $query;
    }

    public static function archiveDateKey(OdClasseur $classeur): ?string
    {
        return optional($classeur->archive_date)->toDateString()
            ?? optional($classeur->integrated_at)->toDateString()
            ?? optional($classeur->created_at)->toDateString();
    }

    /**
     * @param  Collection<int, OdClasseur>  $classeurs
     * @param  callable(OdClasseur): array<string, mixed>  $payloadFn
     * @return array<string, array<string, array<string, array<string, array{agent: array<string, mixed>, classeurs: list<array<string, mixed>>}>>>>
     */
    public static function buildTree(Collection $classeurs, callable $payloadFn): array
    {
        $tree = [];

        foreach ($classeurs as $classeur) {
            $dateKey = self::archiveDateKey($classeur);
            if ($dateKey === null) {
                continue;
            }

            [$year, $month, $day] = explode('-', $dateKey);
            $agentKey = (string) $classeur->user_id;

            if (! isset($tree[$year][$month][$day][$agentKey])) {
                $tree[$year][$month][$day][$agentKey] = [
                    'agent' => [
                        'id' => $classeur->user_id,
                        'name' => $classeur->user?->name ?? 'Agent inconnu',
                        'flex_id' => $classeur->user?->flexComptaUserIdentifier() ?? '',
                    ],
                    'classeurs' => [],
                ];
            }

            $tree[$year][$month][$day][$agentKey]['classeurs'][] = $payloadFn($classeur);
        }

        // Tri décroissant des années / mois / jours.
        krsort($tree);
        foreach ($tree as &$months) {
            krsort($months);
            foreach ($months as &$days) {
                krsort($days);
                foreach ($days as &$agents) {
                    uasort($agents, static fn ($a, $b) => strcmp($a['agent']['name'], $b['agent']['name']));
                }
            }
        }

        return $tree;
    }

    /**
     * @param  array<string, array<string, array<string, array<string, mixed>>>>  $tree
     * @return list<array{year: string, month: string, day: string, agent_id: int, agent_name: string, classeur: array<string, mixed>}>
     */
    public static function flattenTreeForSearch(array $tree): array
    {
        $results = [];
        foreach ($tree as $year => $months) {
            foreach ($months as $month => $days) {
                foreach ($days as $day => $agents) {
                    foreach ($agents as $agentData) {
                        foreach ($agentData['classeurs'] as $classeur) {
                            $results[] = [
                                'year' => $year,
                                'month' => $month,
                                'day' => $day,
                                'agent_id' => $agentData['agent']['id'],
                                'agent_name' => $agentData['agent']['name'],
                                'classeur' => $classeur,
                            ];
                        }
                    }
                }
            }
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $requestFilters
     * @return array<string, mixed>
     */
    public static function normalizeFilters(array $requestFilters): array
    {
        return array_filter([
            'q' => $requestFilters['q'] ?? null,
            'nom_classeur' => $requestFilters['nom_classeur'] ?? null,
            'numero_batch' => $requestFilters['numero_batch'] ?? null,
            'statut' => $requestFilters['statut'] ?? null,
            'user_id' => $requestFilters['user_id'] ?? null,
            'annee' => $requestFilters['annee'] ?? null,
            'mois' => $requestFilters['mois'] ?? null,
            'jour' => $requestFilters['jour'] ?? null,
            'archive_du' => $requestFilters['archive_du'] ?? null,
            'archive_au' => $requestFilters['archive_au'] ?? null,
            'date_valeur_du' => $requestFilters['date_valeur_du'] ?? null,
            'date_valeur_au' => $requestFilters['date_valeur_au'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');
    }

    public static function hasActiveSearch(array $filters): bool
    {
        return count($filters) > 0;
    }
}
