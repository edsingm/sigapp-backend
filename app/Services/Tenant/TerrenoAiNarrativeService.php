<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Services\Ai\Tools\AiProviderRouter;
use App\Services\Ai\Tools\AiTelemetryService;
use Illuminate\Support\Facades\Auth;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use RuntimeException;

final class TerrenoAiNarrativeService
{
    public function __construct(
        private readonly AiTelemetryService $telemetryService,
    ) {}

    /** @return array{markdown: string, html: string} */
    public function generate(array $context): array
    {
        $jsonContext = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ?: '{}';

        $prompt = <<<PROMPT
Você está escrevendo um relatório executivo do SIG IA para um terreno específico.

Regras obrigatórias:
- Escreva exclusivamente em português brasileiro.
- Baseie-se apenas no CONTEXTO fornecido.
- Não invente dados, não especule e não faça perguntas.
- Não use HTML.
- Produza o corpo completo do relatório, como se fosse a resposta final do chat da SIG IA.
- Escreva com tom executivo, objetivo e analítico, sem texto de preenchimento.
- Use todos os dados disponíveis no contexto para montar uma leitura completa do terreno.
- Use exatamente esta estrutura e ordem:

**Resumo Executivo**
2 a 5 linhas curtas e impactantes. Explique o panorama geral do terreno.

---

**Cadastro e Localização**
- Nome, cidade, UF, endereço, bairro, zona, distrito e responsável
- Datas e observações relevantes do cadastro

---

**Geografia, Mapa e Entorno**
- Área, polígono, centroide, declividade, APP e leitura do entorno
- Pontos de apoio, vias próximas e qualquer restrição geográfica relevante

---

**Cidade e Contexto IBGE**
- Informações municipais relevantes que ajudem a contextualizar o terreno

---

**Workflow e Operação**
- Status atual, etapa, motivo, legalização, comitê e negociação
- Inclua pendências, atrasos e sinais de avanço

---

**Viabilidade**
- Situação atual, histórico resumido e leitura executiva da aprovação

---

**Documentos e Tarefas**
- Documentos, tarefas e pendências mais importantes

---

**Mercado, Score e Comparativos**
- Score, ranking, probabilidade de aprovação, VGV, insights, tendências e comparações

---

**Riscos e Pontos de Atenção**
- Liste os maiores riscos primeiro
- Destaque inconsistências, atrasos, lacunas de dados e sinais de alerta

---

**Recomendações Práticas**
1. Ação mais urgente
2. Próxima ação
3. Ação complementar

---

**Próximos Passos Sugeridos**
- Bullets acionáveis e claros
- Inclua orientações que ajudem a tomada de decisão

Contexto estruturado do terreno:
{$jsonContext}

Se algum dado não estiver presente, diga explicitamente "Não informado".
PROMPT;

        $agentRoute = app(AiProviderRouter::class)->getAgentWithFallback();
        $agent = $agentRoute['agent'];
        $startTime = microtime(true);

        try {
            $this->telemetryService->ensureBudgetAvailable();
            $response = $agent->prompt(
                $prompt,
                provider: $agentRoute['providers'],
                timeout: 180
            );

            $markdown = trim((string) $response);

            if ($markdown === '') {
                throw new RuntimeException('A IA não retornou conteúdo para o relatório.');
            }

            $usage = $response->usage;
            $promptTokens = $usage->promptTokens;
            $completionTokens = $usage->completionTokens;
            $cacheReadInputTokens = $usage->cacheReadInputTokens;
            $provider = $response->meta->provider ?? $agentRoute['provider'];
            $model = $response->meta->model ?? $agentRoute['model'];
            $estimatedCost = $this->telemetryService->estimateCost(
                $provider,
                $model,
                $promptTokens,
                $completionTokens,
                $cacheReadInputTokens
            );

            $this->telemetryService->logRequest([
                'user_id' => Auth::id(),
                'provider' => $provider,
                'model' => $model,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $promptTokens + $completionTokens,
                'estimated_cost_usd' => $estimatedCost,
                'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'tool_calls_count' => 0,
                'tool_calls' => [
                    ['tool' => 'terreno_ai_report_narrative'],
                ],
                'status' => 'success',
                'ip_address' => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            $this->telemetryService->logRequest([
                'user_id' => Auth::id(),
                'provider' => $agentRoute['provider'],
                'model' => $agentRoute['model'],
                'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'tool_calls_count' => 0,
                'tool_calls' => [
                    ['tool' => 'terreno_ai_report_narrative'],
                ],
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'ip_address' => request()->ip(),
            ]);

            $markdown = $this->fallbackNarrative($context, $e->getMessage());
        }

        $converter = new GithubFlavoredMarkdownConverter;
        $html = $converter->convert($markdown)->getContent();

        return [
            'markdown' => $markdown,
            'html' => $html,
        ];
    }

    private function fallbackNarrative(array $context, string $reason): string
    {
        $terreno = $context['terreno'] ?? [];
        $workflow = $context['workflowSummary'] ?? [];
        $geo = $context['geoSummary'] ?? [];
        $market = $context['marketSummary'] ?? [];

        $nome = (string) ($terreno['nome'] ?? '—');
        $cidade = (string) ($terreno['cidade'] ?? '—');
        $estado = (string) ($terreno['estado'] ?? '—');
        $status = $this->findSummaryValue($workflow, 'Status') ?? 'Não informado';
        $score = data_get($market, 'score.score', '—');
        $tier = data_get($market, 'score.tier', '—');
        $area = data_get($geo, 'area_util_m2', '—');
        $support = count((array) ($context['supportPoints'] ?? []));

        return <<<MD
**Resumo Executivo**
A IA não conseguiu redigir a narrativa completa neste momento, então este relatório
foi gerado com base nos dados estruturados já consultados. Terreno
**{$nome}** em **{$cidade} / {$estado}**.
Status atual **{$status}**. Score **{$score}** ({$tier}).

---

**Principais Evidências**
- Área útil: **{$area} m²**
- Pontos de apoio próximos: **{$support}**
- Razão técnica: {$reason}

---

**Riscos e Pontos de Atenção**
- A narrativa automática da IA não foi concluída
- Verifique os dados de origem antes de compartilhar externamente

---

**Recomendações Práticas**
1. Reexecutar a geração da narrativa assim que a IA estiver disponível
2. Revisar os dados cadastrais e geográficos do terreno
3. Manter o PDF com os anexos técnicos gerados

---

**Próximos Passos Sugeridos**
- Reprocessar o relatório com a SIG IA
- Conferir a consistência dos dados do terreno
- Validar o mapa e os anexos técnicos
MD;
    }

    private function findSummaryValue(array $summary, string $label): ?string
    {
        foreach ($summary as $item) {
            if ($item['label'] === $label) {
                return $item['value'];
            }
        }

        return null;
    }
}
