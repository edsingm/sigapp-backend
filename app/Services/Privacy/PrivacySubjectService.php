<?php

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Enums\Common\RolesEnum;
use App\Enums\TenantExportStatus;
use App\Enums\TenantExportType;
use App\Exceptions\InvalidAccountPasswordException;
use App\Exceptions\LastTenantAdminException;
use App\Jobs\GenerateSubjectPortabilityJob;
use App\Jobs\GenerateTenantPortabilityJob;
use App\Models\Central\Tenant;
use App\Models\Tenant\TenantExportGeneration;
use App\Models\Tenant\User;
use App\Repositories\Tenant\PrivacySubjectRepository;
use App\Repositories\Tenant\TenantExportGenerationRepository;
use App\Repositories\Tenant\UserRepository;
use App\Services\Auth\TenantUserDirectoryService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PrivacySubjectService
{
    public function __construct(
        private readonly LegalDocumentService $legalDocuments,
        private readonly PrivacySubjectRepository $subjects,
        private readonly TenantExportGenerationRepository $exports,
        private readonly UserRepository $users,
        private readonly TenantUserDirectoryService $directory,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function inventory(User $user): array
    {
        $user->loadMissing(['roles', 'department']);

        $catalog = $this->legalDocuments->catalog((int) $user->getKey());
        $tenant = tenancy()->tenant;

        return [
            'profile' => $this->profile($user),
            'roles' => $user->getRoleNames()->values()->all(),
            'counts' => [
                'terrenos_created' => $this->subjects->countTerrenosCreatedBy((int) $user->getKey()),
                'legal_acceptances' => $this->subjects->countLegalAcceptancesByUser((int) $user->getKey()),
            ],
            'legal' => $catalog,
            'subprocessors' => array_values((array) config('privacy.subprocessors', [])),
            'tenant' => $tenant instanceof Tenant ? [
                'id' => (string) $tenant->getKey(),
                'name' => (string) $tenant->getAttribute('name'),
                'slug' => (string) $tenant->getAttribute('slug'),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function exportPayload(User $user): array
    {
        return [
            'exported_at' => now()->toIso8601String(),
            ...$this->inventory($user),
        ];
    }

    public function queueExport(User $user): TenantExportGeneration
    {
        $reusable = $this->exports->findReusableSubjectPortability($user);
        if ($reusable instanceof TenantExportGeneration) {
            return $reusable;
        }

        $hours = (int) config('privacy.export_ttl_hours', 24);

        $generation = $this->exports->createOrFind(
            $user,
            (string) Str::uuid(),
            [
                'type' => TenantExportType::SUBJECT_PORTABILITY,
                'subject_id' => null,
                'filters' => [],
                'payload' => [],
                'status' => TenantExportStatus::QUEUED,
                'progress' => 0,
                'requested_at' => now(),
                'expires_at' => now()->addHours($hours),
            ],
        );

        if ($generation->wasRecentlyCreated) {
            GenerateSubjectPortabilityJob::dispatch($generation->id);
        }

        return $generation;
    }

    public function findExport(User $user, int $id): TenantExportGeneration
    {
        return $this->exports->findForUser($user, $id);
    }

    public function queueWorkspaceExportForCurrentTenant(): ?TenantExportGeneration
    {
        $actor = $this->users->first();

        return $actor instanceof User ? $this->queueWorkspaceExport($actor) : null;
    }

    public function queueWorkspaceExport(User $actor): TenantExportGeneration
    {
        $hours = (int) config('privacy.export_ttl_hours', 24);

        $generation = $this->exports->createOrFind(
            $actor,
            (string) Str::uuid(),
            [
                'type' => TenantExportType::TENANT_PORTABILITY,
                'subject_id' => null,
                'filters' => [],
                'payload' => [],
                'status' => TenantExportStatus::QUEUED,
                'progress' => 0,
                'requested_at' => now(),
                'expires_at' => now()->addHours($hours),
            ],
        );

        if ($generation->wasRecentlyCreated) {
            GenerateTenantPortabilityJob::dispatch($generation->id);
        }

        return $generation;
    }

    public function erase(User $user, string $password): User
    {
        if (! Hash::check($password, (string) $user->getAuthPassword())) {
            throw new InvalidAccountPasswordException;
        }

        if ($user->isAdmin() && $this->users->adminEligibleCount([RolesEnum::ADMIN->value]) <= 1) {
            throw new LastTenantAdminException;
        }

        $anonymized = $this->users->update($user, [
            'name' => 'Titular removido',
            'email' => sprintf('deleted-%d@privacy.invalid', (int) $user->getKey()),
            'password' => Str::password(32),
            'status' => 'Inactive',
            'remember_token' => Str::random(60),
        ]);

        $anonymized->syncRoles([]);
        $this->users->revokeAllTokens($anonymized);
        $this->directory->deleteUser($anonymized);

        return $anonymized;
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(User $user): array
    {
        $department = $user->department;

        return [
            'id' => (int) $user->getKey(),
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'locale' => $user->locale,
            'status' => $user->status,
            'timezone' => $user->timezone,
            'department' => $department?->name,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
