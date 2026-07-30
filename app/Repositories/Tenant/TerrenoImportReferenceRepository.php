<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Central\Cidade;
use App\Models\Tenant\CorretorExterno;
use App\Models\Tenant\Regional;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;

class TerrenoImportReferenceRepository
{
    public function userIdByEmail(string $email): ?int
    {
        $ids = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
            ->limit(2)
            ->pluck('id');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    public function corretorIdByEmail(string $email): ?int
    {
        $ids = CorretorExterno::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
            ->limit(2)
            ->pluck('id');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    public function regionalIdByName(string $name): ?int
    {
        $ids = Regional::query()
            ->whereRaw('LOWER(nome) = ?', [mb_strtolower($name)])
            ->limit(2)
            ->pluck('id');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    /** @return array{code: string, city: string, state_code: string}|null */
    public function cityByCode(string $code): ?array
    {
        $city = Cidade::query()->select(['code', 'city', 'state_code'])->where('code', $code)->first();

        return $city === null ? null : [
            'code' => (string) $city->code,
            'city' => (string) $city->city,
            'state_code' => (string) $city->state_code,
        ];
    }

    /** @return array{code: string, city: string, state_code: string}|null */
    public function cityByNameAndState(string $name, string $state): ?array
    {
        $cities = Cidade::query()
            ->select(['code', 'city', 'state_code'])
            ->whereRaw('LOWER(city) = ?', [mb_strtolower($name)])
            ->whereRaw('UPPER(state_code) = ?', [mb_strtoupper($state)])
            ->limit(2)
            ->get();

        if ($cities->count() !== 1) {
            return null;
        }

        $code = $cities->pluck('code')->first();

        return is_string($code) ? $this->cityByCode($code) : null;
    }

    public function terrainDuplicateExists(string $name, ?string $cityCode, ?string $address): bool
    {
        return Terreno::query()
            ->whereRaw('LOWER(TRIM(nome)) = ?', [mb_strtolower(trim($name))])
            ->where(function ($query) use ($cityCode): void {
                $cityCode === null
                    ? $query->whereNull('cidade_code')
                    : $query->where('cidade_code', $cityCode);
            })
            ->where(function ($query) use ($address): void {
                $address === null
                    ? $query->whereNull('endereco')
                    : $query->whereRaw('LOWER(TRIM(endereco)) = ?', [mb_strtolower(trim($address))]);
            })
            ->exists();
    }

    /** @return array{users: list<array{id: int, name: string, email: string}>, regionals: list<array{id: int, nome: string}>, corretores: list<array{id: int, nome: string, email: string}>} */
    public function templateReferences(): array
    {
        $users = User::query()->select(['id', 'name', 'email'])->orderBy('name')->limit(1000)->get()
            ->map(static fn (User $user): array => [
                'id' => $user->id,
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ])->values()->all();
        $regionals = Regional::query()->select(['id', 'nome'])->orderBy('nome')->limit(1000)->get()
            ->map(static fn (Regional $regional): array => [
                'id' => (int) $regional->getKey(),
                'nome' => (string) $regional->getAttribute('nome'),
            ])->values()->all();
        $corretores = CorretorExterno::query()->select(['id', 'nome', 'email'])->orderBy('nome')->limit(1000)->get()
            ->map(static fn (CorretorExterno $corretor): array => [
                'id' => (int) $corretor->getKey(),
                'nome' => (string) $corretor->getAttribute('nome'),
                'email' => (string) $corretor->getAttribute('email'),
            ])->values()->all();

        return [
            'users' => array_values($users),
            'regionals' => array_values($regionals),
            'corretores' => array_values($corretores),
        ];
    }
}
