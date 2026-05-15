<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zone extends Model
{
    protected $fillable = [
        'nom',
        'code',
    ];

    public function responsables(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'zone_user');
    }

    public function agences(): HasMany
    {
        return $this->hasMany(Agence::class);
    }
}
