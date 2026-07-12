<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\ComiteMeetingAgendaItem;
use App\Models\Tenant\ComiteMeetingMinutes;
use App\Models\Tenant\ComiteMeetingParticipant;
use App\Models\Tenant\ComiteMeetingSession;
use App\Repositories\Tenant\CommitteeMeetingRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

class CommitteeMeetingService
{
    public function __construct(
        private readonly CommitteeMeetingRepository $repository,
    ) {}

    /** @param array<string, mixed> $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginate($filters);
    }

    public function find(int $id): ComiteMeetingSession
    {
        return $this->repository->findSession($id);
    }

    public function create(array $data, int $actorId): ComiteMeetingSession
    {
        $reviewId = (int) $data['comite_revisao_id'];
        if (! $this->repository->reviewExists($reviewId)) {
            throw new RuntimeException('Revisão de comitê não encontrada.');
        }

        return $this->find($this->repository->createSession([
            ...$data,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ])->id);
    }

    public function update(ComiteMeetingSession $session, array $data, int $actorId): ComiteMeetingSession
    {
        return $this->repository->updateSession($session, [
            ...$data,
            'updated_by' => $actorId,
        ]);
    }

    public function start(ComiteMeetingSession $session, int $actorId): ComiteMeetingSession
    {
        return $this->update($session, ['status' => 'in_progress', 'started_at' => now()], $actorId);
    }

    public function finish(ComiteMeetingSession $session, int $actorId): ComiteMeetingSession
    {
        return $this->update($session, ['status' => 'closed', 'ended_at' => now()], $actorId);
    }

    /** @return Collection<int, ComiteMeetingAgendaItem> */
    public function agenda(ComiteMeetingSession $session): Collection
    {
        return new Collection($this->repository->agenda($session));
    }

    public function createAgendaItem(ComiteMeetingSession $session, array $data): ComiteMeetingAgendaItem
    {
        return $this->repository->createAgendaItem($session, $data);
    }

    public function updateAgendaItem(ComiteMeetingSession $session, int $itemId, array $data): ComiteMeetingAgendaItem
    {
        return $this->repository->updateAgendaItem($this->repository->findAgendaItem($session, $itemId), $data);
    }

    /** @param array<int, int> $itemIds @return Collection<int, ComiteMeetingAgendaItem> */
    public function reorderAgenda(ComiteMeetingSession $session, array $itemIds): Collection
    {
        return $this->repository->reorderAgenda($session, $itemIds);
    }

    public function deleteAgendaItem(ComiteMeetingSession $session, int $itemId): void
    {
        $this->repository->deleteAgendaItem($this->repository->findAgendaItem($session, $itemId));
    }

    public function createParticipant(ComiteMeetingSession $session, array $data): ComiteMeetingParticipant
    {
        if (empty($data['user_id']) && empty($data['email'])) {
            throw new RuntimeException('Informe um usuário ou um e-mail para o participante.');
        }

        return $this->repository->createParticipant($session, $data);
    }

    /** @return Collection<int, ComiteMeetingParticipant> */
    public function participants(ComiteMeetingSession $session): Collection
    {
        return new Collection($this->repository->participants($session));
    }

    public function updateParticipant(ComiteMeetingSession $session, int $participantId, array $data): ComiteMeetingParticipant
    {
        return $this->repository->updateParticipant($this->repository->findParticipant($session, $participantId), $data);
    }

    public function deleteParticipant(ComiteMeetingSession $session, int $participantId): void
    {
        $this->repository->deleteParticipant($this->repository->findParticipant($session, $participantId));
    }

    public function saveMinutes(ComiteMeetingSession $session, array $data, int $actorId): ComiteMeetingMinutes
    {
        if (($data['approved'] ?? false) === true) {
            $data['approved_by'] = $actorId;
            $data['approved_at'] = now();
        }

        return $this->repository->upsertMinutes($session, $data);
    }

    public function minutes(ComiteMeetingSession $session): ?ComiteMeetingMinutes
    {
        return $session->minutes;
    }
}
