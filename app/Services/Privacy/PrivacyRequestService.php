<?php

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Enums\PrivacyRequestKind;
use App\Enums\PrivacyRequestStatus;
use App\Enums\PrivacySubjectType;
use App\Models\AuditLog;
use App\Models\Central\PrivacyRequest;
use App\Repositories\PrivacyRequestRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class PrivacyRequestService
{
    public function __construct(
        private readonly PrivacyRequestRepository $requests,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, PrivacyRequest>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->requests->paginate($filters, $perPage);
    }

    public function find(int $id): PrivacyRequest
    {
        return $this->requests->findById($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PrivacyRequest
    {
        $received = now();

        return $this->requests->create([
            'protocol' => $this->requests->nextProtocol((int) $received->format('Y')),
            'kind' => PrivacyRequestKind::from((string) $data['kind']),
            'subject_type' => PrivacySubjectType::from((string) $data['subject_type']),
            'subject_email' => Str::lower(trim((string) $data['subject_email'])),
            'tenant_id' => $data['tenant_id'] ?? null,
            'status' => PrivacyRequestStatus::OPEN,
            'received_at' => $received,
            'due_at' => $received->copy()->addDays(15),
            'notes' => $data['notes'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PrivacyRequest $request, array $data): PrivacyRequest
    {
        return $this->requests->update($request, $data);
    }

    /**
     * @return LengthAwarePaginator<int, AuditLog>
     */
    public function privilegedAccess(string $tenantId, int $perPage): LengthAwarePaginator
    {
        return $this->requests->privilegedAccessForTenant($tenantId, $perPage);
    }
}
