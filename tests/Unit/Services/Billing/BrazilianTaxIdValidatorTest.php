<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Billing;

use App\Enums\TenantBillingProfileType;
use App\Services\Billing\BrazilianTaxIdValidator;
use PHPUnit\Framework\TestCase;

class BrazilianTaxIdValidatorTest extends TestCase
{
    private BrazilianTaxIdValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new BrazilianTaxIdValidator;
    }

    public function test_accepts_valid_masked_cpf_and_cnpj(): void
    {
        self::assertTrue($this->validator->isValid('529.982.247-25', TenantBillingProfileType::PF));
        self::assertTrue($this->validator->isValid('11.222.333/0001-81', TenantBillingProfileType::PJ));
        self::assertTrue($this->validator->isValid('12.ABC.345/01DE-35', TenantBillingProfileType::PJ));
        self::assertSame('12ABC34501DE35', BrazilianTaxIdValidator::normalizeTaxId('12.abc.345/01de-35'));
    }

    public function test_rejects_invalid_or_repeated_tax_ids(): void
    {
        self::assertFalse($this->validator->isValid('529.982.247-24', TenantBillingProfileType::PF));
        self::assertFalse($this->validator->isValid('111.111.111-11', TenantBillingProfileType::PF));
        self::assertFalse($this->validator->isValid('11.222.333/0001-80', TenantBillingProfileType::PJ));
        self::assertFalse($this->validator->isValid('00.000.000/0000-00', TenantBillingProfileType::PJ));
        self::assertFalse($this->validator->isValid('12.ABC.345/01DE-36', TenantBillingProfileType::PJ));
        self::assertFalse($this->validator->isValid('12.ABC.345/01DE-3A', TenantBillingProfileType::PJ));
    }

    public function test_rejects_document_incompatible_with_person_type(): void
    {
        self::assertFalse($this->validator->isValid('11.222.333/0001-81', TenantBillingProfileType::PF));
        self::assertFalse($this->validator->isValid('529.982.247-25', TenantBillingProfileType::PJ));
    }
}
