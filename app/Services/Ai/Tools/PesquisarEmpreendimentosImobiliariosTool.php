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
        return 'Pesquisa empreendimentos imobiliários, lançamentos e projetos em obras em uma cidade específica, consultando OLX/ZAP e busca web. ATENÇÃO: construtoras do segmento econômico/popular (Pacaembu, MRV, Tenda, Cury, etc.) vendem via MCMV/CEF e site próprio, não aparecem em portais — o campo "aviso_cobertura" no retorno indica explicitamente quais segmentos podem estar subrepresentados e deve ser comunicado ao usuário. Use para análise de concorrentes, benchmarks de mercado, identificação de construtoras ativas e comparação de padrões e preços.';
    }

    public function handle(Request $request): Stringable|string
    {
        $cidade = trim((string) ($request['cidade'] ?? ''));
        $estado = trim((string) ($request['estado'] ?? '')) ?: null;
        $anos = max(1, min(10, (int) ($request['anos'] ?? 5)));

        if ($cidade === '') {
            return AiToolResponse::validation('Informe o nome da cidade para realizar a pesquisa de empreendimentos.');
        }

        if ($deny = app(AiToolAuth::class)->ensureRateLimit(
            'ai-tool-mercado',
            (int) config('ai.mercado_rate_limit_per_hour', 10),
            3600,
            'Limite de pesquisas de mercado atingido para este período.'
        )) {
            return $deny;
        }

        try {
            $resultado = $this->mercadoService->pesquisar($cidade, $estado, $anos);
        } catch (Throwable $exception) {
            return AiToolResponse::error('Falha ao pesquisar empreendimentos imobiliários: '.$exception->getMessage());
        }

        return AiToolResponse::ok(is_array($resultado) ? $resultado : ['result' => $resultado]);
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
