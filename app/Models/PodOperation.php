<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PodOperation extends Model
{
    public const STATUT_BROUILLON = 'brouillon';

    public const STATUT_VALIDE = 'valide';

    public const STATUT_ANNULE = 'annule';

    protected $table = 'pod_operations';

    protected $fillable = [
        'pod_produit_id',
        'user_id',
        'validated_by_user_id',
        'reference',
        'compte_client',
        'code_agence',
        'nom_client',
        'date_valeur',
        'montant_demande',
        'frais_ht',
        'taux_taf',
        'montant_taf',
        'total_client',
        'devise',
        'libelle',
        'calcul_detail',
        'champs_saisis',
        'statut',
        'validated_at',
        'motif_annulation',
    ];

    protected function casts(): array
    {
        return [
            'date_valeur' => 'date',
            'montant_demande' => 'decimal:2',
            'frais_ht' => 'decimal:2',
            'taux_taf' => 'decimal:4',
            'montant_taf' => 'decimal:2',
            'total_client' => 'decimal:2',
            'calcul_detail' => 'array',
            'champs_saisis' => 'array',
            'validated_at' => 'datetime',
        ];
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(PodProduit::class, 'pod_produit_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by_user_id');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(PodOperationLigne::class, 'pod_operation_id')->orderBy('sort_order');
    }
}
