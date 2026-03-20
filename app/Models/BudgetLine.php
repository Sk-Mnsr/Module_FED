<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BudgetLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_id',
        'code',
        'label',
        'type',
        'categorie_depense_id',
        'sous_categorie_depense_id',
        'rubrique',
        'sous_rubrique',
        'montant_estime',
        'montant_consomme',
        'montant_stock',
        'date_souhaitee_execution',
        'justification',
        'compte_gl',
        'is_reclassified',
    ];

    protected $casts = [
        'montant_estime' => 'decimal:2',
        'montant_consomme' => 'decimal:2',
        'montant_stock' => 'decimal:2',
    ];

    public function budget()
    {
        return $this->belongsTo(Budget::class);
    }

    public function histories()
    {
        return $this->hasMany(BudgetLineHistory::class, 'from_line_id')->orderByDesc('created_at');
    }

    public function categorieDepense()
    {
        return $this->belongsTo(CategorieDepense::class);
    }

    public function sousCategorieDepense()
    {
        return $this->belongsTo(SousCategorieDepense::class);
    }

    /**
     * Génère le code ligne budgétaire : [DÉPARTEMENT] – [TYPE] – [SOUS-CATÉGORIE] – [NUMÉRO]
     * Exemple : IT – OPEX – ELC – 001
     */
    public static function generateCode(Budget $budget, ?string $type, ?int $sousCategorieDepenseId, ?int $excludeLineId = null): string
    {
        $deptCode = $budget->department?->code ?? 'XXX';
        $typePart = $type ? strtoupper($type) : 'XXX';
        $sousCatCode = 'XXX';
        if ($sousCategorieDepenseId) {
            $sousCat = SousCategorieDepense::find($sousCategorieDepenseId);
            $sousCatCode = $sousCat?->code ?? 'XXX';
        }

        $query = static::where('budget_id', $budget->id)
            ->where('type', $type)
            ->where('sous_categorie_depense_id', $sousCategorieDepenseId);
        if ($excludeLineId) {
            $query->where('id', '!=', $excludeLineId);
        }
        $count = $query->count();

        $numero = str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);

        return "{$deptCode} – {$typePart} – {$sousCatCode} – {$numero}";
    }

    public function fedItems()
    {
        return $this->hasMany(FedItem::class);
    }
}
