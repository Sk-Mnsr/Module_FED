<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PodOperation extends Model
{
    public const STATUT_BROUILLON = 'brouillon';

    public const STATUT_EN_COURS = 'en_cours';

    public const STATUT_SUSPENDUE = 'suspendue';

    public const STATUT_REJETE = 'rejete';

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
        'etape_index',
        'etape_code',
        'etape_libelle',
        'validated_at',
        'motif_annulation',
        'motif_decision',
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
            'etape_index' => 'integer',
        ];
    }

    public function etapes(): HasMany
    {
        return $this->hasMany(PodOperationEtape::class, 'pod_operation_id')->orderBy('created_at')->orderBy('id');
    }

    public function pieces(): HasMany
    {
        return $this->hasMany(PodOperationPiece::class, 'pod_operation_id')->orderBy('id');
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
