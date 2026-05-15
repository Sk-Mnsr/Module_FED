<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoficarteApporteur extends Model
{
    protected $table = 'coficarte_apporteurs';

    protected $fillable = [
        'agence_id',
        'code',
        'nom',
        'telephone',
        'email',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    public function ventes(): HasMany
    {
        return $this->hasMany(CoficarteSale::class, 'coficarte_apporteur_id');
    }
}
