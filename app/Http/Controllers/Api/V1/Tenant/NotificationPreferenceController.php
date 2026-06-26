<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateNotificationPreferencesRequest;
use App\Http\Requests\Tenant\UpdateNotificationSettingsRequest;
use App\Services\ApiResponseService;
use App\Services\Tenant\NotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function __construct(
        protected NotificationPreferenceService $preferences
    ) {}

    /**
     * Retorna a matriz de preferências de notificação do usuário.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponseService::success(
            [
                'categories' => $this->preferences->matrixForUser($user),
                'settings' => $this->preferences->settingsForUser($user),
            ],
            'Preferências de notificação carregadas com sucesso'
        );
    }

    /**
     * Atualiza as preferências de notificação do usuário.
     */
    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $this->preferences->updateForUser($request->user(), $request->validated('preferences'));

        return ApiResponseService::success(
            ['categories' => $this->preferences->matrixForUser($request->user())],
            'Preferências de notificação atualizadas com sucesso'
        );
    }

    /**
     * Atualiza as configurações globais (silêncio e resumo de e-mail).
     */
    public function updateSettings(UpdateNotificationSettingsRequest $request): JsonResponse
    {
        $this->preferences->updateSettingsForUser($request->user(), $request->validated());

        return ApiResponseService::success(
            ['settings' => $this->preferences->settingsForUser($request->user()->fresh())],
            'Configurações de notificação atualizadas com sucesso'
        );
    }
}
