<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PodProduitAudit extends Model
{
    protected $table = 'pod_produit_audits';

    protected $fillable = [
        'pod_produit_id',
        'user_id',
        'action',
        'resume',
        'avant',
        'apres',
    ];

    protected function casts(): array
    {
        return [
            'avant' => 'array',
            'apres' => 'array',
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
}
