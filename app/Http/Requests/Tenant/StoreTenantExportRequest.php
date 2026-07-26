<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\TenantExportType;
use App\Enums\WorkflowStatus;
use App\Models\Central\Tenant;
use App\Services\PlanMatrixService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreTenantExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $type = TenantExportType::tryFrom((string) $this->input('type'));
        if ($type === null) {
            return $this->user() !== null;
        }

        if (! Gate::allows('export', $type->authorizableModel())) {
            return false;
        }

        if (! tenancy()->initialized) {
            return true;
        }

        $tenant = tenancy()->tenant;

        return $tenant instanceof Tenant
            && app(PlanMatrixService::class)->hasFeatureForTenant($tenant, $type->feature());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $type = TenantExportType::tryFrom((string) $this->input('type'));
        $requiresSubject = $type?->requiresSubject() ?? false;
        $acceptsFilters = $type?->acceptsFilters() ?? false;
        $acceptsPayload = $type?->acceptsPayload() ?? false;

        return [
            'idempotency_key' => ['required', 'uuid'],
            'type' => ['required', Rule::enum(TenantExportType::class)],
            'subject_id' => [
                Rule::requiredIf($requiresSubject),
                Rule::prohibitedIf($type !== null && ! $requiresSubject),
                'nullable',
                'integer',
                'min:1',
            ],
            'filters' => [
                Rule::prohibitedIf($type !== null && ! $acceptsFilters),
                'nullable',
                'array',
            ],
            'filters.nome' => ['nullable', 'string', 'max:255'],
            'filters.ufs' => ['nullable', 'array'],
            'filters.ufs.*' => ['string', 'size:2'],
            'filters.cidades' => ['nullable', 'array'],
            'filters.cidades.*' => ['string', 'max:255'],
            'filters.gestor_ids' => ['nullable', 'array'],
            'filters.gestor_ids.*' => ['integer'],
            'filters.corretor_ids' => ['nullable', 'array'],
            'filters.corretor_ids.*' => ['integer'],
            'filters.regional_ids' => ['nullable', 'array'],
            'filters.regional_ids.*' => ['integer'],
            'filters.data_inicio' => ['nullable', 'date'],
            'filters.data_fim' => ['nullable', 'date', 'after_or_equal:filters.data_inicio'],
            'filters.ano' => ['nullable', 'integer', 'digits:4'],
            'filters.date_field' => ['nullable', Rule::in([
                'created_at',
                'updated_at',
                'data_apresentacao',
                'data_negociacao',
                'data_contrato',
            ])],
            'filters.workflow_statuses' => ['nullable', 'array'],
            'filters.workflow_statuses.*' => ['string', Rule::in(WorkflowStatus::values())],
            'payload' => [
                Rule::prohibitedIf($type !== null && ! $acceptsPayload),
                'nullable',
                'array',
            ],
            'payload.status' => ['nullable', 'string', 'max:255'],
            'payload.observacoes' => ['nullable', 'string', 'max:5000'],
            'payload.checklist' => ['nullable', 'array'],
            'payload.responsavel' => ['nullable', 'string', 'max:255'],
            'payload.data' => ['nullable', 'date'],
        ];
    }
}
