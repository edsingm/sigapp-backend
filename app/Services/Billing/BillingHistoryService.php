<?php

namespace App\Services\Billing;

use App\Models\Central\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Laravel\Cashier\Cashier;
use Stripe\StripeClient;

class BillingHistoryService
{
    private const INVOICE_EXPAND = ['data.lines.data.plan'];

    protected function stripe(): StripeClient
    {
        return Cashier::stripe();
    }

    /**
     * Lista invoices do tenant com filtros e paginação manual via Stripe.
     *
     * @return array{
     *     data: array<int, array<string, mixed>>,
     *     meta: array{
     *         total: int,
     *         per_page: int,
     *         current_page: int,
     *         has_more: bool,
     *         last_id: string|null
     *     }
     * }
     */
    public function getInvoices(
        Tenant $tenant,
        int $perPage = 10,
        int $page = 1,
        ?string $status = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): array {
        $stripeId = $this->stripeId($tenant);

        if ($stripeId === null) {
            return [
                'data' => [],
                'meta' => [
                    'total' => 0,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'has_more' => false,
                    'last_id' => null,
                ],
            ];
        }

        $cacheKey = sprintf(
            'billing_invoices:%s:%s:%s:%s:%d:%d',
            $stripeId,
            $status ?? 'all',
            $dateFrom ?? 'none',
            $dateTo ?? 'none',
            $page,
            $perPage,
        );

        return Cache::tags(['billing_history', 'billing_history_'.$tenant->id])
            ->remember($cacheKey, now()->addMinutes(5), function () use ($stripeId, $perPage, $page, $status, $dateFrom, $dateTo): array {
                /** @var array{customer: string, limit: int, expand: list<string>, status?: non-empty-string, created?: array{gte?: int, lte?: int}, starting_after?: string} $params */
                $params = [
                    'customer' => $stripeId,
                    'limit' => $perPage,
                    'expand' => self::INVOICE_EXPAND,
                ];

                if ($status !== null && $status !== '') {
                    $params['status'] = $status;
                }

                if ($dateFrom !== null && $dateFrom !== '') {
                    $params['created'] = ['gte' => Carbon::parse($dateFrom)->timestamp];
                }

                if ($dateTo !== null && $dateTo !== '') {
                    $params['created'] = $params['created'] ?? [];
                    $params['created']['lte'] = Carbon::parse($dateTo)->endOfDay()->timestamp;
                }

                if ($page > 1) {
                    $previousPageParams = [...$params, 'limit' => ($page - 1) * $perPage];
                    $previousInvoices = $this->stripe()->invoices->all($previousPageParams);
                    $lastItem = end($previousInvoices->data);
                    if ($lastItem !== false && is_string($lastItem->id) && $lastItem->id !== '') {
                        $params['starting_after'] = $lastItem->id;
                    }
                }

                $stripeInvoices = $this->stripe()->invoices->all($params);

                $data = [];
                foreach ($stripeInvoices->data as $invoice) {
                    $lines = [];
                    foreach ($invoice->lines->data ?? [] as $line) {
                        $lines[] = [
                            'description' => $line->description ?? $line->plan->nickname ?? null,
                            'amount' => $line->amount ?? 0,
                            'currency' => $line->currency ?? null,
                            'quantity' => $line->quantity ?? 1,
                        ];
                    }

                    $data[] = [
                        'id' => $invoice->id ?? null,
                        'number' => $invoice->number ?? null,
                        'status' => $invoice->status ?? null,
                        'amount_due' => $invoice->amount_due ?? null,
                        'amount_paid' => $invoice->amount_paid ?? null,
                        'amount_remaining' => $invoice->amount_remaining ?? null,
                        'currency' => $invoice->currency ?? null,
                        'hosted_invoice_url' => $invoice->hosted_invoice_url ?? null,
                        'invoice_pdf' => $invoice->invoice_pdf ?? null,
                        'created_at' => $invoice->created
                            ? Carbon::createFromTimestamp($invoice->created)->toIso8601String()
                            : null,
                        'period_start' => $invoice->period_start
                            ? Carbon::createFromTimestamp($invoice->period_start)->toIso8601String()
                            : null,
                        'period_end' => $invoice->period_end
                            ? Carbon::createFromTimestamp($invoice->period_end)->toIso8601String()
                            : null,
                        'lines' => $lines,
                    ];
                }

                return [
                    'data' => $data,
                    'meta' => [
                        'total' => $stripeInvoices->total_count ?? count($data),
                        'per_page' => $perPage,
                        'current_page' => $page,
                        'has_more' => (bool) $stripeInvoices->has_more,
                        'last_id' => $data !== [] ? end($data)['id'] : null,
                    ],
                ];
            });
    }

