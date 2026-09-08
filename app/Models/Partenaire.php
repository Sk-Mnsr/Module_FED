<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partenaire extends Model
{
    protected $fillable = [
        'identifiant',
        'nom',
        'icone',
        'actif',
    ];

    protected $appends = [
        'icone_url',
    ];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }

    public function reconciliationRuns(): HasMany
    {
        return $this->hasMany(ReconciliationRun::class);
    }

    public function scopeActifs(Builder $query): Builder
    {
        return $query->where('actif', true);
    }

    public function getIconeUrlAttribute(): ?string
    {
        if (! filled($this->icone)) {
            return null;
        }

        // Chemin relatif : évite le décalage APP_URL (localhost) vs 127.0.0.1:8000
        return '/storage/'.ltrim((string) $this->icone, '/');
    }
}
