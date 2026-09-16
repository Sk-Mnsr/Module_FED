<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PodProduitConstante extends Model
{
    public const MODE_SCHEMA = 'schema';

    public const MODE_SCRIPT = 'script';

    public const MODE_DEFAUT = 'defaut';

    public const MODE_CHAMP = 'champ';

    public const MODES = [
        self::MODE_SCHEMA,
        self::MODE_SCRIPT,
        self::MODE_DEFAUT,
        self::MODE_CHAMP,
    ];

    public const TYPES = ['compte', 'numerique', 'montant', 'texte', 'code'];

    protected $table = 'pod_produit_constantes';

    protected $fillable = [
        'pod_produit_id',
        'code',
        'libelle',
        'type',
        'mode',
        'valeur_reference',
        'obligatoire',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'obligatoire' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(PodProduit::class, 'pod_produit_id');
    }
}
