<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OdClasseurPiece extends Model
{
    protected $table = 'od_classeur_pieces';

    protected $fillable = [
        'od_classeur_id',
        'description',
        'original_name',
        'storage_path',
        'size',
        'mime_type',
        'sort_order',
    ];

    public function classeur(): BelongsTo
    {
        return $this->belongsTo(OdClasseur::class, 'od_classeur_id');
    }
}
