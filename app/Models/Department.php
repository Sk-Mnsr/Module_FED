<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'manager_profile_id',
    ];

    public function manager()
    {
        return $this->belongsTo(Profil::class, 'manager_profile_id');
    }

    public function profiles()
    {
        return $this->hasMany(Profil::class);
    }

    public function budgets()
    {
        return $this->hasMany(Budget::class);
    }
}
