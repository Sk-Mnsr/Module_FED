<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PodProduitEcran extends Model
{
    /** Profils CDC → rôles applicatifs (slugs). */
    public const PROFILS = [
        'agence' => [
            'label' => 'Agence',
            'roles' => ['ops', 'pod', 'ca', 'cc', 'caissier'],
        ],
        'back_office' => [
            'label' => 'Back-office',
            'roles' => ['ops', 'pod'],
        ],
        'administration' => [
            'label' => 'Administration',
            'roles' => ['it', 'pod', 'administrateur'],
        ],
        'finance' => [
            'label' => 'Finance / IT',
            'roles' => ['finance', 'daf', 'controle_de_gestion', 'it'],
        ],
        'responsable' => [
            'label' => 'Responsable',
            'roles' => ['daf', 'controle_de_gestion', 'n_plus_1'],
        ],
    ];

    /** Modèles d’écrans CDC (création rapide). */
    public const TEMPLATES = [
        ['code' => 'ECR01', 'libelle' => 'Informations de base', 'description' => 'Saisie des informations générales', 'profils' => ['agence']],
        ['code' => 'ECR02', 'libelle' => 'Paramètres produit', 'description' => 'Paramétrage des caractéristiques', 'profils' => ['back_office']],
        ['code' => 'ECR03', 'libelle' => 'Constantes', 'description' => 'Définition des paramètres du produit', 'profils' => ['administration']],
        ['code' => 'ECR04', 'libelle' => 'Comptabilisation', 'description' => 'Définition du traitement comptable', 'profils' => ['finance']],
        ['code' => 'ECR05', 'libelle' => 'Validation', 'description' => 'Contrôle et validation du produit', 'profils' => ['responsable']],
    ];

    protected $table = 'pod_produit_ecrans';

    protected $fillable = [
        'pod_produit_id',
        'code',
        'libelle',
        'description',
        'profils',
        'sort_order',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'profils' => 'array',
            'sort_order' => 'integer',
            'actif' => 'boolean',
        ];
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(PodProduit::class, 'pod_produit_id');
    }

    public function champs(): HasMany
    {
        return $this->hasMany(PodProduitChamp::class, 'pod_produit_ecran_id')->orderBy('sort_order');
    }

    /**
     * @return list<string>
     */
    public function roleSlugsAutorises(): array
    {
        $profils = is_array($this->profils) ? $this->profils : [];
        if ($profils === []) {
            return [];
        }

        $roles = [];
        foreach ($profils as $key) {
            $roles = array_merge($roles, self::PROFILS[$key]['roles'] ?? []);
        }

        return array_values(array_unique($roles));
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function profilOptions(): array
    {
        $out = [];
        foreach (self::PROFILS as $key => $meta) {
            $out[] = ['key' => $key, 'label' => $meta['label']];
        }

        return $out;
    }
}
