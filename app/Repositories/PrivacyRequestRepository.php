<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\AuditLog;
use App\Models\Central\PrivacyRequest;
use Illuminate\Pagination\LengthAwarePaginator;

class PrivacyRequestRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, PrivacyRequest>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = PrivacyRequest::query()->with(['assignee'])->orderByDesc('received_at')->orderByDesc('id');

        if (isset($filters['status']) && is_string($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['tenant_id']) && is_string($filters['tenant_id']) && $filters['tenant_id'] !== '') {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (isset($filters['subject_email']) && is_string($filters['subject_email']) && $filters['subject_email'] !== '') {
            $query->where('subject_email', $filters['subject_email']);
        }

        return $query->paginate($perPage);
    }

    public function findById(int $id): PrivacyRequest
    {
        return PrivacyRequest::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): PrivacyRequest
    {
        return PrivacyRequest::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(PrivacyRequest $request, array $attributes): PrivacyRequest
    {
        $request->update($attributes);

        return $request->refresh();
    }

    public function nextProtocol(int $year): string
    {
        $prefix = 'LGPD-'.$year.'-';
        $latest = PrivacyRequest::query()
            ->where('protocol', 'like', $prefix.'%')
            ->orderByDesc('protocol')
            ->value('protocol');

        $sequence = 1;
        if (is_string($latest)) {
            $sequence = ((int) substr($latest, -6)) + 1;
        }

        return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return LengthAwarePaginator<int, AuditLog>
     */
    public function privilegedAccessForTenant(string $tenantId, int $perPage): LengthAwarePaginator
    {
        return AuditLog::query()
            ->with('user')
            ->where('action', 'tenant.privileged_access')
            ->where('metadata->tenant_id', $tenantId)
            ->latest()
            ->paginate($perPage);
    }
}
