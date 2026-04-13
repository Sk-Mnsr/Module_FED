<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'description',
        'code',
        'responsable',
        'sous_categorie_id',
        'type_depense_id',
        'stock_actuel',
        'seuil_alerte',
    ];

    public function sousCategorie(): BelongsTo
    {
        return $this->belongsTo(SousCategorie::class);
    }

    public function typeDepense(): BelongsTo
    {
        return $this->belongsTo(TypeDepense::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function latestMovement()
    {
        return $this->hasOne(StockMovement::class)->latestOfMany();
    }
}

