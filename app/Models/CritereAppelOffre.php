<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CritereAppelOffre extends Model
{
    protected $fillable = [
        'appel_offre_id', 'nom', 'ponderation', 'type', 'note_maximale'
    ];

    public function appelOffre(): BelongsTo
    {
        return $this->belongsTo(AppelOffre::class);
    }
}
