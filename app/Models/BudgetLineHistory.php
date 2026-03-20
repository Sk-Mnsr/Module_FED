<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetLineHistory extends Model
{
    protected $fillable = [
        'fed_id',
        'user_id',
        'from_line_id',
        'to_line_id',
        'action',
        'montant_transfere',
        'from_montant_before',
        'from_montant_after',
        'to_montant_before',
        'to_montant_after',
        'note',
        'status',
    ];

    protected $casts = [
        'montant_transfere'   => 'decimal:2',
        'from_montant_before' => 'decimal:2',
        'from_montant_after'  => 'decimal:2',
        'to_montant_before'   => 'decimal:2',
        'to_montant_after'    => 'decimal:2',
    ];

    public function fed()
    {
        return $this->belongsTo(Fed::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fromLine()
    {
        return $this->belongsTo(BudgetLine::class, 'from_line_id');
    }

    public function toLine()
    {
        return $this->belongsTo(BudgetLine::class, 'to_line_id');
    }
}
