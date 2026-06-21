<?php

namespace App\Services\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class PesquisarEmpreendimentosImobiliariosTool implements Tool
{
    public function __construct(
        protected AiMercadoImobiliarioService $mercadoService
    ) {}

    public function description(): Stringable|string
    {
        return 'Pesquisa empreendimentos imobiliários e lançamentos em uma cidade específica, consultando múltiplas fontes do mercado imobiliário brasileiro (VivaReal, ZAP Imóveis, busca web e portais). Use para análise de concorrentes, benchmarks de mercado imobiliário local, identificação de lançamentos recentes, perfil de construtoras/incorporadoras atuantes na região, e comparação de padrões, tipologias e faixas de preço praticadas.';
    }

    public function handle(Request $request): Stringable|string
    {
        $cidade = trim((string) ($request['cidade'] ?? ''));
        $estado = trim((string) ($request['estado'] ?? '')) ?: null;
        $anos = max(1, min(10, (int) ($request['anos'] ?? 5)));

        if ($cidade === '') {
            return 'Informe o nome da cidade para realizar a pesquisa de empreendimentos.';
        }

        try {
            $resultado = $this->mercadoService->pesquisar($cidade, $estado, $anos);
        } catch (Throwable $exception) {
            return 'Falha ao pesquisar empreendimentos imobiliários: '.$exception->getMessage();
        }

        return json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ?: 'Falha ao serializar resultados da pesquisa.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'cidade' => $schema->string()->required()
                ->description('Nome da cidade a pesquisar empreendimentos imobiliários (ex: Campinas, Ribeirão Preto, Goiânia).'),
            'estado' => $schema->string()
                ->description('Sigla do estado brasileiro (ex: SP, MG, GO). Opcional, mas recomendado para cidades com nomes repetidos em vários estados.'),
            'anos' => $schema->integer()
                ->description('Quantidade de anos de histórico de lançamentos a considerar (padrão: 5, máximo: 10).'),
        ];
    }
}
