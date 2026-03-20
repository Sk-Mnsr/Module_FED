<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banque extends Model
{
    protected $fillable = [
        'nom',
        'compte_miroir',
        'compte_externe',
    ];

    /**
     * Obtenir les fournisseurs liés à cette banque.
     */
    public function fournisseurs()
    {
        return $this->hasMany(Fournisseur::class);
    }
}
