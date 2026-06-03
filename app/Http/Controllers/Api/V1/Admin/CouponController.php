<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCouponRequest;
use App\Http\Requests\Admin\UpdateCouponRequest;
use App\Http\Resources\Admin\CouponResource;
use App\Models\Central\Coupon;
use App\Services\ApiResponseService;
use App\Services\Billing\CouponService;
use Illuminate\Http\JsonResponse;

class CouponController extends Controller
{
    public function __construct(
        private readonly CouponService $couponService
    ) {}

    /**
     * Lista todos os coupons.
     *
     * GET /api/v1/admin/coupons
     */
    public function index(): JsonResponse
    {
        $coupons = $this->couponService->list(20);

        return ApiResponseService::paginated(
            $coupons->through(function ($coupon) {
                return new CouponResource($coupon);
            }),
            language()->t('COUPONS_RETRIEVED')
        );
    }

    /**
     * Exibe um coupon específico.
     *
     * GET /api/v1/admin/coupons/{coupon}
     */
    public function show(Coupon $coupon): JsonResponse
    {
        return ApiResponseService::success(
            new CouponResource($coupon),
            language()->t('COUPON_RETRIEVED')
        );
    }

    /**
     * Cria um novo coupon.
     *
     * POST /api/v1/admin/coupons
     */
    public function store(StoreCouponRequest $request): JsonResponse
    {
        $coupon = $this->couponService->create($request->validated());

        return ApiResponseService::created(
            new CouponResource($coupon),
            language()->t('COUPON_CREATED')
        );
    }

    /**
     * Atualiza um coupon.
     *
     * PUT /api/v1/admin/coupons/{coupon}
     */
    public function update(UpdateCouponRequest $request, Coupon $coupon): JsonResponse
    {
        $coupon = $this->couponService->update($coupon, $request->validated());

        return ApiResponseService::success(
            new CouponResource($coupon),
            language()->t('COUPON_UPDATED')
        );
    }

    /**
     * Desativa um coupon.
     *
     * DELETE /api/v1/admin/coupons/{coupon}
     */
    public function destroy(Coupon $coupon): JsonResponse
    {
        $this->couponService->deactivate($coupon);

        return ApiResponseService::success(null, language()->t('COUPON_DEACTIVATED'));
    }
}
