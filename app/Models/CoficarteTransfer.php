<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoficarteTransfer extends Model
{
    public const STATUS_EN_ATTENTE = 'en_attente';

    public const STATUS_VALIDE = 'valide';

    public const STATUS_REJETE = 'rejete';

    public const STATUS_ANNULE = 'annule';

    protected $table = 'coficarte_transfers';

    protected $fillable = [
        'user_id',
        'receveur_user_id',
        'receveur',
        'debut_plage',
        'fin_plage',
        'card_ids',
        'supply_request_id',
        'supply_request_completion',
        'bon_numero',
        'commentaire',
        'status',
        'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'validated_at' => 'datetime',
            'card_ids' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function receveurUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receveur_user_id');
    }

    public function supplyRequest(): BelongsTo
    {
        return $this->belongsTo(CoficarteSupplyRequest::class, 'supply_request_id');
    }
}
