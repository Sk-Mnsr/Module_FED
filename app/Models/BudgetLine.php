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
        // Champs global/entité
        'is_global',
        'global_line_id',
        'agence_id',
        'responsable',
        'article_id',
    ];

    protected $casts = [
        'montant_estime'   => 'decimal:2',
        'montant_consomme' => 'decimal:2',
        'montant_stock'    => 'decimal:2',
        'is_global'        => 'boolean',
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

    public function agence()
    {
        return $this->belongsTo(Agence::class);
    }

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    /** Ligne globale parente (pour les lignes entité) */
    public function globalLine()
    {
        return $this->belongsTo(BudgetLine::class, 'global_line_id');
    }

    /** Sous-lignes entité (pour les lignes globales) */
    public function entityLines()
    {
        return $this->hasMany(BudgetLine::class, 'global_line_id');
    }

    public function fedItems()
    {
        return $this->hasMany(FedItem::class);
    }

    /**
     * Génère le code d'une ligne budgétaire globale : TYPE-CATEGORIE-ARTICLE
     * Exemple : OPEX-MAT-CHRG
     */
    public static function generateGlobalCode(string $type, string $articleCode, string $categorieCode): string
    {
        $typePart    = strtoupper($type);
        $articlePart = strtoupper($articleCode);
        $catPart     = strtoupper($categorieCode);

        return "{$typePart}-{$catPart}-{$articlePart}";
    }

    /**
     * Génère le code d'une ligne budgétaire entité : AGENCE_TYPE-ARTICLE-NNN
     * Exemple : AG501_OPEX-MAT-001
     */
    public static function generateEntityCode(string $agenceCode, string $globalCode): string
    {
        return strtoupper($agenceCode) . '_' . $globalCode;
    }

    /**
     * Ancien générateur conservé pour la compatibilité (non utilisé pour les nouvelles lignes)
     */
    public static function generateCode(Budget $budget, ?string $type, ?int $sousCategorieDepenseId, ?int $excludeLineId = null): string
    {
        $deptCode   = $budget->department?->code ?? 'XXX';
        $typePart   = $type ? strtoupper($type) : 'XXX';
        $sousCatCode = 'XXX';
        if ($sousCategorieDepenseId) {
            $sousCat     = SousCategorieDepense::find($sousCategorieDepenseId);
            $sousCatCode = $sousCat?->code ?? 'XXX';
        }

        $query = static::where('budget_id', $budget->id)
            ->where('type', $type)
            ->where('sous_categorie_depense_id', $sousCategorieDepenseId);
        if ($excludeLineId) {
            $query->where('id', '!=', $excludeLineId);
        }
        $count  = $query->count();
        $numero = str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);

        return "{$deptCode} – {$typePart} – {$sousCatCode} – {$numero}";
    }
}
