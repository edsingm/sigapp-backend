<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Central\PlatformAnnouncement;
use App\Models\Central\Tenant;
use App\Services\Admin\PlatformAnnouncementService;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PlatformAnnouncementController extends Controller
{
    public function __construct(
        private readonly PlatformAnnouncementService $service,
    ) {}

    /**
     * Banners ativos da plataforma para o tenant autenticado.
     *
     * GET /api/v1/platform-announcements/active
     */
    public function active(Request $request): JsonResponse
    {
        $tenant = tenancy()->tenant;
        if (! $tenant instanceof Tenant) {
            return ApiResponseService::error('TENANT_REQUIRED', 'Tenant não resolvido.', null, 400);
        }

        $user = $request->user();
        if ($user === null) {
            return ApiResponseService::error('UNAUTHORIZED', 'Não autenticado.', null, 401);
        }

        $items = $this->service
            ->activeBannersForTenant($tenant, (int) $user->getKey())
            ->map(fn (PlatformAnnouncement $a): array => [
                'id' => $a->id,
                'title' => $a->title,
                'body' => $a->body,
                'channel' => $a->channel,
                'sent_at' => $a->sent_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return ApiResponseService::success(['announcements' => $items]);
    }

    /**
     * Dispensa o banner para o usuário atual (não reaparece).
     *
     * POST /api/v1/platform-announcements/{announcement}/dismiss
     */
    public function dismiss(Request $request, int $announcement): JsonResponse
    {
        $tenant = tenancy()->tenant;
        if (! $tenant instanceof Tenant) {
            return ApiResponseService::error('TENANT_REQUIRED', 'Tenant não resolvido.', null, 400);
        }

        $user = $request->user();
        if ($user === null) {
            return ApiResponseService::error('UNAUTHORIZED', 'Não autenticado.', null, 401);
        }

        $model = PlatformAnnouncement::query()->find($announcement);
        if ($model === null) {
            return ApiResponseService::notFound(language()->t('RESOURCE_NOT_FOUND'));
        }

        try {
            $this->service->dismissForUser($model, $tenant, (int) $user->getKey());
        } catch (InvalidArgumentException $e) {
            return ApiResponseService::error('INVALID_STATE', $e->getMessage(), null, 422);
        }

        return ApiResponseService::success(null, 'SUCCESS_OPERATION');
    }
}
