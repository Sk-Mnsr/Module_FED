<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PodLigneComptable extends Model
{
    protected $table = 'pod_lignes_comptables';

    protected $fillable = [
        'pod_produit_id',
        'sort_order',
        'sens',
        'compte',
        'libelle_ecriture',
        'nature_compte',
        'type_montant',
        'montant_fixe',
        'taux',
        'obligatoire',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'montant_fixe' => 'decimal:2',
            'taux' => 'decimal:4',
            'obligatoire' => 'boolean',
        ];
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(PodProduit::class, 'pod_produit_id');
    }
}
