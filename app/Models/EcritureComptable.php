<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EcritureComptable extends Model
{
    use HasFactory;

    protected $table = 'ecritures_comptables';

    protected $fillable = [
        'numero',
        'no_batch',
        'no_compte',
        'sens',
        'montant',
        'code_operation',
        'date_de_valeur',
        'code_agence',
        'libelle_ecriture',
        'user_id',
        'annee_comptable',
        'mois_comptable',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'date_de_valeur' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
