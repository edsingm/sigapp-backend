<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\PurchaseAddonRequest;
use App\Http\Requests\Tenant\UpdateAddonQuantityRequest;
use App\Http\Resources\TenantAddonSubscriptionResource;
use App\Http\Resources\TenantBillingAddonResource;
use App\Models\Central\Tenant;
use App\Models\Central\TenantAddonPurchase;
use App\Services\ApiResponseService;
use App\Services\Billing\TenantAddonService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Laravel\Cashier\Exceptions\IncompletePayment;

class TenantAddonController extends Controller
{
    public function __construct(
        private readonly TenantAddonService $service,
    ) {}

    public function index(): JsonResponse
    {
        $tenant = tenancy()->tenant;
        if (! $tenant instanceof Tenant) {
            return ApiResponseService::serverError('TENANT_CONTEXT_NOT_AVAILABLE');
        }

        return ApiResponseService::success(
            TenantBillingAddonResource::collection($this->service->catalog($tenant)),
            'DATA_RETRIEVED_SUCCESSFULLY',
        );
    }

    public function mine(): JsonResponse
    {
        $tenant = tenancy()->tenant;
        if (! $tenant instanceof Tenant) {
            return ApiResponseService::serverError('TENANT_CONTEXT_NOT_AVAILABLE');
        }

        return ApiResponseService::success(
            TenantAddonSubscriptionResource::collection($this->service->mine($tenant)),
            'DATA_RETRIEVED_SUCCESSFULLY',
        );
    }

    public function purchase(PurchaseAddonRequest $request): JsonResponse
    {
        $tenant = tenancy()->tenant;
        if (! $tenant instanceof Tenant) {
            return ApiResponseService::serverError('TENANT_CONTEXT_NOT_AVAILABLE');
        }

        try {
            $record = $this->service->purchase(
                $tenant,
                (string) $request->validated('addon_slug'),
                (int) $request->validated('quantity'),
            );
        } catch (IncompletePayment $exception) {
            return ApiResponseService::error(
                'PAYMENT_ACTION_REQUIRED',
                'PAYMENT_ACTION_REQUIRED',
                $this->incompletePaymentDetails($exception),
                402,
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponseService::error('BILLING_ADDON_PURCHASE_ERROR', $exception->getMessage(), null, 422);
        }

        if ($record instanceof TenantAddonPurchase) {
            return ApiResponseService::created([
                'purchase_mode' => 'one_time',
                'purchase_id' => $record->getKey(),
                'checkout_session_id' => $record->stripe_checkout_session_id,
                'checkout_url' => $record->checkout_url,
                'status' => $record->status->value,
            ]);
        }

        return ApiResponseService::created(new TenantAddonSubscriptionResource($record));
    }

    public function update(UpdateAddonQuantityRequest $request, int $addon): JsonResponse
    {
        $tenant = tenancy()->tenant;
        if (! $tenant instanceof Tenant) {
            return ApiResponseService::serverError('TENANT_CONTEXT_NOT_AVAILABLE');
        }

        try {
            $record = $this->service->updateQuantity(
                $tenant,
                $addon,
                (int) $request->validated('quantity'),
            );
        } catch (IncompletePayment $exception) {
            return ApiResponseService::error(
                'PAYMENT_ACTION_REQUIRED',
                'PAYMENT_ACTION_REQUIRED',
                $this->incompletePaymentDetails($exception),
                402,
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponseService::error('BILLING_ADDON_UPDATE_ERROR', $exception->getMessage(), null, 422);
        }

        return ApiResponseService::success(new TenantAddonSubscriptionResource($record), 'SUCCESS_OPERATION');
    }

    public function cancel(int $addon): JsonResponse
    {
        $tenant = tenancy()->tenant;
        if (! $tenant instanceof Tenant) {
            return ApiResponseService::serverError('TENANT_CONTEXT_NOT_AVAILABLE');
        }

        try {
            $record = $this->service->cancel($tenant, $addon);
        } catch (InvalidArgumentException $exception) {
            return ApiResponseService::error('BILLING_ADDON_CANCEL_ERROR', $exception->getMessage(), null, 422);
        }

        return ApiResponseService::success(new TenantAddonSubscriptionResource($record), 'SUCCESS_OPERATION');
    }

    /** @return array<string, string|null> */
    private function incompletePaymentDetails(IncompletePayment $exception): array
    {
        try {
            $paymentIntent = $exception->payment->asStripePaymentIntent();

            return [
                'payment_intent_id' => is_string($paymentIntent->id ?? null) ? $paymentIntent->id : null,
                'client_secret' => is_string($paymentIntent->client_secret ?? null)
                    ? $paymentIntent->client_secret
                    : null,
                'status' => is_string($paymentIntent->status ?? null) ? $paymentIntent->status : null,
            ];
        } catch (\Throwable) {
            return [];
        }
    }
}
