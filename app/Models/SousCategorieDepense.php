<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SousCategorieDepense extends Model
{
    use HasFactory;

    protected $table = 'sous_categorie_depenses';

    protected $fillable = [
        'categorie_depense_id',
        'sous_categorie',
        'code',
    ];

    public function categorieDepense()
    {
        return $this->belongsTo(CategorieDepense::class);
    }

    /**
     * Crée une sous-catégorie si elle n'existe pas, pour les suggestions futures.
     */
    public static function addIfNew(int $categorieDepenseId, ?string $sousCategorie): ?int
    {
        $trimmed = trim($sousCategorie ?? '');
        if ($trimmed === '') {
            return null;
        }

        $existing = static::where('categorie_depense_id', $categorieDepenseId)
            ->where('sous_categorie', $trimmed)
            ->first();

        if ($existing) {
            return $existing->id;
        }

        $code = static::generateCode($categorieDepenseId, $trimmed);
        $new = static::create([
            'categorie_depense_id' => $categorieDepenseId,
            'sous_categorie' => $trimmed,
            'code' => $code,
        ]);

        return $new->id;
    }

    private static function generateCode(int $categorieDepenseId, string $sousCategorie): string
    {
        $base = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $sousCategorie) ?: 'XXX', 0, 3)) ?: 'XXX';
        $code = $base;
        $n = 1;
        while (static::where('categorie_depense_id', $categorieDepenseId)->where('code', $code)->exists()) {
            $code = $base . $n;
            $n++;
        }
        return $code;
    }
}
