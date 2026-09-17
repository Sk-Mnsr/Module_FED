<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OdClasseur extends Model
{
    use SoftDeletes;

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
        'controle_at',
        'controle_by_user_id',
        'controle_anomalie_at',
        'controle_anomalie_motif',
        'controle_anomalie_by_user_id',
        'controle_anomalie_ack_at',
        'integration_status_code',
        'rejection_motif',
        'rejected_by_user_id',
        'rejected_at',
        'piece_pdf_path',
        'fichier_integration_path',
        'fichier_integration_original_name',
        'deleted_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'date_valeur' => 'date',
            'integrated_at' => 'datetime',
            'validated_at' => 'datetime',
            'archive_date' => 'date',
            'archived_at' => 'datetime',
            'controle_at' => 'datetime',
            'controle_anomalie_at' => 'datetime',
            'controle_anomalie_ack_at' => 'datetime',
            'rejected_at' => 'datetime',
            'deleted_at' => 'datetime',
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

    public function isControle(): bool
    {
        return $this->controle_at !== null;
    }

    public function hasControleAnomalie(): bool
    {
        return $this->controle_anomalie_at !== null;
    }

    /** Anomalie encore à traiter côté maker (notification « Pièces à corriger »). */
    public function hasControleAnomaliePending(): bool
    {
        return $this->hasControleAnomalie() && $this->controle_anomalie_ack_at === null;
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

    public function controleBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'controle_by_user_id');
    }

    public function controleAnomalieBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'controle_anomalie_by_user_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
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

    public function canBeRejectedBy(User $user): bool
    {
        return $this->canBeValidatedBy($user);
    }

    /**
     * Suppression : brouillon (créateur / admin) ; en attente : admin ;
     * archivé : SuperAdmin uniquement (mise en corbeille).
     */
    public function canBeDeletedBy(User $user): bool
    {
        if ($this->isIntegre()) {
            return $user->isSuperAdmin();
        }

        if ($this->isBrouillon()) {
            return (int) $this->user_id === (int) $user->id
                || $user->isSuperAdmin()
                || $user->hasRole('it')
                || $user->hasRole('admin');
        }

        if ($this->isAttenteValidation()) {
            return $user->isSuperAdmin()
                || $user->hasRole('it')
                || $user->hasRole('admin');
        }

        return false;
    }

    /**
     * Ajout de pièces justificatives : brouillon, attente, ou déjà archivée.
     */
    public function canAddJustificatifsBy(User $user): bool
    {
        if (! $this->isBrouillon() && ! $this->isAttenteValidation() && ! $this->isIntegre()) {
            return false;
        }

        return $this->isMakerOrAdmin($user);
    }

    /**
     * Suppression de justificatifs : uniquement avant archivage (brouillon / attente).
     */
    public function canManageJustificatifsBy(User $user): bool
    {
        if ($this->isIntegre()) {
            return false;
        }

        if (! $this->isBrouillon() && ! $this->isAttenteValidation()) {
            return false;
        }

        return $this->isMakerOrAdmin($user);
    }

    private function isMakerOrAdmin(User $user): bool
    {
        $isMaker = (int) $this->user_id === (int) $user->id
            || (int) ($this->integrated_by_user_id ?? 0) === (int) $user->id;

        return $isMaker
            || $user->isSuperAdmin()
            || $user->hasRole('it')
            || $user->hasRole('admin');
    }
}
