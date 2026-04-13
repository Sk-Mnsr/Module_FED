<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemandeApprovisionnementItem extends Model
{
    protected $fillable = [
        'demande_id',
        'article_id',
        'designation',
        'quantite_demandee',
        'quantite_livree',
    ];

    public function demande()
    {
        return $this->belongsTo(DemandeApprovisionnement::class, 'demande_id');
    }

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}
