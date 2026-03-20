<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RubriqueDepense extends Model
{
    use HasFactory;

    protected $table = 'rubrique_depenses';

    protected $fillable = ['libelle'];

    public static function addIfNew(?string $libelle): void
    {
        if (empty(trim($libelle ?? ''))) {
            return;
        }

        $trimmed = trim($libelle);
        static::firstOrCreate(
            ['libelle' => $trimmed],
            ['libelle' => $trimmed]
        );
    }
}
