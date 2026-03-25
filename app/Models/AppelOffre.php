<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany; // Added for fournisseurs relation

class AppelOffre extends Model
{
    protected $fillable = [
        'reference', 'objet', 'description', 'date_lancement', 
        'date_limite_soumission', 'statut', 'type_publication', 'creator_id',
        'dao_path', // Added
        'cahier_charges_path', // Added
        'cle_ouverture_hash', 'is_plis_ouverts',
    ];

    protected $casts = [
        'date_lancement' => 'datetime', // Changed from 'date' to 'datetime'
        'date_limite_soumission' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function criteres(): HasMany // Type hint kept as per instruction to avoid unrelated edits
    {
        return $this->hasMany(CritereAppelOffre::class);
    }

    public function offres(): HasMany // Type hint kept as per instruction to avoid unrelated edits
    {
        return $this->hasMany(Offre::class);
    }

    public function comite(): HasOne // Type hint kept as per instruction to avoid unrelated edits
    {
        return $this->hasOne(Comite::class);
    }

    // Added the fournisseurs relation
    public function fournisseurs(): BelongsToMany
    {
        return $this->belongsToMany(Fournisseur::class, 'appel_offre_fournisseur')->withTimestamps();
    }
}
