<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

#[Fillable(['name', 'email', 'password', 'is_admin'])]
#[Hidden(['password', 'remember_token'])]
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property bool $is_admin
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
            'password' => 'hashed',
        ];
    }
}
