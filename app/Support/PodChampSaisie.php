<?php

namespace App\Support;

use App\Models\PodProduit;
use App\Models\PodProduitChamp;
use App\Models\User;
use App\Support\PodEcranAccess;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PodChampSaisie
{
    /**
     * Valide et normalise les valeurs saisies selon le paramétrage produit.
     *
     * @param  array<string, mixed>  $saisies
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function validateAndNormalize(PodProduit $produit, array $saisies, ?User $user = null): array
    {
        $produit->loadMissing(['champs.ecran']);
        $champs = $produit->champs->filter(fn (PodProduitChamp $c) => $c->visible);
        $normalized = [];
        $errors = [];

        foreach ($champs as $champ) {
            $code = $champ->code;

            if ($champ->ecran && $user && ! PodEcranAccess::userCanAccess($user, $champ->ecran)) {
                if ($champ->valeur_defaut !== null && $champ->valeur_defaut !== '') {
                    try {
                        $normalized[$code] = self::castValue($champ, $champ->valeur_defaut);
                    } catch (\InvalidArgumentException) {
                        $normalized[$code] = null;
                    }
                } else {
                    $normalized[$code] = null;
                }
                continue;
            }

            $raw = array_key_exists($code, $saisies) ? $saisies[$code] : null;
            if ($raw === null || $raw === '') {
                $raw = $champ->valeur_defaut;
            }

            if ($champ->gris) {
                $normalized[$code] = self::castValue($champ, $champ->valeur_defaut);
                continue;
            }

            if (($raw === null || $raw === '') && $champ->obligatoire) {
                $errors["champs_saisis.{$code}"] = "Le champ « {$champ->libelle} » est obligatoire.";
                continue;
            }

            if ($raw === null || $raw === '') {
                $normalized[$code] = null;
                continue;
            }

            try {
                $normalized[$code] = self::castValue($champ, $raw);
            } catch (\InvalidArgumentException $e) {
                $errors["champs_saisis.{$code}"] = $e->getMessage();
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    /**
     * @throws \InvalidArgumentException
     */
    private static function castValue(PodProduitChamp $champ, mixed $raw): mixed
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $options = is_array($champ->options) ? $champ->options : [];

        return match ($champ->type) {
            PodProduitChamp::TYPE_TEXTE => self::castTexte($champ, (string) $raw, $options),
            PodProduitChamp::TYPE_LISTE => self::castListe($champ, (string) $raw),
            PodProduitChamp::TYPE_TABLE => self::castTable($champ, (string) $raw),
            PodProduitChamp::TYPE_NUMERIQUE => self::castNumerique($champ, $raw, $options),
            PodProduitChamp::TYPE_DATE => self::castDate($champ, (string) $raw),
            default => throw new \InvalidArgumentException("Type de champ inconnu pour « {$champ->libelle} »."),
        };
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private static function castTexte(PodProduitChamp $champ, string $raw, array $options): string
    {
        $value = trim($raw);
        $max = isset($options['max_length']) ? (int) $options['max_length'] : null;
        if ($max !== null && $max > 0 && mb_strlen($value) > $max) {
            throw new \InvalidArgumentException(
                "« {$champ->libelle} » dépasse {$max} caractères."
            );
        }

        return $value;
    }

    private static function castListe(PodProduitChamp $champ, string $raw): string
    {
        $code = trim($raw);
        $allowed = array_column($champ->valeursListeActives(), 'code');
        if ($allowed === [] || ! in_array($code, $allowed, true)) {
            throw new \InvalidArgumentException(
                "Valeur invalide pour « {$champ->libelle} »."
            );
        }

        return $code;
    }

    private static function castTable(PodProduitChamp $champ, string $raw): string
    {
        $code = trim($raw);
        $allowed = array_column($champ->valeursTableActives(), 'code');
        if ($allowed === [] || ! in_array($code, $allowed, true)) {
            throw new \InvalidArgumentException(
                "Valeur invalide pour « {$champ->libelle} » (table source)."
            );
        }

        return $code;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private static function castNumerique(PodProduitChamp $champ, mixed $raw, array $options): float
    {
        if (! is_numeric($raw)) {
            throw new \InvalidArgumentException("« {$champ->libelle} » doit être numérique.");
        }

        $value = (float) $raw;
        if (isset($options['min']) && $options['min'] !== '' && $options['min'] !== null && $value < (float) $options['min']) {
            throw new \InvalidArgumentException("« {$champ->libelle} » doit être ≥ {$options['min']}.");
        }
        if (isset($options['max']) && $options['max'] !== '' && $options['max'] !== null && $value > (float) $options['max']) {
            throw new \InvalidArgumentException("« {$champ->libelle} » doit être ≤ {$options['max']}.");
        }

        $decimales = isset($options['decimales']) ? max(0, (int) $options['decimales']) : 2;

        return round($value, $decimales);
    }

    private static function castDate(PodProduitChamp $champ, string $raw): string
    {
        $v = Validator::make(['d' => $raw], ['d' => 'required|date']);
        if ($v->fails()) {
            throw new \InvalidArgumentException("« {$champ->libelle} » doit être une date valide.");
        }

        return substr($raw, 0, 10);
    }
}
