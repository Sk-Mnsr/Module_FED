<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Comite extends Model
{
    protected $fillable = [
        'appel_offre_id', 'nom', 'statut'
    ];

    public function appelOffre(): BelongsTo
    {
        return $this->belongsTo(AppelOffre::class);
    }

    public function membres(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }
}
