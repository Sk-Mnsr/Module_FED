<?php

namespace App\Models;

use App\Support\ModuleAccess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Maravel\Models\AuthenticatableBase;

class User extends AuthenticatableBase
{
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'fonction',
        'email',
        'password',
        'profile',
        'activated',
        'password_change_required',
        'signature',
        'agence_id',
        'matricule',
        'department_id',
        'n_plus_1_user_id',
        'n_plus_2_user_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'signature',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    public $appends = [
        'has_signature',
    ];

    /**
     * Casts d'énumération pour le profil
     *
     * @var array
     */
    protected $enumCasts = [
        [
            'colum_name' => 'profile',
            'additional_column_name' => 'profile_fr',
            'choices' => [
                'admin' => 'Adminitrateur',
                'other' => 'Métier',
                'monetique' => 'Monétique',
            ],
        ],
        [
            'colum_name' => 'profile',
            'additional_column_name' => 'ability_rules',
            'choices' => [
                'admin' => [
                    [
                        'subject' => ['all'],
                        'action' => ['manage'],
                    ],
                ],
                'other' => [
                    [
                        'subject' => ['user'],
                        'action' => ['read'],
                    ],
                ],
                'monetique' => [
                    [
                        'subject' => ['user'],
                        'action' => ['read'],
                    ],
                ],
            ],
        ],
        [
            'colum_name' => 'activated',
            'additional_column_name' => 'activated_fr',
            'choices' => [
                1 => 'Oui',
                0 => 'Non',
            ],
        ],
        [
            'colum_name' => 'password_change_required',
            'additional_column_name' => 'password_change_required_fr',
            'choices' => [
                1 => 'Oui',
                0 => 'Non',
            ],
        ],

    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activated' => 'boolean',
            'password_change_required' => 'boolean',
        ];
    }

    /**
     * Indique si l'utilisateur a une signature enregistrée.
     */
    public function getHasSignatureAttribute(): bool
    {
        return ! empty($this->attributes['signature'] ?? null);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function nPlus1()
    {
        return $this->belongsTo(self::class, 'n_plus_1_user_id');
    }

    public function nPlus2()
    {
        return $this->belongsTo(self::class, 'n_plus_2_user_id');
    }

    public function subordonnes()
    {
        return $this->hasMany(self::class, 'n_plus_1_user_id');
    }

    /**
     * N+1 direct ou, à défaut, manager du département.
     */
    public function resolveNPlus1(): ?self
    {
        if ($this->n_plus_1_user_id !== null) {
            return $this->relationLoaded('nPlus1') ? $this->nPlus1 : $this->nPlus1()->first();
        }

        $department = $this->relationLoaded('department')
            ? $this->department
            : $this->department()->with('manager')->first();

        return $department?->manager;
    }

    public function agence()
    {
        return $this->belongsTo(Agence::class);
    }

    public function zones()
    {
        return $this->belongsToMany(Zone::class, 'zone_user');
    }

    /**
     * Agence dont cet utilisateur est chef (s’il existe une désignation sur la table agences).
     */
    public function agenceDirigee()
    {
        return $this->hasOne(Agence::class, 'chef_agence_user_id');
    }

    /**
     * Relation avec les rôles (many-to-many)
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_role', 'user_id', 'role_id');
    }

    public function hasRole(string $roleSlug): bool
    {
        return ModuleAccess::userHasAnyRole($this, [$roleSlug]);
    }

    /**
     * Vérifie si l'utilisateur a au moins un des rôles spécifiés
     */
    public function hasAnyRole(array $roleSlugs): bool
    {
        return ModuleAccess::userHasAnyRole($this, $roleSlugs);
    }

    public function canAccessModule(string $module): bool
    {
        return ModuleAccess::userCanAccess($this, $module);
    }

    /**
     * @return list<string>
     */
    public function accessibleModules(): array
    {
        return ModuleAccess::accessibleModuleKeys($this);
    }

    /**
     * Vérifie si l'utilisateur est SuperAdmin (IT)
     */
    public function isSuperAdmin(): bool
    {
        if ($this->profile === 'admin') {
            return true;
        }

        return in_array('it', ModuleAccess::normalizedRoleSlugs($this), true);
    }

    /**
     * Récupère tous les rôles de l'utilisateur
     */
    public function getRoles(): Collection
    {
        return $this->roles;
    }

    public function feds()
    {
        return $this->hasMany(Fed::class, 'requester_id');
    }

    /** Classeurs OD (pièces comptables / intégrations). */
    public function odClasseurs()
    {
        return $this->hasMany(OdClasseur::class);
    }

    /**
     * Valeur à mettre dans la colonne « user_id » des exports / API Flex (IDFLEX).
     */
    public function flexComptaUserIdentifier(): string
    {
        $m = $this->matricule;

        if ($m === null || trim((string) $m) === '') {
            return '';
        }

        return trim((string) $m);
    }
}
