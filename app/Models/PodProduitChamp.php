<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PodProduitChamp extends Model
{
    public const TYPE_TEXTE = 'texte';

    public const TYPE_LISTE = 'liste';

    public const TYPE_NUMERIQUE = 'numerique';

    public const TYPE_DATE = 'date';

    public const TYPE_TABLE = 'table';

    public const TYPES = [
        self::TYPE_TEXTE,
        self::TYPE_LISTE,
        self::TYPE_NUMERIQUE,
        self::TYPE_DATE,
        self::TYPE_TABLE,
    ];

    /** Codes réservés (champs système de l’opération). */
    public const CODES_RESERVES = [
        'COMPTE_CLIENT',
        'CODE_AGENCE',
        'NOM_CLIENT',
        'DATE_VALEUR',
        'MONTANT_DEMANDE',
        'FRAIS_SAISI',
        'REFERENCE',
        'LIBELLE',
        'POD_PRODUIT_ID',
    ];

    protected $table = 'pod_produit_champs';

    protected $fillable = [
        'pod_produit_id',
        'pod_produit_ecran_id',
        'code',
        'libelle',
        'type',
        'obligatoire',
        'visible',
        'gris',
        'valeur_defaut',
        'options',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'obligatoire' => 'boolean',
            'visible' => 'boolean',
            'gris' => 'boolean',
            'options' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(PodProduit::class, 'pod_produit_id');
    }

    public function ecran(): BelongsTo
    {
        return $this->belongsTo(PodProduitEcran::class, 'pod_produit_ecran_id');
    }

    /**
     * @return list<array{code: string, libelle: string, actif: bool, ordre: int}>
     */
    public function valeursListeActives(): array
    {
        $valeurs = $this->options['valeurs'] ?? [];
        if (! is_array($valeurs)) {
            return [];
        }

        $out = [];
        foreach ($valeurs as $i => $v) {
            if (! is_array($v)) {
                continue;
            }
            $actif = array_key_exists('actif', $v) ? (bool) $v['actif'] : true;
            if (! $actif) {
                continue;
            }
            $code = trim((string) ($v['code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $out[] = [
                'code' => $code,
                'libelle' => (string) ($v['libelle'] ?? $code),
                'actif' => true,
                'ordre' => (int) ($v['ordre'] ?? $i),
            ];
        }

        usort($out, fn ($a, $b) => $a['ordre'] <=> $b['ordre']);

        return $out;
    }

    /**
     * Options dynamiques depuis une table source.
     *
     * @return list<array{code: string, libelle: string, actif: bool, ordre: int}>
     */
    public function valeursTableActives(): array
    {
        $options = is_array($this->options) ? $this->options : [];
        $tableCode = strtoupper(trim((string) ($options['table_code'] ?? '')));
        $colValeur = strtoupper(trim((string) ($options['colonne_valeur'] ?? '')));
        $colLibelle = strtoupper(trim((string) ($options['colonne_libelle'] ?? '')));
        $disabled = is_array($options['colonnes_desactivees'] ?? null)
            ? $options['colonnes_desactivees']
            : [];

        if ($tableCode === '' || $colValeur === '' || $colLibelle === '') {
            return [];
        }

        $table = PodDataTable::query()
            ->where('code', $tableCode)
            ->where('actif', true)
            ->first();

        if (! $table) {
            return [];
        }

        return $table->optionsFor($colValeur, $colLibelle, $disabled);
    }
}
