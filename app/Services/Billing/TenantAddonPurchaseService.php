<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Common\TenantAddonPurchaseStatus;
use App\Models\Central\BillingAddon;
use App\Models\Central\Tenant;
use App\Models\Central\TenantAddonPurchase;
use App\Repositories\Contracts\TenantAddonPurchaseRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class TenantAddonPurchaseService
{
    public const CHECKOUT_PURPOSE = 'tenant_addon_purchase';

    public function __construct(
        private readonly TenantAddonPurchaseRepositoryInterface $repository,
        private readonly StripeCheckoutService $stripeCheckout,
        private readonly AiCreditService $aiCredits,
    ) {}

    /**
     * @param  array{unit_amount: int|null, currency: string, interval: string, price_type: string, formatted_price: string|null, is_purchasable: bool}  $price
     */
    public function createCheckout(
        Tenant $tenant,
        BillingAddon $addon,
        int $quantity,
        array $price,
    ): TenantAddonPurchase {
        if ($price['price_type'] !== 'one_time' || ! is_int($price['unit_amount'])) {
            throw new InvalidArgumentException('O add-on não possui um preço avulso válido.');
        }

        $stripePriceId = (string) $addon->stripe_price_id;

        return Cache::lock("tenant-addon-checkout:{$tenant->getKey()}:{$addon->getKey()}", 30)
            ->block(5, function () use ($tenant, $addon, $quantity, $price, $stripePriceId): TenantAddonPurchase {
                $purchase = $this->repository->create([
                    'tenant_id' => (string) $tenant->getKey(),
                    'billing_addon_id' => (int) $addon->getKey(),
                    'stripe_price_id' => $stripePriceId,
                    'quantity' => $quantity,
                    'unit_amount' => $price['unit_amount'],
                    'currency' => strtolower($price['currency']),
                    'status' => TenantAddonPurchaseStatus::PENDING,
                    'grant_snapshot' => $addon->definition,
                ]);

                try {
                    $session = $this->stripeCheckout->createAddonPaymentSession(
                        $tenant,
                        $addon,
                        $purchase,
                    );

                    $checkoutUrl = $session->url ?? null;
                    if (! is_string($checkoutUrl) || $checkoutUrl === '') {
                        throw new InvalidArgumentException(
                            'O Stripe não retornou uma URL válida para o Checkout do add-on.'
                        );
                    }

                    return $this->repository->update($purchase, [
                        'stripe_checkout_session_id' => (string) $session->id,
                        'checkout_url' => $checkoutUrl,
                        'expires_at' => is_numeric($session->expires_at ?? null)
                            ? now()->setTimestamp((int) $session->expires_at)
                            : now()->addHours(24),
                    ]);
                } catch (Throwable $exception) {
                    $this->repository->update($purchase, [
                        'status' => TenantAddonPurchaseStatus::FAILED,
                        'failed_at' => now(),
                    ]);

                    throw $exception;
                }
            });
    }

    /** @param array<string, mixed> $session */
    public function completeFromCheckoutSession(array $session): ?TenantAddonPurchase
    {
        $sessionId = $this->stringValue($session['id'] ?? null);
        if ($sessionId === null) {
            throw new InvalidArgumentException('Checkout de add-on sem identificador de sessão.');
        }

        $purchase = $this->repository->findByCheckoutSessionId($sessionId);
        if (! $purchase instanceof TenantAddonPurchase) {
            throw new InvalidArgumentException('Compra de add-on não encontrada para a sessão Stripe.');
        }

        $this->validateSession($purchase, $session);

        $paymentStatus = $this->stringValue($session['payment_status'] ?? null);
        if (! in_array($paymentStatus, ['paid', 'no_payment_required'], true)) {
            return null;
        }

        return Cache::lock('tenant-addon-purchase:'.$purchase->getKey(), 30)
            ->block(5, function () use ($purchase, $session, $sessionId): TenantAddonPurchase {
                $connection = $purchase->getConnectionName();

                return DB::connection($connection)->transaction(function () use ($session, $sessionId): TenantAddonPurchase {
                    $locked = $this->repository->findByCheckoutSessionId($sessionId, lockForUpdate: true);
                    if (! $locked instanceof TenantAddonPurchase) {
                        throw new InvalidArgumentException('Compra de add-on não encontrada durante a confirmação.');
                    }

                    if ($locked->status === TenantAddonPurchaseStatus::PAID) {
                        return $locked;
                    }

                    $paid = $this->repository->update($locked, [
                        'stripe_payment_intent_id' => $this->stringValue($session['payment_intent'] ?? null),
                        'amount_total' => is_numeric($session['amount_total'] ?? null)
                            ? (int) $session['amount_total']
                            : $locked->unit_amount * $locked->quantity,
                        'status' => TenantAddonPurchaseStatus::PAID,
                        'paid_at' => now(),
                        'failed_at' => null,
                    ]);

                    $this->aiCredits->grantFromPurchase($paid);

                    return $paid;
                });
            });
    }

    /** @param array<string, mixed> $session */
    public function markFailedFromCheckoutSession(array $session): void
    {
        $this->markTerminal($session, TenantAddonPurchaseStatus::FAILED);
    }

    /** @param array<string, mixed> $session */
    public function markExpiredFromCheckoutSession(array $session): void
    {
        $this->markTerminal($session, TenantAddonPurchaseStatus::EXPIRED);
    }

    /** @param array<string, mixed> $session */
    private function validateSession(TenantAddonPurchase $purchase, array $session): void
    {
        $expected = [
            'purpose' => self::CHECKOUT_PURPOSE,
            'purchase_id' => (string) $purchase->getKey(),
            'tenant_id' => $purchase->tenant_id,
            'addon_id' => (string) $purchase->billing_addon_id,
            'price_id' => $purchase->stripe_price_id,
        ];

        foreach ($expected as $key => $value) {
            if ($this->stringValue(data_get($session, "metadata.{$key}")) !== $value) {
                throw new InvalidArgumentException("Metadado '{$key}' do checkout de add-on é inválido.");
            }
        }

        $tenant = $purchase->tenant;
        if (! $tenant instanceof Tenant || $this->stringValue($session['customer'] ?? null) !== $tenant->stripe_id) {
            throw new InvalidArgumentException('Customer Stripe da compra de add-on é inválido.');
        }

        $currency = $this->stringValue($session['currency'] ?? null);
        if ($currency !== null && strtolower($currency) !== strtolower($purchase->currency)) {
            throw new InvalidArgumentException('Moeda do checkout de add-on é inválida.');
        }
    }

    /** @param array<string, mixed> $session */
    private function markTerminal(array $session, TenantAddonPurchaseStatus $status): void
    {
        $sessionId = $this->stringValue($session['id'] ?? null);
        if ($sessionId === null) {
            return;
        }

        $purchase = $this->repository->findByCheckoutSessionId($sessionId);
        if (! $purchase instanceof TenantAddonPurchase || $purchase->status === TenantAddonPurchaseStatus::PAID) {
            return;
        }

        $attributes = ['status' => $status];
        if ($status === TenantAddonPurchaseStatus::FAILED) {
            $attributes['failed_at'] = now();
        }

        $this->repository->update($purchase, $attributes);
    }

    private function stringValue(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (is_object($value) && isset($value->id) && is_string($value->id)) {
            return $value->id;
        }

        return null;
    }
}