    /**
     * Busca uma invoice específica pelo ID e valida que pertence ao tenant.
     *
     * @return array<string, mixed>|null
     */
    public function findInvoice(Tenant $tenant, string $invoiceId): ?array
    {
        $stripeId = $this->stripeId($tenant);

        if ($stripeId === null) {
            return null;
        }

        $cacheKey = sprintf('billing_invoice:%s:%s', $stripeId, $invoiceId);

        return Cache::tags(['billing_history', 'billing_history_'.$tenant->id])
            ->remember($cacheKey, now()->addMinutes(5), function () use ($stripeId, $invoiceId): ?array {
                try {
                    $invoice = $this->stripe()->invoices->retrieve($invoiceId, [
                        'expand' => self::INVOICE_EXPAND,
                    ]);

                    if ((string) $invoice->customer !== $stripeId) {
                        return null;
                    }

                    $lines = [];
                    foreach ($invoice->lines->data ?? [] as $line) {
                        $lines[] = [
                            'description' => $line->description ?? $line->plan->nickname ?? null,
                            'amount' => $line->amount ?? 0,
                            'currency' => $line->currency ?? null,
                            'quantity' => $line->quantity ?? 1,
                        ];
                    }

                    return [
                        'id' => $invoice->id ?? null,
                        'number' => $invoice->number ?? null,
                        'status' => $invoice->status ?? null,
                        'amount_due' => $invoice->amount_due ?? null,
                        'amount_paid' => $invoice->amount_paid ?? null,
                        'amount_remaining' => $invoice->amount_remaining ?? null,
                        'currency' => $invoice->currency ?? null,
                        'hosted_invoice_url' => $invoice->hosted_invoice_url ?? null,
                        'invoice_pdf' => $invoice->invoice_pdf ?? null,
                        'created_at' => $invoice->created
                            ? Carbon::createFromTimestamp($invoice->created)->toIso8601String()
                            : null,
                        'period_start' => $invoice->period_start
                            ? Carbon::createFromTimestamp($invoice->period_start)->toIso8601String()
                            : null,
                        'period_end' => $invoice->period_end
                            ? Carbon::createFromTimestamp($invoice->period_end)->toIso8601String()
                            : null,
                        'lines' => $lines,
                    ];
                } catch (\Exception) {
                    return null;
                }
            });
    }

    /**
     * Retorna a URL do PDF de uma invoice para redirect.
     */
    public function getInvoicePdfUrl(Tenant $tenant, string $invoiceId): ?string
    {
        $invoice = $this->findInvoice($tenant, $invoiceId);

        return $invoice['invoice_pdf'] ?? $invoice['hosted_invoice_url'] ?? null;
    }

    /**
     * Invalida o cache de billing history do tenant.
     */
    public function invalidateCache(Tenant $tenant): void
    {
        Cache::tags(['billing_history', 'billing_history_'.$tenant->id])->flush();
    }

    private function stripeId(Tenant $tenant): ?string
    {
        $stripeId = $tenant->getAttribute('stripe_id');

        return is_string($stripeId) && $stripeId !== '' ? $stripeId : null;
    }
}
