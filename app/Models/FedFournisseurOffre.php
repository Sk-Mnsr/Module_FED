<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FedFournisseurOffre extends Model
{
    protected $fillable = [
        'fed_id',
        'fed_item_id',
        'fournisseur',
        'fournisseur_id',
        'rapport_qualite_prix',
        'delais_livraison',
        'garanties_offertes',
        'conformite_specifications',
        'conformite_reglementaire',
        'acompte_requis',
        'pourcentage_acompte',
        'conditions_paiement',
        'prix_unitaire',
        'delai',
        'remarques',
        'ordre',
    ];

    protected $casts = [
        'prix_unitaire' => 'decimal:2',
        'pourcentage_acompte' => 'decimal:2',
    ];

    public function fed()
    {
        return $this->belongsTo(Fed::class);
    }

    public function attachments()
    {
        return $this->hasMany(FedFournisseurOffreAttachment::class);
    }

    public function fedItem()
    {
        return $this->belongsTo(FedItem::class);
    }

    public function fournisseur_relation()
    {
        return $this->belongsTo(Fournisseur::class, 'fournisseur_id');
    }
}
