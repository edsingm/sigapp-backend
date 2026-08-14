<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant;

use App\Models\Tenant\Proprietario;
use PHPUnit\Framework\TestCase;

class ProprietarioCpfCnpjFormattingTest extends TestCase
{
    public function test_formats_alphanumeric_cnpj_without_discarding_letters(): void
    {
        $proprietario = new Proprietario([
            'cpf_cnpj' => '12abc34501de35',
            'tipo_pessoa' => Proprietario::TIPO_JURIDICA,
        ]);

        self::assertSame('**.***.***/****-35', $proprietario->cpf_cnpj_formatado);
        self::assertSame('**.***.***/****-35', Proprietario::maskTaxId('12abc34501de35', Proprietario::TIPO_JURIDICA));
        self::assertSame('***.***.***-25', Proprietario::maskTaxId('52998224725', Proprietario::TIPO_FISICA));
    }
}
