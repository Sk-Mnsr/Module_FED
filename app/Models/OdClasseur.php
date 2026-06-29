<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OdClasseur extends Model
{
    public const STATUT_BROUILLON = 'brouillon';

    public const STATUT_ATTENTE_VALIDATION = 'attente_validation';

    public const STATUT_INTEGRE = 'integre';

    protected $table = 'od_classeurs';

    protected $fillable = [
        'user_id',
        'nom_classeur',
        'date_valeur',
        'numero_batch',
        'numero_piece',
        'statut',
        'integrated_at',
        'integrated_by_user_id',
        'assigned_checker_user_id',
        'validated_by_user_id',
        'validated_at',
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
            'validated_at' => 'datetime',
            'archive_date' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function isBrouillon(): bool
    {
        return $this->statut === self::STATUT_BROUILLON;
    }

    public function isAttenteValidation(): bool
    {
        return $this->statut === self::STATUT_ATTENTE_VALIDATION;
    }

    public function isIntegre(): bool
    {
        return $this->statut === self::STATUT_INTEGRE;
    }

    public function isEditable(): bool
    {
        return $this->isBrouillon();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function integratedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'integrated_by_user_id');
    }

    public function assignedChecker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_checker_user_id');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by_user_id');
    }

    public function pieces(): HasMany
    {
        return $this->hasMany(OdClasseurPiece::class, 'od_classeur_id')->orderBy('sort_order');
    }

    public function canBeIntegratedBy(User $user): bool
    {
        return $this->isBrouillon() && (int) $this->user_id === (int) $user->id;
    }

    public function canBeValidatedBy(User $user): bool
    {
        return $this->isAttenteValidation()
            && (int) $this->assigned_checker_user_id === (int) $user->id;
    }
}
