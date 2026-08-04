<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\PerfilFinanciamento;
use PHPUnit\Framework\TestCase;

class PerfilFinanciamentoTest extends TestCase
{
    public function test_cef_legado_mantem_fluxo_de_apoio_a_producao(): void
    {
        $this->assertTrue(PerfilFinanciamento::CEF->isApoioProducao());
        $this->assertTrue(PerfilFinanciamento::APOIO_PRODUCAO->isCef());
        $this->assertSame(['apoio_producao', 'cef'], PerfilFinanciamento::APOIO_PRODUCAO->perfisPremissas());
    }

    public function test_apenas_apoio_e_plano_empresario_permitem_financiamento_pj(): void
    {
        $this->assertTrue(PerfilFinanciamento::APOIO_PRODUCAO->permiteFinanciamentoPj());
        $this->assertTrue(PerfilFinanciamento::PLANO_EMPRESARIO->permiteFinanciamentoPj());
        $this->assertFalse(PerfilFinanciamento::PROPRIO->permiteFinanciamentoPj());
        $this->assertFalse(PerfilFinanciamento::ALOCACAO_RECURSOS->permiteFinanciamentoPj());
    }
}
