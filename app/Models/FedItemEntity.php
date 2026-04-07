<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FedItemEntity extends Model
{
    use HasFactory;

    protected $fillable = [
        'fed_item_id',
        'budget_line_id',
        'quantity',
    ];

    public function fedItem()
    {
        return $this->belongsTo(FedItem::class);
    }

    public function budgetLine()
    {
        return $this->belongsTo(BudgetLine::class);
    }
}
