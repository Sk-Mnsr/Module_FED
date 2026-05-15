<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoficarteCampaign extends Model
{
    protected $table = 'coficarte_campaigns';

    protected $fillable = [
        'nom',
        'description',
        'agence_id',
        'objectif_ventes',
        'objectif_montant_recharges',
        'date_debut',
        'date_fin',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'active' => 'boolean',
        ];
    }

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    public function ventes(): HasMany
    {
        return $this->hasMany(CoficarteSale::class, 'coficarte_campaign_id');
    }

    public function recharges(): HasMany
    {
        return $this->hasMany(CoficarteRecharge::class, 'coficarte_campaign_id');
    }

    public function scopeActiveForDate($query, ?\DateTimeInterface $date = null)
    {
        $d = $date ?? now();

        return $query->where('active', true)
            ->whereDate('date_debut', '<=', $d)
            ->whereDate('date_fin', '>=', $d);
    }
}
