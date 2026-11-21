<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    public const ROLE_PATIENT = 'patient';
    public const ROLE_ADMIN = 'admin';

    protected $fillable = ['name', 'email', 'phone', 'password', 'preferred_language', 'is_active', 'last_login_at'];

    protected $hidden = ['password', 'remember_token'];

    /** Mirrors the DB defaults so freshly created (not reloaded) models behave the same. */
    protected $attributes = ['is_active' => true, 'preferred_language' => 'en'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function patient(): HasOne
    {
        return $this->hasOne(Patient::class);
    }

    public function hasRole(string ...$names): bool
    {
        $roles = $this->relationLoaded('roles') ? $this->roles : $this->roles()->get();

        return $roles->whereIn('name', $names)->isNotEmpty();
    }

    public function assignRole(string $name): void
    {
        $role = Role::firstOrCreate(['name' => $name], ['label' => ucfirst($name)]);
        $this->roles()->syncWithoutDetaching([$role->id]);
        $this->unsetRelation('roles');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }
}
