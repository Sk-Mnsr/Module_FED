<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PodDataTableRow extends Model
{
    protected $table = 'pod_data_table_rows';

    protected $fillable = [
        'pod_data_table_id',
        'data',
        'actif',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'actif' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(PodDataTable::class, 'pod_data_table_id');
    }
}
