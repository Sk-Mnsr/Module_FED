<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fed extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'requester_id',
        'date',
        'demandeur',
        'department',
        'fonction',
        'category',
        'subcategory',
        'beneficiaire',
        'motive',
        'estimated_total',
        'priority',
        'status',
        'submitted_at',
        'requester_signature',
        'requester_signed_at',
        'n1_comment',
        'n1_avis',
        'n1_action_at',
        'n1_signature',
        'n1_signed_at',
        'achats_comment',
        'achats_action_at',
        'achats_signature',
        'achats_signed_at',
        'facilities_comment',
        'facilities_action_at',
        'facilities_signature',
        'facilities_signed_at',
        'cg_budget_status',
        'cg_comment',
        'cg_action_at',
        'daf_comment',
        'daf_action_at',
        'daf_signature',
        'daf_signed_at',
        'dga_comment',
        'dga_action_at',
        'dga_signature',
        'dga_signed_at',
        'offre_choisie_id',
        'numero_bon_commande',
        'date_bon_commande',
        'adresse_livraison',
        'n1_validated_by',
        'achats_validated_by',
        'facilities_validated_by',
        'daf_validated_by',
        'dga_validated_by',
        'cg_validated_by',
        'expert_opinion_requested',
        'expert_opinion_offre_id',
        'expert_opinion_comment',
        'expert_opinion_at',
    ];

    protected $casts = [
        'estimated_total' => 'decimal:2',
        'date' => 'date',
        'date_bon_commande' => 'date',
        'submitted_at' => 'datetime',
        'requester_signed_at' => 'datetime',
        'n1_action_at' => 'datetime',
        'n1_signed_at' => 'datetime',
        'achats_action_at' => 'datetime',
        'achats_signed_at' => 'datetime',
        'facilities_action_at' => 'datetime',
        'facilities_signed_at' => 'datetime',
        'cg_action_at' => 'datetime',
        'daf_action_at' => 'datetime',
        'daf_signed_at' => 'datetime',
        'dga_action_at' => 'datetime',
        'dga_signed_at' => 'datetime',
        'expert_opinion_at' => 'datetime',
    ];

    public const STATUS_PENDING_VALIDATION = 'pending_validation';
    public const STATUS_N1_NEEDS_INFO = 'n1_needs_info';
    public const STATUS_N1_REJECTED = 'n1_rejected';
    public const STATUS_N1_APPROVED = 'n1_approved';
    public const STATUS_ACHATS_NEEDS_INFO = 'achats_needs_info';
    public const STATUS_ACHATS_REJECTED = 'achats_rejected';
    public const STATUS_ACHATS_APPROVED = 'achats_approved';
    public const STATUS_FACILITIES_NEEDS_INFO = 'facilities_needs_info';
    public const STATUS_FACILITIES_REJECTED = 'facilities_rejected';
    public const STATUS_FACILITIES_APPROVED = 'facilities_approved';
    public const STATUS_CG_TREATED = 'cg_treated';
    public const STATUS_WAITING_DAF_RECLASS_APPROVAL = 'waiting_daf_reclass_approval';
    public const STATUS_DAF_REJECTED = 'daf_rejected';
    public const STATUS_DAF_APPROVED = 'daf_approved';
    public const STATUS_DGA_REJECTED = 'dga_rejected';
    public const STATUS_EXPERT_OPINION_PENDING = 'expert_opinion_pending';
    public const STATUS_EXPERT_OPINION_GIVEN = 'expert_opinion_given';
    public const STATUS_BON_DE_COMMANDE = 'bon_de_commande';

    public function fournisseurOffres()
    {
        return $this->hasMany(FedFournisseurOffre::class)->orderBy('ordre');
    }

    public function offreChoisie()
    {
        return $this->belongsTo(FedFournisseurOffre::class, 'offre_choisie_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function items()
    {
        return $this->hasMany(FedItem::class);
    }

    public function attachments()
    {
        return $this->hasMany(FedAttachment::class);
    }

    public function budgetLines()
    {
        return $this->belongsToMany(BudgetLine::class, 'fed_budget_line')->withTimestamps();
    }

    public function budgetLineHistories()
    {
        return $this->hasMany(BudgetLineHistory::class)->with(['user', 'fromLine', 'toLine'])->orderByDesc('created_at');
    }

    /** Accesseur pour compatibilité affichage : première ligne budgétaire */
    public function getBudgetLineAttribute(): ?object
    {
        $first = $this->budgetLines->first();

        return $first ? (object) ['code' => $first->code, 'label' => $first->label] : null;
    }

    public function isEditableByRequester(): bool
    {
        // Tant que la FED n'est pas validée (N+1) ou rejetée, le demandeur peut modifier
        if ($this->status === self::STATUS_N1_NEEDS_INFO) {
            return true;
        }
        if ($this->status === self::STATUS_PENDING_VALIDATION) {
            return true;
        }
        return false;
    }

    public static function generateCode(?string $department = null, ?string $date = null): string
    {
        $prefix = self::buildDepartmentPrefix($department);
        $dateStamp = $date ? date('dmY', strtotime($date)) : date('dmY');
        $base = "{$prefix}-{$dateStamp}-";

        $lastCode = self::where('code', 'like', "{$base}%")
            ->orderBy('code', 'desc')
            ->value('code');

        if ($lastCode) {
            $parts = explode('-', $lastCode);
            $lastNumber = isset($parts[2]) ? (int) $parts[2] : 0;
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        $formattedNumber = str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);

        return "{$base}{$formattedNumber}";
    }

    private static function buildDepartmentPrefix(?string $department): string
    {
        if (!$department) {
            return 'FED';
        }

        $letters = preg_replace('/[^A-Za-z]/', '', $department);
        $letters = strtoupper($letters);

        if ($letters === '') {
            return 'FED';
        }

        return substr($letters, 0, 3);
    }
}

