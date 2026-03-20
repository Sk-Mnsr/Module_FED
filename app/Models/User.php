<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Maravel\Models\AuthenticatableBase;

class User extends AuthenticatableBase
{
    use HasFactory, Notifiable, TwoFactorAuthenticatable, HasApiTokens;

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
			'additional_column_name' => "profile_fr",
			'choices' => [
				'admin' => "Adminitrateur",
				'other' => "Métier",
			]
		],
		[
			'colum_name' => 'profile',
			'additional_column_name' => "ability_rules",
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
			],
		],
		[
			'colum_name' => 'activated',
			'additional_column_name' => "activated_fr",
			'choices' => [
				1 => "Oui",
				0 => "Non",
			]
		],
		[
			'colum_name' => 'password_change_required',
			'additional_column_name' => "password_change_required_fr",
			'choices' => [
				1 => "Oui",
				0 => "Non",
			]
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
        return !empty($this->attributes['signature'] ?? null);
    }

    /**
     * Relation avec le profil (via email)
     */
    public function profil()
    {
        return $this->hasOne(Profil::class, 'email', 'email');
    }

    /**
     * Relation avec les rôles (many-to-many)
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_role', 'user_id', 'role_id');
    }

    /**
     * Vérifie si l'utilisateur a un rôle spécifique
     */
    public function hasRole(string $roleSlug): bool
    {
        return $this->roles()->where('slug', $roleSlug)->exists();
    }

    /**
     * Vérifie si l'utilisateur a au moins un des rôles spécifiés
     */
    public function hasAnyRole(array $roleSlugs): bool
    {
        return $this->roles()->whereIn('slug', $roleSlugs)->exists();
    }

    /**
     * Vérifie si l'utilisateur est SuperAdmin (IT)
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('it');
    }

    /**
     * Récupère tous les rôles de l'utilisateur
     */
    public function getRoles(): \Illuminate\Support\Collection
    {
        return $this->roles;
    }

    public function feds()
    {
        return $this->hasMany(Fed::class, 'requester_id');
    }
}
