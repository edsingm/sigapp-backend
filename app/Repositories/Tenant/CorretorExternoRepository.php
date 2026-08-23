<?php

namespace App\Repositories\Tenant;

use App\Encryption\TenantPiiBlindIndexer;
use App\Models\Tenant\CorretorExterno;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CorretorExternoRepository
{
    public function __construct(private readonly TenantPiiBlindIndexer $blindIndexer) {}

    public function findById(int|string $id): CorretorExterno
    {
        return CorretorExterno::findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CorretorExterno
    {
        return CorretorExterno::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CorretorExterno $corretor, array $data): CorretorExterno
    {
        $corretor->update($data);

        return $corretor;
    }

    public function delete(CorretorExterno $corretor): bool
    {
        return $corretor->delete() !== false;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, CorretorExterno>
     */
    public function paginate(int $perPage = 10, array $filters = []): LengthAwarePaginator
    {
        $query = CorretorExterno::query();

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $emailHash = $this->blindIndexer->email($search);
            $phoneHash = $this->blindIndexer->phone($search);

            $query->where(function ($query) use ($emailHash, $phoneHash, $search): void {
                $query->where('nome', 'like', "%{$search}%")
                    ->orWhere('creci', 'like', "%{$search}%")
                    ->orWhere('email_hash', $emailHash);

                if ($this->blindIndexer->normalizePhone($search) !== '') {
                    $query->orWhere('telefone_hash', $phoneHash);
                }
            });
        }

        $query->orderBy('nome', 'asc');

        return $query->paginate($perPage);
    }

    public function listForSelect(): Collection
    {
        return CorretorExterno::orderBy('nome', 'asc')
            ->get(['id', 'nome']);
    }

    public function emailExists(string $email, int|string|null $ignoreId = null): bool
    {
        $normalized = $this->blindIndexer->normalizeEmail($email);
        $hash = $this->blindIndexer->email($email);
        $query = CorretorExterno::query()
            ->where(function ($query) use ($hash, $normalized): void {
                $query->where('email_hash', $hash)
                    ->orWhere(function ($query) use ($normalized): void {
                        $query->whereNull('email_hash')
                            ->whereRaw('LOWER(email) = ?', [$normalized]);
                    });
            });

        if ($ignoreId !== null && $ignoreId !== '') {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            return true;
        }

        $found = false;
        CorretorExterno::query()->toBase()
            ->select(['id', 'email'])
            ->whereNull('email_hash')
            ->when(
                $ignoreId !== null && $ignoreId !== '',
                fn ($query) => $query->where('id', '!=', $ignoreId),
            )
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($email, &$found): bool {
                foreach ($rows as $row) {
                    if (is_string($row->email) && $this->blindIndexer->matchesStoredEmail($email, $row->email)) {
                        $found = true;

                        return false;
                    }
                }

                return true;
            });

        return $found;
    }
}
