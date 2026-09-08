<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PodProduit extends Model
{
    public const STATUT_BROUILLON = 'brouillon';

    public const STATUT_VALIDE = 'valide';

    public const STATUT_PRODUCTION = 'production';

    public const MODE_FIXE = 'fixe';

    public const MODE_TRANCHE = 'tranche';

    public const MODE_TAUX = 'taux';

    public const MODE_MIXTE = 'mixte';

    public const MODE_MANUEL = 'manuel';

    public const MODE_GRATUIT = 'gratuit';

    protected $table = 'pod_produits';

    protected $fillable = [
        'code',
        'libelle',
        'type_operation',
        'devise',
        'code_taf',
        'libelle_ecriture_produit',
        'libelle_ecriture_taf',
        'compte_produit',
        'compte_taf',
        'compte_client_mask',
        'initiateur',
        'validateur',
        'mode_frais',
        'frais_fixe',
        'taux_frais',
        'frais_min',
        'frais_max',
        'taux_taf',
        'base_calcul',
        'notes',
        'statut',
        'actif',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'frais_fixe' => 'decimal:2',
            'taux_frais' => 'decimal:4',
            'frais_min' => 'decimal:2',
            'frais_max' => 'decimal:2',
            'taux_taf' => 'decimal:4',
            'actif' => 'boolean',
        ];
    }

    public function tranches(): HasMany
    {
        return $this->hasMany(PodTrancheFrais::class, 'pod_produit_id')->orderBy('sort_order');
    }

    public function lignesComptables(): HasMany
    {
        return $this->hasMany(PodLigneComptable::class, 'pod_produit_id')->orderBy('sort_order');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function tauxTafEffectif(): float
    {
        if ($this->taux_taf !== null) {
            return (float) $this->taux_taf;
        }

        return (float) AppSetting::get('pod.taux_taf_defaut', 10);
    }
}
