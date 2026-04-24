<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ChatAiRequest;
use App\Repositories\AiConversationRepository;
use App\Services\AiDataRedactor;
use App\Services\AiProviderRouter;
use App\Services\AiTelemetryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Exceptions\RateLimitedException;

class AiController extends Controller
{
    public function __construct(
        private readonly AiConversationRepository $conversationRepository
    ) {}

    /**
     * Lista as conversas do usuário autenticado (50 mais recentes).
     */
    public function conversations(): JsonResponse
    {
        $userId = (int) Auth::id();
        $rows = $this->conversationRepository->getRecentConversations($userId);

        return new JsonResponse(['data' => $rows]);
    }

    /**
     * Retorna as mensagens (user + assistant) de uma conversa.
     */
    public function conversationMessages(string $id): JsonResponse
    {
        if (! $this->conversationRepository->conversationExists((int) $id, Auth::id())) {
            return new JsonResponse(['message' => 'Conversa não encontrada.'], 404);
        }

        $messages = $this->conversationRepository->getMessages((int) $id);

        return new JsonResponse(['data' => $messages]);
    }

    /**
     * Retorna o status atual de uso e orçamento de IA do tenant.
     */
    public function budgetStatus(AiTelemetryService $telemetryService): JsonResponse
    {
        return new JsonResponse([
            'data' => $telemetryService->getBudgetStatus(),
        ]);
    }

