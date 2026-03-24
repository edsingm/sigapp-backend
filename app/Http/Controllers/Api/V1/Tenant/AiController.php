<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Ai\Agents\SIG_IA;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Exceptions\RateLimitedException;

class AiController extends Controller
{
    /**
     * Lista as conversas do usuário autenticado (50 mais recentes).
     */
    public function conversations(): JsonResponse
    {
        $rows = DB::table('agent_conversations')
            ->where('user_id', Auth::id())
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get(['id', 'title', 'created_at', 'updated_at']);

        return new JsonResponse(['data' => $rows]);
    }

    /**
     * Retorna as mensagens (user + assistant) de uma conversa.
     */
    public function conversationMessages(string $id): JsonResponse
    {
        $exists = DB::table('agent_conversations')
            ->where('id', $id)
            ->where('user_id', Auth::id())
            ->exists();

        if (! $exists) {
            return new JsonResponse(['message' => 'Conversa não encontrada.'], 404);
        }

        $messages = DB::table('agent_conversation_messages')
            ->where('conversation_id', $id)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at')
            ->get(['id', 'role', 'content', 'created_at']);

        return new JsonResponse(['data' => $messages]);
    }

    /**
     * Envia uma mensagem para o agente SIG IA e retorna a resposta via streaming.
     *
     * Aceita `conversation_id` opcional para continuar uma conversa existente.
     * Para novas conversas, cria o registro em `agent_conversations` antes de streamar
     * e devolve o ID gerado no header `X-Conversation-Id`.
     */
    public function chat(Request $request): mixed
    {
        $request->validate([
            'message'         => ['required', 'string'],
            'conversation_id' => ['nullable', 'string', 'uuid'],
        ]);

        $user    = Auth::user();
        $message = $request->string('message')->toString();
        $agent   = new SIG_IA;

        try {
            $conversationId = $request->input('conversation_id');

            if ($conversationId) {
                $exists = DB::table('agent_conversations')
                    ->where('id', $conversationId)
                    ->where('user_id', $user->id)
                    ->exists();

                if (! $exists) {
                    return new JsonResponse(['message' => 'Conversa não encontrada.'], 404);
                }
            } else {
                // Nova conversa: criar registro agora para ter o ID disponível para o header
                $store          = resolve(ConversationStore::class);
                $conversationId = $store->storeConversation($user->id, Str::limit($message, 60));
            }

            $streamable = $agent->continue($conversationId, $user)->stream($message);

            // StreamableAgentResponse implementa Responsable — precisamos converter
            // para StreamedResponse (Symfony) para poder manipular os headers HTTP.
            $response = $streamable->toResponse(request());
            $response->headers->set('X-Conversation-Id', $conversationId);
            $response->headers->set('Access-Control-Expose-Headers', 'X-Conversation-Id');

            return $response;

        } catch (RateLimitedException) {
            return new JsonResponse(
                ['message' => 'O assistente atingiu o limite de requisições do provedor de IA. Aguarde alguns segundos e tente novamente.'],
                429,
            );
        }
    }
}
