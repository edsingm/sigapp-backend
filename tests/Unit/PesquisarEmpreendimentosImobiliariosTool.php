<?php

namespace Tests\Unit;

use App\Services\Ai\Tools\AiMercadoImobiliarioService;
use App\Services\Ai\Tools\PesquisarEmpreendimentosImobiliariosTool as ToolUnderTest;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Mockery;
use Tests\TestCase;

class PesquisarEmpreendimentosImobiliariosTool extends TestCase
{
    public function test_implementa_contrato_tool(): void
    {
        $service = Mockery::mock(AiMercadoImobiliarioService::class);
        $tool = new ToolUnderTest($service);

        $this->assertInstanceOf(Tool::class, $tool);
    }

    public function test_tem_descricao(): void
    {
        $service = Mockery::mock(AiMercadoImobiliarioService::class);
        $tool = new ToolUnderTest($service);

        $this->assertNotEmpty($tool->description());
    }

    public function test_retorna_erro_com_cidade_vazia(): void
    {
        $service = Mockery::mock(AiMercadoImobiliarioService::class);
        $tool = new ToolUnderTest($service);

        $result = $tool->handle(new Request([]));

        $this->assertStringContainsString('cidade', (string) $result);
    }

    public function test_retorna_json_com_cidade_valida(): void
    {
        $service = Mockery::mock(AiMercadoImobiliarioService::class);
        $service->shouldReceive('pesquisar')
            ->once()
            ->with('Campinas', 'SP', 5)
            ->andReturn([
                'cidade_pesquisada' => 'Campinas',
                'estado' => 'SP',
                'periodo_anos' => 5,
                'total_encontrados' => 0,
                'fontes_consultadas' => [],
                'erros_de_consulta' => null,
                'empreendimentos' => [],
            ]);

        $tool = new ToolUnderTest($service);
        $result = $tool->handle(new Request(['cidade' => 'Campinas', 'estado' => 'SP']));

        $decoded = json_decode((string) $result, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('empreendimentos', $decoded);
        $this->assertSame('Campinas', $decoded['cidade_pesquisada']);
    }

    public function test_limita_anos_entre_1_e_10(): void
    {
        $service = Mockery::mock(AiMercadoImobiliarioService::class);
        $service->shouldReceive('pesquisar')
            ->once()
            ->with('São Paulo', null, 10)
            ->andReturn(['empreendimentos' => []]);

        $tool = new ToolUnderTest($service);
        $tool->handle(new Request(['cidade' => 'São Paulo', 'anos' => 99]));

        $service->shouldHaveReceived('pesquisar')->once();
    }

    public function test_retorna_erro_quando_servico_lanca_excecao(): void
    {
        $service = Mockery::mock(AiMercadoImobiliarioService::class);
        $service->shouldReceive('pesquisar')
            ->andThrow(new \RuntimeException('timeout'));

        $tool = new ToolUnderTest($service);
        $result = $tool->handle(new Request(['cidade' => 'Fortaleza']));

        $this->assertStringContainsString('Falha', (string) $result);
    }
}
