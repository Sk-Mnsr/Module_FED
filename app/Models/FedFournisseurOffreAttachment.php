<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FedFournisseurOffreAttachment extends Model
{
    protected $fillable = [
        'fed_fournisseur_offre_id',
        'original_name',
        'path',
        'mime_type',
        'size',
    ];

    public function offre()
    {
        return $this->belongsTo(FedFournisseurOffre::class, 'fed_fournisseur_offre_id');
    }
}
