<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fournisseur extends Model
{
    protected $fillable = [
        'nom',
        'type',
        'categorie',
        'description',
        'contact_nom',
        'contact_telephone',
        'contact_email',
        'site_web',
        'adresse_physique',
        'compte_transit_paiement',
        'compte_avance_acompte',
        'compte_client_interne',
        'banque_id'
    ];

    /**
     * Obtenir la banque associée au fournisseur.
     */
    public function banque()
    {
        return $this->belongsTo(Banque::class);
    }
}
