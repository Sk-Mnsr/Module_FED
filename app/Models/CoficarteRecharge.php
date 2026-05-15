<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoficarteRecharge extends Model
{
    public const PAYMENT_EN_ATTENTE = 'en_attente';

    public const PAYMENT_ENCAISSE = 'encaisse';

    protected $table = 'coficarte_recharges';

    protected $fillable = [
        'coficarte_card_id',
        'user_id',
        'montant',
        'numero_carte_saisi',
        'agence_enregistrement_id',
        'titulaire_carte',
        'email_titulaire',
        'honoraire_chargement',
        'payment_status',
        'encaissement_code',
        'bordereau_caisse_path',
        'confirmed_by_user_id',
        'confirmed_at',
        'commentaire',
        'coficarte_campaign_id',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'honoraire_chargement' => 'integer',
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(CoficarteCard::class, 'coficarte_card_id');
    }

    public function agenceEnregistrement(): BelongsTo
    {
        return $this->belongsTo(Agence::class, 'agence_enregistrement_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CoficarteCampaign::class, 'coficarte_campaign_id');
    }
}
