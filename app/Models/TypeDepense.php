<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TypeDepense extends Model
{
    protected $table = 'type_depenses';

    protected $fillable = [
        'nom_depense',
        'compte_gl',
    ];
}
