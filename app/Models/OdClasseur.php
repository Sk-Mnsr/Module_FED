<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OdClasseur extends Model
{
    protected $table = 'od_classeurs';

    protected $fillable = [
        'user_id',
        'nom_classeur',
        'date_valeur',
        'numero_batch',
        'numero_piece',
        'statut',
        'integrated_at',
        'archive_date',
        'archived_at',
        'integration_status_code',
        'piece_pdf_path',
        'fichier_integration_path',
        'fichier_integration_original_name',
    ];

    protected function casts(): array
    {
        return [
            'date_valeur' => 'date',
            'integrated_at' => 'datetime',
            'archive_date' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function isIntegre(): bool
    {
        return $this->statut === 'integre';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pieces(): HasMany
    {
        return $this->hasMany(OdClasseurPiece::class, 'od_classeur_id')->orderBy('sort_order');
    }
}
