<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoficarteStockThreshold extends Model
{
    public const CIBLE_CENTRAL = 'central';

    public const CIBLE_AGENCE = 'agence';

    protected $table = 'coficarte_stock_thresholds';

    protected $fillable = [
        'cible',
        'agence_id',
        'min_cards',
        'objectif_nb_ventes_mois',
        'objectif_montant_recharges_mois',
    ];

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }
}
