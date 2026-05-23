<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\RedeemCouponRequest;
use App\Models\Tenant\Terreno;
use App\Services\ApiResponseService;
use App\Services\Billing\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CouponController extends Controller
{
    public function __construct(
        protected CouponService $couponService
    ) {}

    /**
     * Aplica um coupon na assinatura do tenant.
     *
     * POST /api/v1/tenant/billing/coupon/redeem
     */
    public function redeem(RedeemCouponRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Terreno::class);

        $tenant = tenancy()->tenant;

        $result = $this->couponService->redeem($tenant, $request->validated('code'));

        if (! $result['success']) {
            return match ($result['error']) {
                'COUPON_NOT_FOUND' => ApiResponseService::notFound('COUPON_NOT_FOUND'),
                'NO_ACTIVE_SUBSCRIPTION' => ApiResponseService::conflict('NO_ACTIVE_SUBSCRIPTION'),
                'COUPON_EXPIRED' => ApiResponseService::error('COUPON_EXPIRED', 'COUPON_EXPIRED', null, 422),
                'COUPON_FULLY_REDEEMED' => ApiResponseService::error('COUPON_FULLY_REDEEMED', 'COUPON_FULLY_REDEEMED', null, 422),
                'COUPON_INACTIVE' => ApiResponseService::error('COUPON_INACTIVE', 'COUPON_INACTIVE', null, 422),
                'COUPON_NOT_APPLICABLE_TO_PLAN' => ApiResponseService::error('COUPON_NOT_APPLICABLE', 'COUPON_NOT_APPLICABLE_TO_PLAN', null, 422),
                'COUPON_NOT_APPLICABLE_TO_TENANT' => ApiResponseService::error('COUPON_NOT_APPLICABLE', 'COUPON_NOT_APPLICABLE_TO_TENANT', null, 422),
                'COUPON_REDEEM_ERROR' => ApiResponseService::error('COUPON_REDEEM_ERROR', 'COUPON_REDEEM_ERROR', null, 500),
                default => ApiResponseService::error('UNKNOWN_ERROR', 'UNKNOWN_ERROR', null, 500),
            };
        }

        $coupon = $result['coupon'];

        return ApiResponseService::success([
            'coupon' => [
                'code' => $coupon->code,
                'name' => $coupon->name,
                'formatted_discount' => $coupon->formatted_discount,
            ],
        ], language()->t('COUPON_REDEEMED'));
    }
}
