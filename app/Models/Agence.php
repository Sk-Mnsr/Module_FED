<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agence extends Model
{
    use HasFactory;

    /** Code Flex de l’entité siège (rattachée aux directions / services, sans chef d’agence métier). */
    public const CODE_SIEGE = '500';

    protected $fillable = [
        'nom',
        'code',
        'chef_agence_user_id',
        'zone_id',
    ];

    public function chefAgence()
    {
        return $this->belongsTo(User::class, 'chef_agence_user_id');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function isSiege(): bool
    {
        return (string) $this->code === self::CODE_SIEGE;
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
