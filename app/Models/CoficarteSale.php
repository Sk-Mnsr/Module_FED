<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoficarteSale extends Model
{
    public const PAYMENT_EN_ATTENTE = 'en_attente';

    public const PAYMENT_ENCAISSE = 'encaisse';

    public const COMPTE_PACK_IN = 'in_pack';

    public const COMPTE_PACK_HORS = 'hors_pack';

    protected $table = 'coficarte_sales';

    protected $fillable = [
        'user_id',
        'coficarte_card_id',
        'date_vente',
        'derniers_4',
        'type_acheteur',
        'nom_client',
        'numero_compte_client',
        'compte_client_pack',
        'telephone_client',
        'email_client',
        'adresse_client',
        'montant_premiere_recharge',
        'fiche_enrolement_path',
        'gpt_id',
        'locked',
        'payment_status',
        'encaissement_code',
        'bordereau_caisse_path',
        'coficarte_apporteur_id',
        'coficarte_campaign_id',
        'kyc_type_piece',
        'kyc_numero_piece',
        'kyc_date_emission',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'date_vente' => 'date',
            'locked' => 'boolean',
            'kyc_date_emission' => 'date',
            'montant_premiere_recharge' => 'integer',
            'activated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(CoficarteCard::class, 'coficarte_card_id');
    }

    public function apporteur(): BelongsTo
    {
        return $this->belongsTo(CoficarteApporteur::class, 'coficarte_apporteur_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CoficarteCampaign::class, 'coficarte_campaign_id');
    }
}
