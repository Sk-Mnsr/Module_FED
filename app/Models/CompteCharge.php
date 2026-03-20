<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompteCharge extends Model
{
    protected $fillable = [
        'nom',
        'code_agence',
        'code_gl',
    ];
}
