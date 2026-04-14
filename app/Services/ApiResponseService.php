<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiResponseService
{
    /**
     * Retorna uma resposta de sucesso.
     */
    public static function success(
        mixed $data = null,
        string $message = 'SUCCESS_OPERATION',
        int $statusCode = 200
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => language()->t($message),
        ], $statusCode);
    }

    /**
     * Retorna uma resposta de recurso criado (201).
     */
    public static function created(
        mixed $data = null,
        string $message = 'RESOURCE_CREATED_SUCCESSFULLY'
    ): JsonResponse {
        return static::success($data, language()->t($message), 201);
    }

    /**
     * Retorna uma resposta sem conteúdo (204).
     */
    public static function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Retorna uma resposta paginada.
     */
    public static function paginated(
        LengthAwarePaginator $paginator,
        string $message = 'DATA_RETRIEVED_SUCCESSFULLY'
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'message' => language()->t($message),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ], 200);
    }

    /**
     * Retorna uma resposta de erro.
     */
    public static function error(
        string $code,
        string $message = 'UNKNOWN_ERROR',
        mixed $details = null,
        int $statusCode = 400
    ): JsonResponse {
        $response = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => language()->t($message),
            ],
        ];

        if ($details !== null) {
            $response['error']['details'] = $details;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Retorna uma resposta de erro de validação.
     */
    public static function validationError(array $errors): JsonResponse
    {
        return static::error(
            'VALIDATION_ERROR',
            language()->t('INVALID_PROVIDED_DATA'),
            $errors,
            422
        );
    }

    /**
     * Retorna uma resposta de não autorizado.
     */
    public static function unauthorized(string $message = 'NOT_AUTHENTICATED'): JsonResponse
    {
        return static::error('UNAUTHORIZED', language()->t($message), null, 401);
    }

    /**
     * Retorna uma resposta de proibido.
     */
    public static function forbidden(string $message = 'MISSING_PERMISSION'): JsonResponse
    {
        return static::error('FORBIDDEN', language()->t($message), null, 403);
    }

    /**
     * Retorna uma resposta de não encontrado.
     */
    public static function notFound(string $message = 'RESOURCE_NOT_FOUND'): JsonResponse
    {
        return static::error('NOT_FOUND', language()->t($message), null, 404);
    }

    /**
     * Retorna uma resposta de conflito.
     */
    public static function conflict(string $message = 'RESOURCE_ALREADY_EXISTS'): JsonResponse
    {
        return static::error('CONFLICT', language()->t($message), null, 409);
    }

    /**
     * Retorna uma resposta de excesso de requisições.
     */
    public static function tooManyRequests(string $message = 'TOO_MANY_REQUESTS_1_MINUTE'): JsonResponse
    {
        return static::error('TOO_MANY_REQUESTS', language()->t($message), null, 429);
    }

    /**
     * Retorna uma resposta de erro interno do servidor.
     */
    public static function serverError(string $message = 'INTERNAL_SERVER_ERROR'): JsonResponse
    {
        return static::error('INTERNAL_ERROR', language()->t($message), null, 500);
    }
}
