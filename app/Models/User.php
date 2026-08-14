<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

#[Fillable(['name', 'email', 'password'])]
#[Hidden([
    'password',
    'remember_token',
    'admin_mfa_secret',
])]
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property bool $is_admin
 * @property bool $is_dpo
 * @property string|null $admin_mfa_secret
 * @property Carbon|null $admin_mfa_confirmed_at
 * @property int|null $admin_mfa_last_used_timestep
 * @property int $admin_mfa_version
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use CentralConnection, HasApiTokens, HasFactory, Notifiable;

    /**
     * Obtém os atributos que devem ser convertidos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_admin' => 'boolean',
            'is_dpo' => 'boolean',
            'password' => 'hashed',
            'admin_mfa_secret' => 'encrypted',
            'admin_mfa_confirmed_at' => 'datetime',
            'admin_mfa_last_used_timestep' => 'integer',
            'admin_mfa_version' => 'integer',
        ];
    }
}
