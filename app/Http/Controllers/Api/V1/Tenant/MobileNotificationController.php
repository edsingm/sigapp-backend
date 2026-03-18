<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\MobileNotificationResource;
use App\Services\ApiResponseService;
use App\Services\Tenant\MobilePushService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class MobileNotificationController extends Controller
{
    public function __construct(
        protected MobilePushService $mobilePushService
    ) {}

    /**
     * Listar notificações móveis.
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $notifications = $this->mobilePushService->paginateNotifications(
            $request->user(),
            (int) ($data['per_page'] ?? 20)
        );

        $notifications->through(
            fn ($notification) => (new MobileNotificationResource($notification))->resolve()
        );

        return ApiResponseService::paginated($notifications, 'Notificações carregadas com sucesso');
    }

    /**
     * Marcar uma notificação como lida.
     */
    public function read(Request $request, string $id)
    {
        try {
            $notification = $this->mobilePushService->markAsRead($request->user(), $id);

            return ApiResponseService::success(
                new MobileNotificationResource($notification),
                'Notificação marcada como lida'
            );
        } catch (ModelNotFoundException) {
            return ApiResponseService::notFound('Notificação não encontrada');
        }
    }
}
