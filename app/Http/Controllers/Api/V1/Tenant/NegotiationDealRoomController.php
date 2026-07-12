<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ContractConditionRequest;
use App\Http\Requests\Tenant\NegotiationApprovalRequest;
use App\Http\Requests\Tenant\NegotiationOfferRequest;
use App\Http\Resources\Tenant\ContratoCondicaoResource;
use App\Http\Resources\Tenant\NegociacaoAprovacaoResource;
use App\Http\Resources\Tenant\NegociacaoOfertaResource;
use App\Services\ApiResponseService;
use App\Services\Tenant\NegotiationDealRoomService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class NegotiationDealRoomController extends Controller
{
    public function __construct(
        private readonly NegotiationDealRoomService $dealRoom,
    ) {}

    public function offers(string $negotiationId): JsonResponse
    {
        $negotiation = $this->dealRoom->negotiation((int) $negotiationId);

        return ApiResponseService::success(NegociacaoOfertaResource::collection($this->dealRoom->offers($negotiation)));
    }

    public function storeOffer(NegotiationOfferRequest $request, string $negotiationId): JsonResponse
    {
        try {
            $negotiation = $this->dealRoom->negotiation((int) $negotiationId);

            return ApiResponseService::created(
                new NegociacaoOfertaResource($this->dealRoom->createOffer($negotiation, $request->validated(), $request->user())),
                'Oferta criada com sucesso',
            );
        } catch (RuntimeException $exception) {
            return ApiResponseService::error('DEAL_ROOM_INVALID', $exception->getMessage(), null, 422);
        }
    }

    public function showOffer(string $negotiationId, string $offerId): JsonResponse
    {
        $negotiation = $this->dealRoom->negotiation((int) $negotiationId);

        return ApiResponseService::success(new NegociacaoOfertaResource($this->dealRoom->offer($negotiation, (int) $offerId)));
    }

    public function acceptOffer(string $negotiationId, string $offerId): JsonResponse
    {
        try {
            $negotiation = $this->dealRoom->negotiation((int) $negotiationId);

            return ApiResponseService::success(
                new NegociacaoOfertaResource($this->dealRoom->acceptOffer($negotiation, (int) $offerId, request()->user())),
                'Oferta aceita com sucesso',
            );
        } catch (RuntimeException $exception) {
            return ApiResponseService::error('DEAL_ROOM_INVALID', $exception->getMessage(), null, 422);
        }
    }

    public function rejectOffer(string $negotiationId, string $offerId): JsonResponse
    {
        $negotiation = $this->dealRoom->negotiation((int) $negotiationId);

        return ApiResponseService::success(
            new NegociacaoOfertaResource($this->dealRoom->rejectOffer($negotiation, (int) $offerId, request()->user())),
            'Oferta rejeitada com sucesso',
        );
    }

    public function approvals(string $negotiationId): JsonResponse
    {
        $negotiation = $this->dealRoom->negotiation((int) $negotiationId);

        return ApiResponseService::success(NegociacaoAprovacaoResource::collection($this->dealRoom->approvals($negotiation)));
    }

    public function storeApproval(NegotiationApprovalRequest $request, string $negotiationId): JsonResponse
    {
        $negotiation = $this->dealRoom->negotiation((int) $negotiationId);

        return ApiResponseService::created(
            new NegociacaoAprovacaoResource($this->dealRoom->saveApproval($negotiation, $request->validated(), $request->user())),
            'Aprovação registrada com sucesso',
        );
    }

    public function conditions(string $contractId): JsonResponse
    {
        $contract = $this->dealRoom->contract((int) $contractId);

        return ApiResponseService::success(ContratoCondicaoResource::collection($this->dealRoom->conditions($contract)));
    }

    public function storeCondition(ContractConditionRequest $request, string $contractId): JsonResponse
    {
        $contract = $this->dealRoom->contract((int) $contractId);

        return ApiResponseService::created(
            new ContratoCondicaoResource($this->dealRoom->createCondition($contract, $request->validated())),
            'Condição contratual criada com sucesso',
        );
    }

    public function updateCondition(ContractConditionRequest $request, string $contractId, string $conditionId): JsonResponse
    {
        $contract = $this->dealRoom->contract((int) $contractId);

        return ApiResponseService::success(
            new ContratoCondicaoResource($this->dealRoom->updateCondition($contract, (int) $conditionId, $request->validated())),
            'Condição contratual atualizada com sucesso',
        );
    }
}
