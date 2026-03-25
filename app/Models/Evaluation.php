<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evaluation extends Model
{
    protected $fillable = [
        'offre_id', 'user_id', 'critere_appel_offre_id', 'note', 'commentaire'
    ];

    public function offre(): BelongsTo
    {
        return $this->belongsTo(Offre::class);
    }

    public function membre(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function critere(): BelongsTo
    {
        return $this->belongsTo(CritereAppelOffre::class, 'critere_appel_offre_id');
    }
}
