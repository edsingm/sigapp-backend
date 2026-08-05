<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Common\RolesEnum;
use App\Enums\TenantBillingProfileType;
use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Throwable;

class TenantBillingProfileService
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
        private readonly BrazilianTaxIdValidator $taxIdValidator,
        private readonly StripeCheckoutService $stripeCheckout,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Tenant $tenant, array $data): Tenant
    {
        $address = is_array($data['address'] ?? null) ? $data['address'] : [];
        $type = TenantBillingProfileType::from((string) $data['type']);

        $tenant = $this->tenants->updateBillingProfile($tenant, [
            'billing_profile_type' => $type,
            'billing_tax_id' => BrazilianTaxIdValidator::normalizeTaxId((string) $data['tax_id']),
            'billing_legal_name' => (string) $data['legal_name'],
            'billing_trade_name' => $data['trade_name'] ?? null,
            'billing_email' => (string) $data['email'],
            'billing_phone' => (string) $data['phone'],
            'billing_postal_code' => (string) $address['postal_code'],
            'billing_street' => (string) $address['street'],
            'billing_number' => (string) $address['number'],
            'billing_complement' => $address['complement'] ?? null,
            'billing_neighborhood' => (string) $address['neighborhood'],
            'billing_city' => (string) $address['city'],
            'billing_state' => (string) $address['state'],
            'billing_country' => (string) $address['country'],
            'billing_municipal_registration' => $data['municipal_registration'] ?? null,
            'billing_tax_regime' => $data['tax_regime'] ?? null,
            'billing_profile_required' => true,
            'billing_profile_completed_at' => now(),
        ]);

        $this->synchronizeStripe($tenant);

        return $tenant;
    }

    /** @return array<string, mixed> */
    public function profile(Tenant $tenant): array
    {
        $type = $this->type($tenant);

        return [
            'type' => $type?->value,
            'tax_id' => $this->stringAttribute($tenant, 'billing_tax_id'),
            'legal_name' => $this->stringAttribute($tenant, 'billing_legal_name'),
            'trade_name' => $this->stringAttribute($tenant, 'billing_trade_name'),
            'email' => $this->stringAttribute($tenant, 'billing_email'),
            'phone' => $this->stringAttribute($tenant, 'billing_phone'),
            'address' => [
                'postal_code' => $this->stringAttribute($tenant, 'billing_postal_code'),
                'street' => $this->stringAttribute($tenant, 'billing_street'),
                'number' => $this->stringAttribute($tenant, 'billing_number'),
                'complement' => $this->stringAttribute($tenant, 'billing_complement'),
                'neighborhood' => $this->stringAttribute($tenant, 'billing_neighborhood'),
                'city' => $this->stringAttribute($tenant, 'billing_city'),
                'state' => $this->stringAttribute($tenant, 'billing_state'),
                'country' => $this->stringAttribute($tenant, 'billing_country') ?? 'BR',
            ],
            'municipal_registration' => $this->stringAttribute($tenant, 'billing_municipal_registration'),
            'tax_regime' => $this->stringAttribute($tenant, 'billing_tax_regime'),
            ...$this->summary($tenant),
        ];
    }

    /** @return array{status: string, required: bool, completed: bool, completed_at: ?string, missing_fields: list<string>, required_action: ?string} */
    public function summary(Tenant $tenant): array
    {
        $required = (bool) $tenant->getAttribute('billing_profile_required');
        $missingFields = $this->missingFields($tenant);
        $completedAt = $tenant->getAttribute('billing_profile_completed_at');
        $completed = $missingFields === [] && $completedAt !== null;

        return [
            'status' => ! $required ? 'exempt' : ($completed ? 'complete' : 'incomplete'),
            'required' => $required,
            'completed' => $completed,
            'completed_at' => $completedAt instanceof CarbonInterface
                ? $completedAt->toIso8601String()
                : null,
            'missing_fields' => $required ? $missingFields : [],
            'required_action' => $required && ! $completed
                ? 'complete_tenant_billing_profile'
                : null,
        ];
    }

    /** @return array{status: string, required: bool, completed: bool, completed_at: ?string, missing_fields: list<string>, required_action: ?string, can_complete: bool} */
    public function summaryForUser(Tenant $tenant, ?Authenticatable $user): array
    {
        return [
            ...$this->summary($tenant),
            'can_complete' => $user instanceof User && $user->hasRole(RolesEnum::ADMIN->value),
        ];
    }

    public function requiresCompletion(Tenant $tenant): bool
    {
        $summary = $this->summary($tenant);

        return $summary['required'] && ! $summary['completed'];
    }

    /** @return list<string> */
    public function missingFields(Tenant $tenant): array
    {
        $missing = [];
        $type = $this->type($tenant);

        if ($type === null) {
            $missing[] = 'type';
        }

        $taxId = $this->stringAttribute($tenant, 'billing_tax_id');
        if ($type === null || $taxId === null || ! $this->taxIdValidator->isValid($taxId, $type)) {
            $missing[] = 'tax_id';
        }

        if ($this->stringAttribute($tenant, 'billing_legal_name') === null) {
            $missing[] = 'legal_name';
        }

        if ($type === TenantBillingProfileType::PJ && $this->stringAttribute($tenant, 'billing_trade_name') === null) {
            $missing[] = 'trade_name';
        }

        if ($this->stringAttribute($tenant, 'billing_email') === null) {
            $missing[] = 'email';
        }

        if ($this->stringAttribute($tenant, 'billing_phone') === null) {
            $missing[] = 'phone';
        }

        $addressAttributes = [
            'billing_postal_code',
            'billing_street',
            'billing_number',
            'billing_neighborhood',
            'billing_city',
            'billing_state',
            'billing_country',
        ];

        foreach ($addressAttributes as $attribute) {
            if ($this->stringAttribute($tenant, $attribute) === null) {
                $missing[] = 'address';
                break;
            }
        }

        return $missing;
    }

    private function synchronizeStripe(Tenant $tenant): void
    {
        $stripeId = $this->stringAttribute($tenant, 'stripe_id');
        if ($stripeId === null) {
            return;
        }

        try {
            $this->stripeCheckout->updateCustomerBillingProfile($stripeId, $this->profile($tenant));
        } catch (Throwable $exception) {
            Log::warning('Falha ao sincronizar perfil fiscal do tenant com o Stripe.', [
                'tenant_id' => $tenant->getKey(),
                'error_class' => $exception::class,
            ]);
        }
    }

    private function type(Tenant $tenant): ?TenantBillingProfileType
    {
        $value = $tenant->getAttribute('billing_profile_type');

        if ($value instanceof TenantBillingProfileType) {
            return $value;
        }

        return is_string($value) ? TenantBillingProfileType::tryFrom($value) : null;
    }

    private function stringAttribute(Tenant $tenant, string $attribute): ?string
    {
        $value = $tenant->getAttribute($attribute);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
