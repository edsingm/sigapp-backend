<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ChatAiRequest;
use App\Repositories\AiConversationRepository;
use App\Services\Ai\AiStreamResponseGuard;
use App\Services\Ai\Tools\AiDataRedactor;
use App\Services\Ai\Tools\AiProviderRouter;
use App\Services\Ai\Tools\AiTelemetryService;
use App\Services\Ai\Tools\AiToolCallTelemetry;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Exceptions\RateLimitedException;
use Symfony\Component\HttpFoundation\Response;

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

        return ApiResponseService::success($rows);
    }

    /**
     * Retorna as mensagens (user + assistant) de uma conversa.
     */
    public function conversationMessages(string $id): JsonResponse
    {
        if (! $this->conversationRepository->conversationExists($id, Auth::id())) {
            return ApiResponseService::notFound('Conversa não encontrada.');
        }

        $messages = $this->conversationRepository->getMessages($id);

        return ApiResponseService::success($messages);
    }

    /**
     * Retorna o status atual de uso e orçamento de IA do tenant.
     */
    public function budgetStatus(AiTelemetryService $telemetryService): JsonResponse
    {
        return ApiResponseService::success($telemetryService->getBudgetStatus());
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
    ): Response {
        $userId = Auth::id();
        $authUser = Auth::user();
        $message = $request->string('message')->toString();
        $conversationId = $request->input('conversation_id');

        if (! $authUser) {
            return ApiResponseService::error(
                'UNAUTHENTICATED',
                'Usuário não autenticado.',
                null,
                401
            );
        }

        // Redact data sensível da mensagem
        $message = $redactor->redactPrompt($message);

        try {
            // Conversa existente: verificar ownership
            if ($conversationId) {
                if (! $this->conversationRepository->conversationExists($conversationId, $userId)) {
                    return ApiResponseService::notFound('Conversa não encontrada.');
                }
            } else {
                // Nova conversa: criar registro
                $store = resolve(ConversationStore::class);
                $conversationId = $store->storeConversation($userId, Str::limit($message, 60));
            }
        } catch (\Throwable $e) {
            Log::error('AI conversation setup failed: '.$e->getMessage());

            return ApiResponseService::error(
                'AI_CONVERSATION_SETUP_FAILED',
                'Falha ao configurar conversa.',
                null,
                500
            );
        }

        // Resolve agente (primário por enquanto)
        $agentRoute = $providerRouter->getAgentWithFallback();
        $agent = $agentRoute['agent'];

        $startTime = microtime(true);

        try {
            $streamable = $agent->continue($conversationId, $authUser)->stream(
                $message,
                provider: $agentRoute['providers'],
            );

            $streamable->then(function ($streamedResponse) use (
                $userId,
                $conversationId,
                $agentRoute,
                $startTime,
                $telemetryService,
                $providerRouter,
                $redactor
            ) {
                $duration = (int) ((microtime(true) - $startTime) * 1000);
                $provider = $streamedResponse->meta->provider ?? $agentRoute['provider'];
                $model = $streamedResponse->meta->model ?? $agentRoute['model'];
                $usage = $streamedResponse->usage ?? null;
                $promptTokens = $usage->promptTokens ?? 0;
                $completionTokens = $usage->completionTokens ?? 0;
                $cacheReadInputTokens = $usage->cacheReadInputTokens ?? 0;
                $totalTokens = $promptTokens + $completionTokens;
                $estimatedCost = $telemetryService->estimateCost($provider, $model, $promptTokens, $completionTokens, $cacheReadInputTokens);
                $toolCalls = AiToolCallTelemetry::fromStreamEvents(
                    $streamedResponse->events,
                    $redactor
                );

                $providerRouter->recordAttempt($provider, $model, true);

                $telemetryService->logRequest([
                    'user_id' => $userId,
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
                $userId,
                $conversationId,
                $agentRoute,
                $startTime,
                $telemetryService,
                $providerRouter
            ) {
                try {
                    $hasTextContent = false;
                    $accumulatedText = '';

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
                            $accumulatedText .= $delta;
                        } elseif ($type === 'text' && is_string($event->content ?? null)) {
                            $hasTextContent = true;
                            $accumulatedText .= (string) $event->content;
                        }

                        echo 'data: '.((string) $event)."\n\n";
                        if (function_exists('ob_flush')) {
                            @ob_flush();
                        }
                        flush();
                    }

                    // Sem texto: fallback genérico
                    if (! $hasTextContent) {
                        Log::warning('AI stream produced no text content', [
                            'user_id' => $userId,
                            'conversation_id' => $conversationId,
                            'provider' => $agentRoute['provider'],
                            'model' => $agentRoute['model'],
                        ]);

                        echo 'data: '.json_encode([
                            'type' => 'text',
                            'content' => AiStreamResponseGuard::emptyFallbackMessage(),
                        ], JSON_UNESCAPED_UNICODE)."\n\n";
                    } elseif (AiStreamResponseGuard::looksIncomplete($accumulatedText)) {
                        // Texto só com monólogo de progresso ("vou buscar…") sem entrega final
                        Log::warning('AI stream produced incomplete progress monologue', [
                            'user_id' => $userId,
                            'conversation_id' => $conversationId,
                            'provider' => $agentRoute['provider'],
                            'model' => $agentRoute['model'],
                            'text_tail' => mb_substr($accumulatedText, -240),
                        ]);

                        echo 'data: '.json_encode([
                            'type' => 'text',
                            'content' => "\n\n".AiStreamResponseGuard::incompleteFallbackMessage(),
                        ], JSON_UNESCAPED_UNICODE)."\n\n";
                    }

                    echo "data: [DONE]\n\n";
                } catch (RateLimitedException) {
                    $duration = (int) ((microtime(true) - $startTime) * 1000);
                    $providerRouter->recordAttempt($agentRoute['provider'], $agentRoute['model'], false, 'Rate limit exceeded');
                    $telemetryService->logRequest([
                        'user_id' => $userId,
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
                        'user_id' => $userId,
                        'conversation_id' => $conversationId,
                        'provider' => $agentRoute['provider'],
                        'model' => $agentRoute['model'],
                        'status' => 'error',
                        'duration_ms' => $duration,
                        'error_message' => $e->getMessage(),
                        'ip_address' => request()->ip(),
                    ]);

                    Log::error('AI stream error: '.$e->getMessage(), [
                        'user_id' => $userId,
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
                'user_id' => $userId,
                'conversation_id' => $conversationId,
                'provider' => $agentRoute['provider'],
                'model' => $agentRoute['model'],
                'status' => 'rate_limited',
                'duration_ms' => $duration,
                'error_message' => 'Rate limit do provedor excedido',
                'ip_address' => request()->ip(),
            ]);

            return ApiResponseService::error(
                'AI_PROVIDER_RATE_LIMITED',
                'O assistente atingiu o limite de requisições do provedor de IA. Aguarde alguns segundos e tente novamente.',
                null,
                429,
            );
        }
    }
}