    /**
     * Envia uma mensagem para o agente SIG IA e retorna a resposta via streaming.
     *
     * Aceita `conversation_id` opcional para continuar uma conversa existente.
     * Para novas conversas, cria o registro em `agent_conversations` antes de streamar
     * e devolve o ID gerado no header `X-Conversation-Id`.
     */
    public function chat(
        ChatAiRequest $request,
        AiTelemetryService $telemetryService,
        AiProviderRouter $providerRouter,
        AiDataRedactor $redactor
    ): mixed {
        $user = Auth::id();
        $message = $request->string('message')->toString();
        $conversationId = $request->input('conversation_id');

        // Redact data sensível da mensagem
        $message = $redactor->redactPrompt($message);

        try {
            // Conversa existente: verificar ownership
            if ($conversationId) {
                if (! $this->conversationRepository->conversationExists((int) $conversationId, $user)) {
                    return new JsonResponse(['message' => 'Conversa não encontrada.'], 404);
                }
            } else {
                // Nova conversa: criar registro
                $store = resolve(ConversationStore::class);
                $conversationId = $store->storeConversation($user, Str::limit($message, 60));
            }
        } catch (\Throwable $e) {
            Log::error('AI conversation setup failed: '.$e->getMessage());

            return new JsonResponse(
                ['message' => 'Falha ao configurar conversa.'],
                500
            );
        }

        // Resolve agente (primário por enquanto)
        $agentRoute = $providerRouter->getAgentWithFallback();
        $agent = $agentRoute['agent'];

        $startTime = microtime(true);

        try {
            $streamable = $agent->continue($conversationId, Auth::user())->stream($message);

            $streamable->then(function ($streamedResponse) use (
                $user,
                $conversationId,
                $agentRoute,
                $startTime,
                $telemetryService,
                $providerRouter
            ) {
                $duration = (int) ((microtime(true) - $startTime) * 1000);
                $provider = $streamedResponse->meta->provider ?? $agentRoute['provider'];
                $model = $streamedResponse->meta->model ?? $agentRoute['model'];
                $usage = $streamedResponse->usage ?? null;
                $promptTokens = $usage->promptTokens ?? 0;
                $completionTokens = $usage->completionTokens ?? 0;
                $totalTokens = $promptTokens + $completionTokens;
                $estimatedCost = $telemetryService->estimateCost($provider, $model, $promptTokens, $completionTokens);
                $toolCalls = $streamedResponse->events
                    ->where('type', 'tool-call')
                    ->map(fn ($event) => ['tool' => $event->tool ?? 'unknown', 'input' => $event->input ?? []])
                    ->values()
                    ->toArray();

                $providerRouter->recordAttempt($provider, $model, true);

                $telemetryService->logRequest([
                    'user_id' => $user,
                    'conversation_id' => $conversationId,
                    'provider' => $provider,
                    'model' => $model,
                    'prompt_tokens' => $promptTokens,
                    'completion_tokens' => $completionTokens,
                    'total_tokens' => $totalTokens,
                    'estimated_cost_usd' => $estimatedCost,
                    'duration_ms' => $duration,
                    'tool_calls_count' => count($toolCalls),
                    'tool_calls' => $toolCalls,
                    'status' => 'success',
                    'ip_address' => request()->ip(),
                ]);
            });

            $response = response()->stream(function () use (
                $streamable,
                $user,
                $conversationId,
                $agentRoute,
                $startTime,
                $telemetryService,
                $providerRouter
            ) {
                try {
                    $hasTextContent = false;

                    foreach ($streamable as $event) {
                        $type = method_exists($event, 'type') ? $event->type() : ($event->type ?? null);

                        // Skip reasoning events entirely
                        if (is_string($type) && Str::startsWith($type, 'reasoning_')) {
                            continue;
                        }

                        // Skip text_delta events with empty content (models like Qwen3 emit
                        // these during thinking phases before producing real text)
                        if ($type === 'text_delta') {
                            $delta = $event->delta ?? '';
                            if ($delta === '') {
                                continue;
                            }
                            $hasTextContent = true;
                        }

                        echo 'data: '.((string) $event)."\n\n";
                        if (function_exists('ob_flush')) {
                            @ob_flush();
                        }
                        flush();
                    }

                    // If no real text was produced (only tool calls or empty response),
                    // send a friendly message instead of a blank response
                    if (! $hasTextContent) {
                        Log::warning('AI stream produced no text content', [
                            'user_id' => $user,
                            'conversation_id' => $conversationId,
                            'provider' => $agentRoute['provider'],
                            'model' => $agentRoute['model'],
                        ]);

                        echo 'data: '.json_encode([
                            'type' => 'text',
                            'content' => 'A análise foi realizada, mas não encontrei informações relevantes para formular uma resposta. Isso pode ocorrer quando não há dados disponíveis no momento.',
                        ], JSON_UNESCAPED_UNICODE)."\n\n";
                    }

                    echo "data: [DONE]\n\n";
                } catch (RateLimitedException) {
                    $duration = (int) ((microtime(true) - $startTime) * 1000);
                    $providerRouter->recordAttempt($agentRoute['provider'], $agentRoute['model'], false, 'Rate limit exceeded');
                    $telemetryService->logRequest([
                        'user_id' => $user,
                        'conversation_id' => $conversationId,
                        'provider' => $agentRoute['provider'],
                        'model' => $agentRoute['model'],
                        'status' => 'rate_limited',
                        'duration_ms' => $duration,
                        'error_message' => 'Rate limit do provedor excedido',
                        'ip_address' => request()->ip(),
                    ]);
                    echo 'data: '.json_encode(['type' => 'error', 'message' => 'O assistente atingiu o limite de requisições do provedor de IA. Aguarde alguns segundos e tente novamente.'], JSON_UNESCAPED_UNICODE)."\n\n";
                    echo "data: [DONE]\n\n";
                } catch (\Throwable $e) {
                    $duration = (int) ((microtime(true) - $startTime) * 1000);
                    $providerRouter->recordAttempt($agentRoute['provider'], $agentRoute['model'], false, $e->getMessage());
                    $telemetryService->logRequest([
                        'user_id' => $user,
                        'conversation_id' => $conversationId,
                        'provider' => $agentRoute['provider'],
                        'model' => $agentRoute['model'],
                        'status' => 'error',
                        'duration_ms' => $duration,
                        'error_message' => $e->getMessage(),
                        'ip_address' => request()->ip(),
                    ]);

                    Log::error('AI stream error: '.$e->getMessage(), [
                        'user_id' => $user,
                        'conversation_id' => $conversationId,
                        'provider' => $agentRoute['provider'],
                        'model' => $agentRoute['model'],
                    ]);

                    echo 'data: '.json_encode(['type' => 'error', 'message' => 'Erro ao processar a resposta da IA. Tente novamente.'], JSON_UNESCAPED_UNICODE)."\n\n";
                    echo "data: [DONE]\n\n";
                }
            }, 200, ['Content-Type' => 'text/event-stream']);

            $response->headers->set('X-Conversation-Id', $conversationId);
            $response->headers->set('X-AI-Provider', $agentRoute['provider'].'/'.$agentRoute['model']);
            $response->headers->set('Access-Control-Expose-Headers', 'X-Conversation-Id, X-AI-Provider');

            return $response;
        } catch (RateLimitedException) {
            // Registrar falha de rate limit na telemetria
            $duration = (int) ((microtime(true) - $startTime) * 1000);

            $providerRouter->recordAttempt(
                $agentRoute['provider'],
                $agentRoute['model'],
                false,
                'Rate limit exceeded',
            );

            $telemetryService->logRequest([
                'user_id' => $user,
                'conversation_id' => $conversationId,
                'provider' => $agentRoute['provider'],
                'model' => $agentRoute['model'],
                'status' => 'rate_limited',
                'duration_ms' => $duration,
                'error_message' => 'Rate limit do provedor excedido',
                'ip_address' => request()->ip(),
            ]);

            return new JsonResponse(
                ['message' => 'O assistente atingiu o limite de requisições do provedor de IA. Aguarde alguns segundos e tente novamente.'],
                429,
            );
        }
    }
}
