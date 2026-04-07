<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categorie extends Model
{
    protected $fillable = ['nom', 'famille_id'];

    public function famille(): BelongsTo
    {
        return $this->belongsTo(Famille::class);
    }

    public function sousCategories(): HasMany
    {
        return $this->hasMany(SousCategorie::class);
    }
}
