<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TypologieDepense extends Model
{
    use HasFactory;

    protected $table = 'typologie_depenses';

    protected $fillable = [
        'type',
        'libelle',
        'description',
    ];
}
