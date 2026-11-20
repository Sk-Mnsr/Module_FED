<?php

namespace App\Support;

use App\Models\PodProduit;
use App\Models\PodProduitChamp;
use App\Models\PodProduitScript;
use App\Services\Integrations\FlexcubeQueryRunner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Recherche Flexcube déclenchée par un champ : le champ porte options.recherche = code d’un script « requête ».
 * Les variables :CODE de la requête prennent la valeur des champs saisis du même code.
 * Les colonnes renvoyées remplissent les champs de même code et les infos client de l’opération.
 */
final class PodRechercheFlexcube
{
    /** Colonnes Flexcube → champs système de l’opération. */
    private const SYSTEME = [
        'compte_client' => ['COMPTE_CLIENT', 'NO_COMPTE'],
        'code_agence' => ['CODE_AGENCE'],
        'nom_client' => ['NOM_CLIENT', 'COMPTE'],
    ];

    public function __construct(private readonly FlexcubeQueryRunner $runner) {}

    public static function scriptCode(PodProduitChamp $champ): ?string
    {
        $code = strtoupper(trim((string) (($champ->options ?? [])['recherche'] ?? '')));

        return $code !== '' ? $code : null;
    }

    /**
     * @param  array<string, mixed>  $saisies
     * @return array{ligne: array<string, mixed>, valeurs: array<string, mixed>, systeme: array<string, string|null>}
     */
    public function rechercher(PodProduit $produit, PodProduitChamp $champ, array $saisies): array
    {
        $code = self::scriptCode($champ);
        if ($code === null) {
            throw new RuntimeException("Le champ « {$champ->libelle} » n’a pas de recherche Flexcube.");
        }

        $produit->loadMissing(['scripts', 'champs']);
        /** @var PodProduitScript|null $script */
        $script = $produit->scripts->first(fn (PodProduitScript $s) => strtoupper($s->code) === $code);
        $sql = trim((string) (($script?->parametres ?? [])['requete'] ?? ''));
        if (! $script || ! $script->actif || $sql === '') {
            throw new RuntimeException("Script de recherche « {$code} » introuvable ou inactif.");
        }

        $binds = [];
        foreach (FlexcubeQueryRunner::bindNames($sql) as $nom) {
            $valeur = $saisies[$nom] ?? null;
            if ($valeur === null || trim((string) $valeur) === '') {
                $libelle = $produit->champs->firstWhere('code', $nom)?->libelle ?? $nom;
                throw new RuntimeException("Renseignez « {$libelle} » avant la recherche.");
            }
            $binds[$nom] = trim((string) $valeur);
        }

        try {
            $lignes = $this->runner->select($sql, $binds, 2);
        } catch (RuntimeException $e) {
            Log::warning('PodRechercheFlexcube: requête en échec', ['script' => $code, 'error' => $e->getMessage()]);
            throw new RuntimeException('Flexcube ne répond pas pour le moment. Réessayez dans quelques instants.');
        }

        if ($lignes === []) {
            $valeur = trim((string) ($saisies[$champ->code] ?? ''));
            throw new RuntimeException("Le numéro {$valeur} n’existe pas dans Flexcube. Vérifiez la saisie et relancez la recherche.");
        }

        $ligne = $lignes[0];
        $codesChamps = $produit->champs->pluck('code')->map(fn ($c) => strtoupper((string) $c))->all();

        $valeurs = [];
        foreach ($ligne as $colonne => $valeur) {
            if (in_array(strtoupper((string) $colonne), $codesChamps, true) && strtoupper((string) $colonne) !== strtoupper($champ->code)) {
                $valeurs[strtoupper((string) $colonne)] = $valeur;
            }
        }

        $systeme = [];
        foreach (self::SYSTEME as $cible => $colonnes) {
            foreach ($colonnes as $colonne) {
                if (array_key_exists($colonne, $ligne) && $ligne[$colonne] !== null && $ligne[$colonne] !== '') {
                    $systeme[$cible] = (string) $ligne[$colonne];
                    break;
                }
            }
        }

        return ['ligne' => $ligne, 'valeurs' => $valeurs, 'systeme' => $systeme];
    }

    /**
     * Rejoue côté serveur les recherches des champs de l’étape, pour que les valeurs
     * remplies viennent de Flexcube et non du navigateur.
     *
     * @param  Collection<int, PodProduitChamp>  $champs
     * @param  array<string, mixed>  $saisies
     * @return array{saisies: array<string, mixed>, systeme: array<string, string|null>}
     *
     * @throws ValidationException
     */
    public function appliquer(PodProduit $produit, Collection $champs, array $saisies): array
    {
        $systeme = [];

        foreach ($champs as $champ) {
            if (self::scriptCode($champ) === null) {
                continue;
            }
            $valeur = $saisies[$champ->code] ?? null;
            if (($valeur === null || $valeur === '') && ! $champ->obligatoire) {
                continue;
            }

            try {
                $resultat = $this->rechercher($produit, $champ, $saisies);
            } catch (RuntimeException $e) {
                throw ValidationException::withMessages(["champs_saisis.{$champ->code}" => $e->getMessage()]);
            }

            $saisies = array_merge($saisies, $resultat['valeurs']);
            $systeme = array_merge($systeme, $resultat['systeme']);
        }

        return ['saisies' => $saisies, 'systeme' => $systeme];
    }
}
