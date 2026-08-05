<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationRun extends Model
{
    protected $fillable = [
        'partenaire_id',
        'partenaire_identifiant',
        'partenaire_nom',
        'date_debut',
        'date_fin',
        'mode',
        'taux_reussite',
        'reconcilies',
        'total',
        'taux_json',
        'summary_json',
        'excel_path',
        'excel_filename',
        'user_id',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'taux_reussite' => 'float',
            'taux_json' => 'array',
            'summary_json' => 'array',
        ];
    }

    public function partenaire(): BelongsTo
    {
        return $this->belongsTo(Partenaire::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getExcelUrlAttribute(): ?string
    {
        if (! filled($this->excel_path)) {
            return null;
        }

        return '/storage/'.ltrim((string) $this->excel_path, '/');
    }
}
