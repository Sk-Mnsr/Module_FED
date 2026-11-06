<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PodOperationEtape extends Model
{
    public const ACTION_SOUMISE = 'soumise';

    public const ACTION_VALIDEE = 'validee';

    public const ACTION_SUSPENDUE = 'suspendue';

    public const ACTION_REPRISE = 'reprise';

    public const ACTION_REJETEE = 'rejetee';

    public const ACTION_ANNULEE = 'annulee';

    protected $table = 'pod_operation_etapes';

    protected $fillable = [
        'pod_operation_id',
        'etape_index',
        'ecran_code',
        'ecran_libelle',
        'action',
        'user_id',
        'commentaire',
    ];

    protected function casts(): array
    {
        return [
            'etape_index' => 'integer',
        ];
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(PodOperation::class, 'pod_operation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
