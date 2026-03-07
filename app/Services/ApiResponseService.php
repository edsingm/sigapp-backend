<?php

namespace App\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class ApiResponseService
{
    /**
     * Return a success response.
     */
    public static function success(
        mixed $data = null,
        string $message = 'Operação realizada com sucesso',
        int $statusCode = 200
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
        ], $statusCode);
    }

    /**
     * Return a created response (201).
     */
    public static function created(
        mixed $data = null,
        string $message = 'Recurso criado com sucesso'
    ): JsonResponse {
        return static::success($data, $message, 201);
    }

    /**
     * Return a no content response (204).
     */
    public static function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Return a paginated response.
     */
    public static function paginated(
        LengthAwarePaginator $paginator,
        string $message = 'Dados recuperados com sucesso'
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'message' => $message,
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
     * Return an error response.
     */
    public static function error(
        string $code,
        string $message,
        mixed $details = null,
        int $statusCode = 400
    ): JsonResponse {
        $response = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        if ($details !== null) {
            $response['error']['details'] = $details;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return a validation error response.
     */
    public static function validationError(array $errors): JsonResponse
    {
        return static::error(
            'VALIDATION_ERROR',
            'Os dados fornecidos são inválidos',
            $errors,
            422
        );
    }

    /**
     * Return an unauthorized response.
     */
    public static function unauthorized(string $message = 'Não autenticado'): JsonResponse
    {
        return static::error('UNAUTHORIZED', $message, null, 401);
    }

    /**
     * Return a forbidden response.
     */
    public static function forbidden(string $message = 'Sem permissão'): JsonResponse
    {
        return static::error('FORBIDDEN', $message, null, 403);
    }

    /**
     * Return a not found response.
     */
    public static function notFound(string $message = 'Recurso não encontrado'): JsonResponse
    {
        return static::error('NOT_FOUND', $message, null, 404);
    }

    /**
     * Return a conflict response.
     */
    public static function conflict(string $message = 'Recurso já existe'): JsonResponse
    {
        return static::error('CONFLICT', $message, null, 409);
    }

    /**
     * Return a too many requests response.
     */
    public static function tooManyRequests(string $message = 'Muitas requisições. Tente novamente em 1 minuto.'): JsonResponse
    {
        return static::error('TOO_MANY_REQUESTS', $message, null, 429);
    }

    /**
     * Return an internal server error response.
     */
    public static function serverError(string $message = 'Erro interno do servidor'): JsonResponse
    {
        return static::error('INTERNAL_ERROR', $message, null, 500);
    }
}
