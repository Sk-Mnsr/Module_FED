<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PodOperationLigne extends Model
{
    protected $table = 'pod_operation_lignes';

    protected $fillable = [
        'pod_operation_id',
        'sort_order',
        'sens',
        'compte',
        'libelle_ecriture',
        'nature_compte',
        'type_montant',
        'montant',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'montant' => 'decimal:2',
        ];
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(PodOperation::class, 'pod_operation_id');
    }
}
