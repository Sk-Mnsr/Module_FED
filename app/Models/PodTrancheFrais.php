<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PodTrancheFrais extends Model
{
    protected $table = 'pod_tranches_frais';

    protected $fillable = [
        'pod_produit_id',
        'libelle',
        'montant_min',
        'montant_max',
        'frais_fixe',
        'taux',
        'frais_min',
        'frais_max',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'montant_min' => 'decimal:2',
            'montant_max' => 'decimal:2',
            'frais_fixe' => 'decimal:2',
            'taux' => 'decimal:4',
            'frais_min' => 'decimal:2',
            'frais_max' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(PodProduit::class, 'pod_produit_id');
    }
}
