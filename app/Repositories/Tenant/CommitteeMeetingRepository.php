<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Tenant\ComiteMeetingAgendaItem;
use App\Models\Tenant\ComiteMeetingMinutes;
use App\Models\Tenant\ComiteMeetingParticipant;
use App\Models\Tenant\ComiteMeetingSession;
use App\Models\Tenant\ComiteRevisao;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

class CommitteeMeetingRepository
{
    /** @param array<string, mixed> $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = ComiteMeetingSession::query()
            ->with(['comiteRevisao.terreno', 'chair', 'agendaItems', 'participants.user', 'minutes'])
            ->when($filters['comite_revisao_id'] ?? null, fn ($query, $id) => $query->where('comite_revisao_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status));

        return $query->latest('scheduled_at')->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function findSession(int $id): ComiteMeetingSession
    {
        return ComiteMeetingSession::query()
            ->with(['comiteRevisao.terreno', 'chair', 'agendaItems', 'participants.user', 'minutes'])
            ->findOrFail($id);
    }

    public function reviewExists(int $id): bool
    {
        return ComiteRevisao::query()->whereKey($id)->exists();
    }

    public function createSession(array $data): ComiteMeetingSession
    {
        return ComiteMeetingSession::create($data);
    }

    public function updateSession(ComiteMeetingSession $session, array $data): ComiteMeetingSession
    {
        $session->update($data);

        return $this->findSession($session->id);
    }

    /** @return array<int, ComiteMeetingAgendaItem> */
    public function agenda(ComiteMeetingSession $session): array
    {
        return $session->agendaItems()->get()->all();
    }

    public function createAgendaItem(ComiteMeetingSession $session, array $data): ComiteMeetingAgendaItem
    {
        return $session->agendaItems()->create($data);
    }

    public function findAgendaItem(ComiteMeetingSession $session, int $id): ComiteMeetingAgendaItem
    {
        return $session->agendaItems()->findOrFail($id);
    }

    public function updateAgendaItem(ComiteMeetingAgendaItem $item, array $data): ComiteMeetingAgendaItem
    {
        $item->update($data);

        return $item->fresh() ?? throw new RuntimeException('Item de pauta não encontrado após atualização.');
    }

    public function deleteAgendaItem(ComiteMeetingAgendaItem $item): void
    {
        $item->delete();
    }

    /** @param array<int, int> $itemIds @return Collection<int, ComiteMeetingAgendaItem> */
    public function reorderAgenda(ComiteMeetingSession $session, array $itemIds): Collection
    {
        $items = $session->agendaItems()->whereKey($itemIds)->get();
        if ($items->count() !== count($itemIds) || count(array_unique($itemIds)) !== count($itemIds)) {
            throw new RuntimeException('A ordenação contém itens que não pertencem à reunião.');
        }

        foreach ($itemIds as $position => $itemId) {
            $items->firstWhere('id', $itemId)?->update(['position' => $position]);
        }

        return $session->agendaItems()->get();
    }

    public function createParticipant(ComiteMeetingSession $session, array $data): ComiteMeetingParticipant
    {
        return $session->participants()->create($data)->load('user');
    }

    /** @return array<int, ComiteMeetingParticipant> */
    public function participants(ComiteMeetingSession $session): array
    {
        return $session->participants()->with('user')->get()->all();
    }

    public function findParticipant(ComiteMeetingSession $session, int $id): ComiteMeetingParticipant
    {
        return $session->participants()->findOrFail($id);
    }

    public function updateParticipant(ComiteMeetingParticipant $participant, array $data): ComiteMeetingParticipant
    {
        $participant->update($data);

        return $participant->fresh('user') ?? throw new RuntimeException('Participante não encontrado após atualização.');
    }

    public function deleteParticipant(ComiteMeetingParticipant $participant): void
    {
        $participant->delete();
    }

    public function upsertMinutes(ComiteMeetingSession $session, array $data): ComiteMeetingMinutes
    {
        return $session->minutes()->updateOrCreate([], $data)->load('approvedBy');
    }
}
