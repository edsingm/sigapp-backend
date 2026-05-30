<?php

namespace App\Services\Billing;

use App\Models\Central\Coupon;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Traits\LogsAudit;
use Laravel\Cashier\Cashier;
use Stripe\StripeClient;

class CouponService
{
    use LogsAudit;

    protected function stripe(): StripeClient
    {
        return Cashier::stripe();
    }

    /**
     * Cria um coupon no Stripe e persiste localmente.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Coupon
    {
        $stripeCoupon = $this->stripe()->coupons->create([
            'name' => $data['name'],
            'duration' => $data['duration'] ?? 'once',
            ...($data['type'] === 'percent'
                ? ['percent_off' => (int) $data['percent_off']]
                : [
                    'amount_off' => (int) $data['amount_off'],
                    'currency' => $data['currency'] ?? 'brl',
                ]
            ),
            'max_redemptions' => $data['max_redemptions'] ?? null,
            'redeem_by' => isset($data['redeem_by'])
                ? strtotime($data['redeem_by'])
                : null,
            'metadata' => [
                'local_coupon_code' => $data['code'],
            ],
        ]);

        $coupon = Coupon::create([
            'stripe_coupon_id' => $stripeCoupon->id,
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'amount_off' => $data['type'] === 'fixed' ? (int) $data['amount_off'] : null,
            'percent_off' => $data['type'] === 'percent' ? (int) $data['percent_off'] : null,
            'currency' => $data['type'] === 'fixed' ? ($data['currency'] ?? 'brl') : null,
            'max_redemptions' => $data['max_redemptions'] ?? null,
            'redeem_by' => $data['redeem_by'] ?? null,
            'expires_after_first_redemption' => (bool) ($data['expires_after_first_redemption'] ?? false),
            'is_active' => true,
            'applies_to_plans' => $data['applies_to_plans'] ?? null,
            'applies_to_tenants' => $data['applies_to_tenants'] ?? null,
        ]);

        $this->audit('coupon.created', "Coupon '{$coupon->code}' criado.", [
            'coupon_id' => $coupon->id,
            'stripe_coupon_id' => $stripeCoupon->id,
            'type' => $coupon->type,
        ]);

        return $coupon;
    }

    /**
     * Atualiza um coupon local e no Stripe.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Coupon $coupon, array $data): Coupon
    {
        if ($coupon->stripe_coupon_id) {
            $this->stripe()->coupons->update($coupon->stripe_coupon_id, [
                'name' => $data['name'] ?? $coupon->name,
            ]);
        }

        $coupon->update([
            'name' => $data['name'] ?? $coupon->name,
            'description' => $data['description'] ?? $coupon->description,
            'max_redemptions' => $data['max_redemptions'] ?? $coupon->max_redemptions,
            'redeem_by' => $data['redeem_by'] ?? $coupon->redeem_by,
            'applies_to_plans' => $data['applies_to_plans'] ?? $coupon->applies_to_plans,
            'applies_to_tenants' => $data['applies_to_tenants'] ?? $coupon->applies_to_tenants,
        ]);

        $this->audit('coupon.updated', "Coupon '{$coupon->code}' atualizado.", [
            'coupon_id' => $coupon->id,
        ]);

        return $coupon;
    }

    /**
     * Desativa um coupon (soft delete + desativa no Stripe).
     */
    public function deactivate(Coupon $coupon): void
    {
        if ($coupon->stripe_coupon_id) {
            $this->stripe()->coupons->delete($coupon->stripe_coupon_id);
        }

        $coupon->update(['is_active' => false]);
        $coupon->delete();

        $this->audit('coupon.deactivated', "Coupon '{$coupon->code}' desativado.", [
            'coupon_id' => $coupon->id,
        ]);
    }

    /**
     * Valida se o coupon pode ser aplicado ao tenant/plano.
     *
     * @return array{valid: bool, error: string|null}
     */
    public function validateForTenant(Coupon $coupon, Tenant $tenant, ?Plan $plan = null): array
    {
        if (! $coupon->isAvailable()) {
            if ($coupon->isExpired()) {
                return ['valid' => false, 'error' => 'COUPON_EXPIRED'];
            }
            if ($coupon->isFullyRedeemed()) {
                return ['valid' => false, 'error' => 'COUPON_FULLY_REDEEMED'];
            }

            return ['valid' => false, 'error' => 'COUPON_INACTIVE'];
        }

        if ($plan !== null && ! $coupon->appliesToPlan($plan)) {
            return ['valid' => false, 'error' => 'COUPON_NOT_APPLICABLE_TO_PLAN'];
        }

        if (! $coupon->appliesToTenant($tenant)) {
            return ['valid' => false, 'error' => 'COUPON_NOT_APPLICABLE_TO_TENANT'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Aplica um coupon na assinatura Stripe do tenant.
     *
     * @return array{success: bool, error: string|null, coupon: Coupon|null}
     */
    public function redeem(Tenant $tenant, string $code): array
    {
        $coupon = Coupon::query()->where('code', strtoupper(trim($code)))->first();

        if ($coupon === null) {
            return ['success' => false, 'error' => 'COUPON_NOT_FOUND', 'coupon' => null];
        }

        $plan = $tenant->plan()->first();
        $validation = $this->validateForTenant($coupon, $tenant, $plan);

        if (! $validation['valid']) {
            return ['success' => false, 'error' => $validation['error'], 'coupon' => $coupon];
        }

        $stripeSubscriptionId = $tenant->getAttribute('stripe_subscription_id');
        if (! is_string($stripeSubscriptionId) || $stripeSubscriptionId === '') {
            return ['success' => false, 'error' => 'NO_ACTIVE_SUBSCRIPTION', 'coupon' => $coupon];
        }

        try {
            $this->stripe()->subscriptions->update($stripeSubscriptionId, [
                'coupon' => $coupon->stripe_coupon_id,
            ]);

            Coupon::query()->whereKey($coupon->getKey())->increment('times_redeemed');

            $this->audit('coupon.redeemed', "Coupon '{$coupon->code}' aplicado ao tenant.", [
                'coupon_id' => $coupon->id,
                'tenant_id' => $tenant->id,
                'tenant_slug' => (string) $tenant->getAttribute('slug'),
            ]);

            return ['success' => true, 'error' => null, 'coupon' => $coupon];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'COUPON_REDEEM_ERROR', 'coupon' => $coupon];
        }
    }

    /**
     * Busca coupon por código (case-insensitive).
     */
    public function findByCode(string $code): ?Coupon
    {
        return Coupon::query()->where('code', strtoupper(trim($code)))->first();
    }

    /**
     * Incrementa o contador de redemptions a partir do webhook.
     */
    public function incrementRedemption(string $stripeCouponId): void
    {
        $coupon = Coupon::query()->where('stripe_coupon_id', $stripeCouponId)->first();

        if ($coupon) {
            Coupon::query()->whereKey($coupon->getKey())->increment('times_redeemed');
        }
    }
}
