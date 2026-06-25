<?php

namespace Tests\Unit;

use App\Services\Ai\Tools\AiMercadoImobiliarioService;
use App\Services\Ai\Tools\PesquisarEmpreendimentosImobiliariosTool as ToolUnderTest;
use Closure;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class PesquisarEmpreendimentosImobiliariosTool extends TestCase
{
    public function test_implementa_contrato_tool(): void
    {
        $service = $this->fakeMercadoService();
        $tool = new ToolUnderTest($service);

        $this->assertInstanceOf(Tool::class, $tool);
    }

    public function test_tem_descricao(): void
    {
        $service = $this->fakeMercadoService();
        $tool = new ToolUnderTest($service);

        $this->assertNotEmpty($tool->description());
    }

    public function test_retorna_erro_com_cidade_vazia(): void
    {
        $service = $this->fakeMercadoService();
        $tool = new ToolUnderTest($service);

        $result = $tool->handle(new Request([]));

        $this->assertStringContainsString('cidade', (string) $result);
    }

    public function test_retorna_json_com_cidade_valida(): void
    {
        $service = $this->fakeMercadoService(
            function (string $cidade, ?string $estado, int $anos): array {
                $this->assertSame('Campinas', $cidade);
                $this->assertSame('SP', $estado);
                $this->assertSame(5, $anos);

                return [
                    'cidade_pesquisada' => 'Campinas',
                    'estado' => 'SP',
                    'periodo_anos' => 5,
                    'total_encontrados' => 0,
                    'fontes_consultadas' => [],
                    'erros_de_consulta' => null,
                    'empreendimentos' => [],
                ];
            }
        );

        $tool = new ToolUnderTest($service);
        $result = $tool->handle(new Request(['cidade' => 'Campinas', 'estado' => 'SP']));

        $decoded = json_decode((string) $result, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('empreendimentos', $decoded);
        $this->assertSame('Campinas', $decoded['cidade_pesquisada']);
    }

    public function test_limita_anos_entre_1_e_10(): void
    {
        $called = 0;
        $service = $this->fakeMercadoService(
            function (string $cidade, ?string $estado, int $anos) use (&$called): array {
                $called++;
                $this->assertSame('São Paulo', $cidade);
                $this->assertNull($estado);
                $this->assertSame(10, $anos);

                return ['empreendimentos' => []];
            }
        );

        $tool = new ToolUnderTest($service);
        $tool->handle(new Request(['cidade' => 'São Paulo', 'anos' => 99]));

        $this->assertSame(1, $called);
    }

    public function test_retorna_erro_quando_servico_lanca_excecao(): void
    {
        $service = $this->fakeMercadoService(
            fn (): array => throw new \RuntimeException('timeout')
        );

        $tool = new ToolUnderTest($service);
        $result = $tool->handle(new Request(['cidade' => 'Fortaleza']));

        $this->assertStringContainsString('Falha', (string) $result);
    }

    private function fakeMercadoService(?Closure $callback = null): AiMercadoImobiliarioService
    {
        return new class($callback) extends AiMercadoImobiliarioService
        {
            public function __construct(private readonly ?Closure $callback) {}

            /**
             * @return array<string, mixed>
             */
            public function pesquisar(string $cidade, ?string $estado = null, int $anos = 5): array
            {
                if ($this->callback === null) {
                    return [];
                }

                return ($this->callback)($cidade, $estado, $anos);
            }
        };
    }
}
