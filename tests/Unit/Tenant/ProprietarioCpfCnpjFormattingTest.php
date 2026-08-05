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

        self::assertSame('12.ABC.345/01DE-35', $proprietario->cpf_cnpj_formatado);
    }
}
