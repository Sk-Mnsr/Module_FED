<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offre extends Model
{
    protected $fillable = [
        'appel_offre_id', 'fournisseur_id', 'user_id', 'nom_fournisseur', 'email_fournisseur', 
        'date_soumission', 'note_technique', 'note_financiere', 'note_totale', 
        'classement', 'statut', 'montant', 'details_financiers', 'commentaires',
        'rccm_path',
        'ninea_path',
        'fiche_technique_path',
        'references_path',
        'contact_nom',
        'contact_telephone',
    ];

    protected $casts = [
        'date_soumission' => 'datetime',
        'details_financiers' => 'array',
    ];

    public function appelOffre(): BelongsTo
    {
        return $this->belongsTo(AppelOffre::class);
    }

    public function fournisseurInterne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }
}
