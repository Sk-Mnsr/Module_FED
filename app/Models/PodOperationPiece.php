<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PodOperationPiece extends Model
{
    protected $table = 'pod_operation_pieces';

    protected $fillable = [
        'pod_operation_id',
        'champ_code',
        'etape_index',
        'user_id',
        'path',
        'original_name',
        'mime',
        'size',
    ];

    protected function casts(): array
    {
        return [
            'etape_index' => 'integer',
            'size' => 'integer',
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
