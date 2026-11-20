<?php

namespace App\Services\Integrations;

use App\Models\CoficarteApporteur;
use Illuminate\Support\Facades\DB;

/**
 * Synchronise le référentiel national des apporteurs d’affaires depuis Flexcube (UDTM_LOV).
 *
 * - Code = LOV, Nom = LOV_DESC.
 * - Un code absent du référentiel est créé (actif).
 * - Un code déjà présent au niveau national voit son nom mis à jour ; son statut actif/inactif n’est pas modifié.
 * - Les apporteurs absents de Flexcube ne sont pas désactivés.
 * - Un code déjà utilisé par une agence est ignoré (même règle que l’import CSV).
 */
final class FlexcubeApporteurSync
{
    public const SQL = <<<SQL
select LOV,LOV_DESC from CFSFCUBS145.UDTM_LOV lov  where  lov.FIELD_NAME='APP_AFFAIRE' and LOV like 'AP-M%'
SQL;

    private const MAX_ROWS = 10000;

    public function __construct(private readonly FlexcubeQueryRunner $runner) {}

    /**
     * @return array{crees: int, mis_a_jour: int, inchanges: int, ignores: int, erreurs: list<string>, total_flexcube: int}
     *
     * @throws \RuntimeException si Flexcube est inaccessible ou non configuré
     */
    public function synchroniser(): array
    {
        $lignes = $this->runner->select(self::SQL, [], self::MAX_ROWS);

        $crees = 0;
        $misAJour = 0;
        $inchanges = 0;
        $ignores = 0;
        $erreurs = [];
        $dejaVus = [];

        DB::transaction(function () use ($lignes, &$crees, &$misAJour, &$inchanges, &$ignores, &$erreurs, &$dejaVus) {
            $existants = CoficarteApporteur::query()
                ->whereNotNull('code')
                ->get(['id', 'code', 'nom', 'agence_id'])
                ->keyBy(fn (CoficarteApporteur $a) => mb_strtoupper(trim((string) $a->code)));

            foreach ($lignes as $index => $ligne) {
                $code = mb_strtoupper(trim((string) ($ligne['LOV'] ?? '')));
                $nom = trim((string) ($ligne['LOV_DESC'] ?? ''));
                $numero = $index + 1;

                if ($code === '' || $nom === '') {
                    $ignores++;
                    continue;
                }

                if (mb_strlen($code) > 64 || mb_strlen($nom) > 191) {
                    $erreurs[] = "Ligne {$numero} : code ou nom trop long ({$code}).";
                    $ignores++;
                    continue;
                }

                // Doublon dans la réponse Flexcube (ex. même LOV présent plusieurs fois) : premier gagnant.
                if (isset($dejaVus[$code])) {
                    $ignores++;
                    continue;
                }
                $dejaVus[$code] = true;

                /** @var CoficarteApporteur|null $existant */
                $existant = $existants->get($code);

                if ($existant === null) {
                    $nouveau = CoficarteApporteur::create([
                        'agence_id' => null,
                        'code' => $code,
                        'nom' => $nom,
                        'telephone' => null,
                        'email' => null,
                        'actif' => true,
                    ]);
                    $existants->put($code, $nouveau);
                    $crees++;
                    continue;
                }

                if ($existant->agence_id !== null) {
                    $erreurs[] = "Ligne {$numero} : le code {$code} est déjà utilisé hors du référentiel national.";
                    $ignores++;
                    continue;
                }

                if ($existant->nom !== $nom) {
                    $existant->update(['nom' => $nom]);
                    $misAJour++;
                } else {
                    $inchanges++;
                }
            }
        });

        return [
            'crees' => $crees,
            'mis_a_jour' => $misAJour,
            'inchanges' => $inchanges,
            'ignores' => $ignores,
            'erreurs' => $erreurs,
            'total_flexcube' => count($lignes),
        ];
    }
}
