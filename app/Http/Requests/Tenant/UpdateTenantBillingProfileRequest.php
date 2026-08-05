<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Common\RolesEnum;
use App\Enums\TenantBillingProfileType;
use App\Models\Tenant\User;
use App\Services\Billing\BrazilianTaxIdValidator;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantBillingProfileRequest extends FormRequest
{
    private const BRAZILIAN_STATES = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS',
        'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC',
        'SP', 'SE', 'TO',
    ];

    public function authorize(): bool
    {
        $user = $this->user();

        return tenancy()->initialized
            && $user instanceof User
            && $user->hasRole(RolesEnum::ADMIN->value);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $type = TenantBillingProfileType::tryFrom((string) $this->input('type'));

        return [
            'type' => ['required', Rule::enum(TenantBillingProfileType::class)],
            'tax_id' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail) use ($type): void {
                    if (! is_string($value) || $type === null) {
                        return;
                    }

                    if (! app(BrazilianTaxIdValidator::class)->isValid($value, $type)) {
                        $fail(language()->t('INVALID_TAX_ID'));
                    }
                },
            ],
            'legal_name' => ['required', 'string', 'min:3', 'max:255'],
            'trade_name' => [
                Rule::requiredIf($type === TenantBillingProfileType::PJ),
                Rule::prohibitedIf($type === TenantBillingProfileType::PF),
                'nullable',
                'string',
                'max:255',
            ],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^(?:55)?[1-9][0-9][0-9]{8,9}$/'],
            'address' => ['required', 'array'],
            'address.postal_code' => ['required', 'string', 'digits:8'],
            'address.street' => ['required', 'string', 'max:255'],
            'address.number' => ['required', 'string', 'max:30'],
            'address.complement' => ['nullable', 'string', 'max:255'],
            'address.neighborhood' => ['required', 'string', 'max:255'],
            'address.city' => ['required', 'string', 'max:255'],
            'address.state' => ['required', 'string', Rule::in(self::BRAZILIAN_STATES)],
            'address.country' => ['required', 'string', Rule::in(['BR'])],
            'municipal_registration' => [
                Rule::prohibitedIf($type === TenantBillingProfileType::PF),
                'nullable',
                'string',
                'max:255',
            ],
            'tax_regime' => [
                Rule::prohibitedIf($type === TenantBillingProfileType::PF),
                'nullable',
                'string',
                'max:80',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $address = $this->input('address');
        $normalizedAddress = is_array($address) ? $address : [];

        if (array_key_exists('postal_code', $normalizedAddress) && is_string($normalizedAddress['postal_code'])) {
            $normalizedAddress['postal_code'] = BrazilianTaxIdValidator::digits($normalizedAddress['postal_code']);
        }

        if (array_key_exists('state', $normalizedAddress) && is_string($normalizedAddress['state'])) {
            $normalizedAddress['state'] = strtoupper(trim($normalizedAddress['state']));
        }

        $normalizedAddress['country'] = strtoupper(trim((string) ($normalizedAddress['country'] ?? 'BR')));

        $normalized = ['address' => $normalizedAddress];

        $taxId = $this->input('tax_id');
        if (is_string($taxId)) {
            $normalized['tax_id'] = BrazilianTaxIdValidator::normalizeTaxId($taxId);
        }

        $phone = $this->input('phone');
        if (is_string($phone)) {
            $normalized['phone'] = BrazilianTaxIdValidator::digits($phone);
        }

        $type = $this->input('type');
        if (is_string($type)) {
            $normalized['type'] = strtolower(trim($type));
        }

        $this->merge($normalized);
    }
}
