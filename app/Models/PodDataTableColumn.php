<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PodDataTableColumn extends Model
{
    protected $table = 'pod_data_table_columns';

    protected $fillable = [
        'pod_data_table_id',
        'code',
        'libelle',
        'actif',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(PodDataTable::class, 'pod_data_table_id');
    }
}
