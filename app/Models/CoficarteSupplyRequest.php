<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoficarteSupplyRequest extends Model
{
    public const STATUS_EN_ATTENTE = 'en_attente';

    public const STATUS_TRANSFERT_EN_COURS = 'transfert_en_cours';

    public const STATUS_PARTIELLE = 'partielle';

    public const STATUS_ACCEPTEE = 'acceptee';

    public const STATUS_REFUSEE = 'refusee';

    public const STATUS_ANNULEE = 'annulee';

    protected $table = 'coficarte_supply_requests';

    protected $fillable = [
        'agence_id',
        'chef_user_id',
        'quantite_demandee',
        'quantite_livree',
        'commentaire',
        'status',
        'cloture_partielle',
        'reponse_monetique',
        'traite_par_user_id',
        'traite_le',
    ];

    protected function casts(): array
    {
        return [
            'traite_le' => 'datetime',
            'cloture_partielle' => 'boolean',
        ];
    }

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    public function chef(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chef_user_id');
    }

    public function traitePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traite_par_user_id');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(CoficarteTransfer::class, 'supply_request_id');
    }
}
