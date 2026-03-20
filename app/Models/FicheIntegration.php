<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FicheIntegration extends Model
{
    use HasFactory;

    protected $fillable = [
        'numero',
        'no_batch',
        'no_compte',
        'sens',
        'montant',
        'code_operation',
        'date_de_valeur',
        'code_agence',
        'libele_ecriture',
        'user_id',
        'annee_comptable',
        'mois_comptable',
        'montantAPayer',
        'account',
        'relicat',
        'restantAPayer',
        'statut',
    ];

    /**
     * Get the user that created the fiche integration.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
