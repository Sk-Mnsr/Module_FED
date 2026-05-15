<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CoficarteCard extends Model
{
    public const STATUS_EN_STOCK = 'en_stock';

    public const STATUS_VENDU = 'vendu';

    public const STATUS_EN_TRANSFERT = 'en_transfert';

    public const STATUS_EN_ATTENTE_ENCAISSEMENT = 'en_attente_encaissement';

    protected $table = 'coficarte_cards';

    protected $fillable = [
        'created_by',
        'agence_id',
        'assigned_to_user_id',
        'numero_carte',
        'reference_facture',
        'facture_path',
        'reference_bon_livraison',
        'bon_livraison_path',
        'prix_vente',
        'prix_achat',
        'date_livraison',
        'date_expiration',
        'status',
        'possesseur',
    ];

    protected function casts(): array
    {
        return [
            'date_livraison' => 'date',
            'date_expiration' => 'date',
            'prix_vente' => 'integer',
            'prix_achat' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function sale(): HasOne
    {
        return $this->hasOne(CoficarteSale::class, 'coficarte_card_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CoficarteCardMovement::class, 'coficarte_card_id')->orderByDesc('created_at');
    }

    public function recharges(): HasMany
    {
        return $this->hasMany(CoficarteRecharge::class, 'coficarte_card_id');
    }
}
