<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemandeApprovisionnement extends Model
{
    protected $table = 'demandes_approvisionnement';

    protected $fillable = [
        'user_id',
        'status',
        'motif',
        'date_demande',
        'date_traitement',
        'traitee_par',
    ];

    protected $casts = [
        'date_demande' => 'datetime',
        'date_traitement' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(DemandeApprovisionnementItem::class, 'demande_id');
    }

    public function traitee_par_user()
    {
        return $this->belongsTo(User::class, 'traitee_par');
    }
}
