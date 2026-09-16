<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PodProduitScript extends Model
{
    protected $table = 'pod_produit_scripts';

    protected $fillable = [
        'pod_produit_id',
        'code',
        'libelle',
        'moteur',
        'parametres',
        'description',
        'actif',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'parametres' => 'array',
            'actif' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(PodProduit::class, 'pod_produit_id');
    }
}
