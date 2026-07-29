<?php

namespace App\Services\Ai\Tools\Meta;

use App\Services\Ai\Tools\AiIbgeCityProfileService;
use App\Services\Ai\Tools\AiMercadoImobiliarioService;
use App\Services\Ai\Tools\GetCityIbgeProfileTool;
use App\Services\Ai\Tools\PesquisarEmpreendimentosImobiliariosTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Meta-tool: IBGE + mercado imobiliário externo.
 */
class MarketIntelTool implements Tool
{
    use MetaToolSupport;

    public function description(): Stringable|string
    {
        return 'Inteligência de mercado. action=ibge (perfil municipal oficial) ou empreendimentos '
            .'(lançamentos OLX/ZAP/web — leia aviso_cobertura). ibge: codigo_municipio OU cidade+uf; '
            .'empreendimentos: cidade obrigatória, estado/anos opcionais.';
    }

    public function handle(Request $request): Stringable|string
    {
        $action = $this->action($request, 'ibge');
        $args = $request->toArray();
        unset($args['action']);

        // Normaliza aliases comuns do LLM
        if ($action === 'ibge') {
            if (empty($args['uf']) && ! empty($args['estado'])) {
                $args['uf'] = $args['estado'];
            }
        }
        if ($action === 'empreendimentos') {
            if (empty($args['estado']) && ! empty($args['uf'])) {
                $args['estado'] = $args['uf'];
            }
        }

        $forward = new Request($args);

        return match ($action) {
            'ibge' => $this->call(
                new GetCityIbgeProfileTool(app(AiIbgeCityProfileService::class)),
                $forward
            ),
            'empreendimentos' => $this->call(
                new PesquisarEmpreendimentosImobiliariosTool(app(AiMercadoImobiliarioService::class)),
                $forward
            ),
            default => $this->unknownAction($action, ['ibge', 'empreendimentos']),
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->required()
                ->description('ibge | empreendimentos')
                ->enum(['ibge', 'empreendimentos']),
            'codigo_municipio' => $schema->string()->description('ibge: código IBGE'),
            'cidade' => $schema->string()->description('ibge/empreendimentos: nome da cidade'),
            'uf' => $schema->string()->description('ibge: UF (ex.: PR). Também aceito em empreendimentos.'),
            'estado' => $schema->string()->description('Alias de UF (ibge e empreendimentos).'),
            'anos' => $schema->integer()->description('empreendimentos: anos de histórico')->min(1),
        ];
    }
}
