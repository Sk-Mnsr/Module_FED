<?php

namespace App\Support;

use App\Models\OdClasseur;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Classement électronique OD : département › année › mois › journée › agent › classeurs.
 */
final class OdArchivage
{
    public const DEPT_FINANCE = 'finance';

    public const DEPT_OPERATIONS = 'operations';
    private const MONTHS_FR = [
        1 => 'Janvier', 2 => 'Fevrier', 3 => 'Mars', 4 => 'Avril',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Aout',
        9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Decembre',
    ];

    public static function monthFolderName(int $month): string
    {
        $label = self::MONTHS_FR[$month] ?? str_pad((string) $month, 2, '0', STR_PAD_LEFT);

        return strtoupper($label);
    }

    public static function dayFolderName(string $year, string $month, string $day): string
    {
        return 'Journée du '.str_pad($day, 2, '0', STR_PAD_LEFT);
    }

    public static function agentFolderName(?\App\Models\User $user): string
    {
        if ($user === null) {
            return 'agent_inconnu';
        }

        $flex = $user->flexComptaUserIdentifier();
        if ($flex !== '') {
            return strtolower($flex);
        }

        $parts = preg_split('/\s+/', trim((string) $user->name)) ?: [];
        if (count($parts) >= 2) {
            $first = strtolower(substr($parts[0], 0, 1));
            $last = strtolower(preg_replace('/[^a-z0-9]+/i', '_', (string) end($parts)) ?? '');

            return $first.'_'.$last;
        }

        return strtolower(preg_replace('/[^a-z0-9]+/i', '_', (string) $user->name) ?? 'agent');
    }

    public static function pieceFolderName(
        OdClasseur $classeur,
        string $year,
        string $month,
        string $day,
        int $sequence
    ): string {
        $seq = str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        $type = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', (string) $classeur->nom_classeur) ?? 'OD');
        $type = trim($type, '_') ?: 'OD';
        $ref = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', (string) ($classeur->numero_batch ?? $classeur->numero_piece ?? $classeur->id)) ?? (string) $classeur->id);

        return 'PC_'.$year.'_'.str_pad($month, 2, '0', STR_PAD_LEFT).'_'.str_pad($day, 2, '0', STR_PAD_LEFT).'_'.$seq.'_'.$type.'_'.$ref;
    }

    /** Libellé court pour l'arborescence (sans date ni séquence). */
    public static function pieceDisplayName(OdClasseur $classeur): string
    {
        $type = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', (string) $classeur->nom_classeur) ?? 'OD');
        $type = trim($type, '_') ?: 'OD';
        $ref = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', (string) ($classeur->numero_batch ?? $classeur->numero_piece ?? $classeur->id)) ?? (string) $classeur->id);

        return 'PC_'.$type.'_'.$ref;
    }

    public static function departmentKey(?\App\Models\User $user): string
    {
        if ($user !== null && $user->hasRole('finance')) {
            return self::DEPT_FINANCE;
        }

        return self::DEPT_OPERATIONS;
    }

    public static function departmentLabel(string $key): string
    {
        return match ($key) {
            self::DEPT_FINANCE => 'Finance',
            self::DEPT_OPERATIONS => 'Operations',
            default => 'Operations',
        };
    }

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
     * @return array<string, array<string, array<string, array<string, array<string, array{agent: array<string, mixed>, classeurs: list<array<string, mixed>>}>>>>
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
            $dept = self::departmentKey($classeur->user);
            $agentKey = (string) $classeur->user_id;

            if (! isset($tree[$dept][$year][$month][$day][$agentKey])) {
                $tree[$dept][$year][$month][$day][$agentKey] = [
                    'agent' => [
                        'id' => $classeur->user_id,
                        'name' => $classeur->user?->name ?? 'Agent inconnu',
                        'flex_id' => $classeur->user?->flexComptaUserIdentifier() ?? '',
                        'folder_name' => self::agentFolderName($classeur->user),
                    ],
                    'folder_labels' => [
                        'year' => $year,
                        'month' => self::monthFolderName((int) $month),
                        'day' => self::dayFolderName($year, $month, $day),
                    ],
                    'classeurs' => [],
                ];
            }

            $sequence = count($tree[$dept][$year][$month][$day][$agentKey]['classeurs']) + 1;
            $payload = $payloadFn($classeur);
            $payload['piece_folder_name'] = self::pieceFolderName($classeur, $year, $month, $day, $sequence);
            $payload['piece_display_name'] = self::pieceDisplayName($classeur);
            $payload['department'] = $dept;
            $payload['folder_path'] = implode('/', [
                'Dossiers Comptables',
                self::departmentLabel($dept),
                $year,
                self::monthFolderName((int) $month),
                self::dayFolderName($year, $month, $day),
                $classeur->user?->name ?? self::agentFolderName($classeur->user),
                $payload['piece_folder_name'],
            ]);

            $tree[$dept][$year][$month][$day][$agentKey]['classeurs'][] = $payload;
        }

        uksort($tree, static fn (string $a, string $b): int => strcmp($a, $b));
        foreach ($tree as &$years) {
            krsort($years);
            foreach ($years as &$months) {
                krsort($months);
                foreach ($months as &$days) {
                    krsort($days);
                    foreach ($days as &$agents) {
                        uasort($agents, static fn ($a, $b) => strcmp($a['agent']['name'], $b['agent']['name']));
                    }
                }
            }
        }

        return $tree;
    }

    /**
     * @param  array<string, array<string, array<string, array<string, array<string, mixed>>>>>  $tree
     * @return list<array{dept: string, year: string, month: string, day: string, agent_id: int, agent_name: string, classeur: array<string, mixed>}>
     */
    public static function flattenTreeForSearch(array $tree): array
    {
        $results = [];
        foreach ($tree as $dept => $years) {
            foreach ($years as $year => $months) {
                foreach ($months as $month => $days) {
                    foreach ($days as $day => $agents) {
                        foreach ($agents as $agentData) {
                            foreach ($agentData['classeurs'] as $classeur) {
                                $results[] = [
                                    'dept' => $dept,
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
