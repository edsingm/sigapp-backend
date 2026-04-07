<?php

namespace App\Support;

use App\Enums\UserType;
use App\Models\User as CentralUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User;

/**
 * Wrapper sobre o usuário autenticado que expõe contexto
 * de tipo (ADMIN ou TENANT) além de delegar todos os
 * atributos e métodos ao model subjacente.
 *
 * @mixin User
 */
class UserContext
{
    public function __construct(private readonly Authenticatable $user) {}

    /**
     * Retorna o tipo do usuário autenticado.
     *
     * UserType::SIGAPP → usuário da aplicação central (App\Models\User com is_admin)
     * UserType::TENANT → usuário de um tenant       (App\Models\Tenant\User)
     */
    public function getType(): UserType
    {
        return $this->user instanceof CentralUser
            ? UserType::SIGAPP
            : UserType::TENANT;
    }

    /**
     * Retorna o model de usuário subjacente.
     */
    public function getUser(): Authenticatable
    {
        return $this->user;
    }

    /** @param mixed $value */
    public function __set(string $name, $value): void
    {
        $this->user->{$name} = $value;
    }

    public function __get(string $name): mixed
    {
        return $this->user->{$name};
    }

    public function __isset(string $name): bool
    {
        return isset($this->user->{$name});
    }

    /** @param array<mixed> $arguments */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->user->{$name}(...$arguments);
    }
}
