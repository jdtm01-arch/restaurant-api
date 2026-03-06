<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Role;
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];    

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
        ];
    }

    public function restaurants()
    {
        return $this->belongsToMany(Restaurant::class)
                    ->withPivot('role_id')
                    ->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | Role Helpers (Multi-tenant)
    |--------------------------------------------------------------------------
    */

    public function roleForRestaurant(int $restaurantId): ?Role
    {
        $relation = $this->restaurants()
            ->where('restaurant_id', $restaurantId)
            ->first();

        if (! $relation) {
            return null;
        }

        return Role::find($relation->pivot->role_id);
    }

    public function hasRoleInRestaurant(string $roleSlug, int $restaurantId): bool
    {
        $role = $this->roleForRestaurant($restaurantId);

        return $role && $role->slug === $roleSlug;
    }
}
