<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Famille extends Model
{
    protected $fillable = ['nom'];

    public function categories(): HasMany
    {
        return $this->hasMany(Categorie::class);
    }
}
