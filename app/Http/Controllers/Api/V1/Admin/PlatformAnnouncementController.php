<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlatformAnnouncementRequest;
use App\Http\Requests\Admin\UpdatePlatformAnnouncementRequest;
use App\Models\Central\PlatformAnnouncement;
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

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->get('per_page', 20)));
        $paginator = $this->service->paginate($perPage)->through(
            fn (PlatformAnnouncement $a): array => $this->serialize($a)
        );

        return ApiResponseService::paginated($paginator);
    }

    public function store(StorePlatformAnnouncementRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()?->id;

        $announcement = $this->service->createDraft($data);

        return ApiResponseService::created($this->serialize($announcement->load('author:id,name,email')));
    }

    public function show(PlatformAnnouncement $announcement): JsonResponse
    {
        $announcement->load('author:id,name,email');

        return ApiResponseService::success([
            ...$this->serialize($announcement),
            'estimated_recipients' => $this->service->estimateRecipients($announcement),
        ]);
    }

    public function update(
        UpdatePlatformAnnouncementRequest $request,
        PlatformAnnouncement $announcement
    ): JsonResponse {
        try {
            $model = $this->service->updateDraft($announcement, $request->validated());
        } catch (InvalidArgumentException $e) {
            return ApiResponseService::error('INVALID_STATE', $e->getMessage(), null, 422);
        }

        return ApiResponseService::success($this->serialize($model->load('author:id,name,email')));
    }

    public function destroy(PlatformAnnouncement $announcement): JsonResponse
    {
        try {
            $this->service->deleteDraft($announcement);
        } catch (InvalidArgumentException $e) {
            return ApiResponseService::error('INVALID_STATE', $e->getMessage(), null, 422);
        }

        return ApiResponseService::success(null, 'SUCCESS_OPERATION');
    }

    public function send(PlatformAnnouncement $announcement): JsonResponse
    {
        try {
            $model = $this->service->send($announcement);
        } catch (InvalidArgumentException $e) {
            return ApiResponseService::error('INVALID_STATE', $e->getMessage(), null, 422);
        }

        $this->audit(
            'announcement.sent',
            "Anúncio \"{$model->title}\" enviado para {$model->recipients_count} destinatário(s).",
            [
                'announcement_id' => $model->id,
                'recipients_count' => $model->recipients_count,
                'segment' => $model->segment,
                'channel' => $model->channel,
            ]
        );

        return ApiResponseService::success($this->serialize($model->load('author:id,name,email')));
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(PlatformAnnouncement $a): array
    {
        $author = $a->relationLoaded('author') ? $a->author : null;

        return [
            'id' => $a->id,
            'title' => $a->title,
            'body' => $a->body,
            'type' => $a->type ?: PlatformAnnouncement::TYPE_INFO,
            'channel' => $a->channel,
            'segment' => $a->segment,
            'segment_value' => $a->segment_value,
            'status' => $a->status,
            'recipients_count' => $a->recipients_count,
            'sent_at' => $a->sent_at?->toIso8601String(),
            'created_by' => $a->created_by,
            'author' => $author !== null ? [
                'id' => $author->id,
                'name' => $author->name,
                'email' => $author->email,
            ] : null,
            'meta' => $a->meta,
            'created_at' => $a->created_at?->toIso8601String(),
            'updated_at' => $a->updated_at?->toIso8601String(),
        ];
    }
}
