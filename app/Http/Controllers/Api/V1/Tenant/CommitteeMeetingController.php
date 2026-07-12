<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\CommitteeAgendaItemRequest;
use App\Http\Requests\Tenant\CommitteeMinutesRequest;
use App\Http\Requests\Tenant\CommitteeParticipantRequest;
use App\Http\Requests\Tenant\ListCommitteeMeetingsRequest;
use App\Http\Requests\Tenant\ReorderCommitteeAgendaRequest;
use App\Http\Requests\Tenant\StoreCommitteeMeetingRequest;
use App\Http\Requests\Tenant\UpdateCommitteeMeetingRequest;
use App\Http\Resources\Tenant\CommitteeMeetingAgendaItemResource;
use App\Http\Resources\Tenant\CommitteeMeetingParticipantResource;
use App\Http\Resources\Tenant\CommitteeMeetingSessionResource;
use App\Http\Resources\Tenant\CommitteeMinutesResource;
use App\Models\Tenant\ComiteMeetingSession;
use App\Services\ApiResponseService;
use App\Services\Tenant\CommitteeMeetingService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class CommitteeMeetingController extends Controller
{
    public function __construct(
        private readonly CommitteeMeetingService $service,
    ) {}

    public function index(ListCommitteeMeetingsRequest $request): JsonResponse
    {
        $result = $this->service->paginate($request->validated());
        $result->through(fn (ComiteMeetingSession $session): array => (new CommitteeMeetingSessionResource($session))->resolve());

        return ApiResponseService::paginated($result, 'Reuniões de comitê carregadas com sucesso');
    }

    public function store(StoreCommitteeMeetingRequest $request): JsonResponse
    {
        try {
            $session = $this->service->create($request->validated(), (int) $request->user()->id);

            return ApiResponseService::created(new CommitteeMeetingSessionResource($session), 'Reunião de comitê criada com sucesso');
        } catch (RuntimeException $exception) {
            return ApiResponseService::error('COMMITTEE_MEETING_INVALID', $exception->getMessage(), null, 422);
        }
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponseService::success(new CommitteeMeetingSessionResource($this->service->find((int) $id)));
    }

    public function update(UpdateCommitteeMeetingRequest $request, string $id): JsonResponse
    {
        $session = $this->service->find((int) $id);

        return ApiResponseService::success(
            new CommitteeMeetingSessionResource($this->service->update($session, $request->validated(), (int) $request->user()->id)),
            'Reunião de comitê atualizada com sucesso',
        );
    }

    public function start(string $id): JsonResponse
    {
        $session = $this->service->start($this->service->find((int) $id), (int) request()->user()->id);

        return ApiResponseService::success(new CommitteeMeetingSessionResource($session), 'Reunião iniciada com sucesso');
    }

    public function finish(string $id): JsonResponse
    {
        $session = $this->service->finish($this->service->find((int) $id), (int) request()->user()->id);

        return ApiResponseService::success(new CommitteeMeetingSessionResource($session), 'Reunião finalizada com sucesso');
    }

    public function agenda(string $id): JsonResponse
    {
        $session = $this->service->find((int) $id);

        return ApiResponseService::success(CommitteeMeetingAgendaItemResource::collection($this->service->agenda($session)));
    }

    public function storeAgenda(CommitteeAgendaItemRequest $request, string $id): JsonResponse
    {
        $session = $this->service->find((int) $id);

        return ApiResponseService::created(
            new CommitteeMeetingAgendaItemResource($this->service->createAgendaItem($session, $request->validated())),
            'Item de pauta criado com sucesso',
        );
    }

    public function updateAgenda(CommitteeAgendaItemRequest $request, string $id, string $itemId): JsonResponse
    {
        $session = $this->service->find((int) $id);

        return ApiResponseService::success(
            new CommitteeMeetingAgendaItemResource($this->service->updateAgendaItem($session, (int) $itemId, $request->validated())),
            'Item de pauta atualizado com sucesso',
        );
    }

    public function destroyAgenda(string $id, string $itemId): JsonResponse
    {
        $this->service->deleteAgendaItem($this->service->find((int) $id), (int) $itemId);

        return ApiResponseService::noContent();
    }

    public function reorderAgenda(ReorderCommitteeAgendaRequest $request, string $id): JsonResponse
    {
        try {
            $items = $this->service->reorderAgenda(
                $this->service->find((int) $id),
                $request->validated('agenda_item_ids'),
            );

            return ApiResponseService::success(
                CommitteeMeetingAgendaItemResource::collection($items),
                'Pauta reordenada com sucesso',
            );
        } catch (RuntimeException $exception) {
            return ApiResponseService::error('COMMITTEE_AGENDA_INVALID', $exception->getMessage(), null, 422);
        }
    }

    public function participants(string $id): JsonResponse
    {
        $session = $this->service->find((int) $id);

        return ApiResponseService::success(CommitteeMeetingParticipantResource::collection($this->service->participants($session)));
    }

    public function storeParticipant(CommitteeParticipantRequest $request, string $id): JsonResponse
    {
        try {
            $session = $this->service->find((int) $id);

            return ApiResponseService::created(
                new CommitteeMeetingParticipantResource($this->service->createParticipant($session, $request->validated())),
                'Participante adicionado com sucesso',
            );
        } catch (RuntimeException $exception) {
            return ApiResponseService::error('COMMITTEE_PARTICIPANT_INVALID', $exception->getMessage(), null, 422);
        }
    }

    public function updateParticipant(CommitteeParticipantRequest $request, string $id, string $participantId): JsonResponse
    {
        $session = $this->service->find((int) $id);

        return ApiResponseService::success(
            new CommitteeMeetingParticipantResource($this->service->updateParticipant($session, (int) $participantId, $request->validated())),
            'Participante atualizado com sucesso',
        );
    }

    public function destroyParticipant(string $id, string $participantId): JsonResponse
    {
        $this->service->deleteParticipant($this->service->find((int) $id), (int) $participantId);

        return ApiResponseService::noContent();
    }

    public function saveMinutes(CommitteeMinutesRequest $request, string $id): JsonResponse
    {
        $session = $this->service->find((int) $id);

        return ApiResponseService::success(
            new CommitteeMinutesResource($this->service->saveMinutes($session, $request->validated(), (int) $request->user()->id)),
            'Ata de reunião salva com sucesso',
        );
    }

    public function minutes(string $id): JsonResponse
    {
        $minutes = $this->service->minutes($this->service->find((int) $id));

        return ApiResponseService::success($minutes ? new CommitteeMinutesResource($minutes) : null);
    }

    public function approveMinutes(string $id): JsonResponse
    {
        $session = $this->service->find((int) $id);

        return ApiResponseService::success(
            new CommitteeMinutesResource($this->service->saveMinutes($session, ['approved' => true], (int) request()->user()->id)),
            'Ata aprovada com sucesso',
        );
    }
}
