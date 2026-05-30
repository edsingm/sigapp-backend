<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\InvoiceResource;
use App\Models\Central\Tenant;
use App\Models\Tenant\Terreno;
use App\Services\ApiResponseService;
use App\Services\Billing\BillingHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BillingHistoryController extends Controller
{
    public function __construct(
        protected BillingHistoryService $billingHistoryService
    ) {}

    /**
     * Lista o histórico de invoices do tenant.
     *
     * GET /api/v1/tenant/billing/history
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Terreno::class);

        $tenant = tenancy()->tenant;
        if (! $tenant instanceof Tenant) {
            return ApiResponseService::serverError('TENANT_CONTEXT_NOT_AVAILABLE');
        }

        $perPage = (int) $request->query('per_page', '10');
        $page = (int) $request->query('page', '1');
        $perPage = min(max($perPage, 1), 50);
        $page = max($page, 1);
        $status = $request->has('status') ? $request->string('status')->toString() : null;
        $dateFrom = $request->has('date_from') ? $request->string('date_from')->toString() : null;
        $dateTo = $request->has('date_to') ? $request->string('date_to')->toString() : null;

        $invoices = $this->billingHistoryService->getInvoices(
            $tenant,
            perPage: $perPage,
            page: $page,
            status: $status,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
        );

        return ApiResponseService::success([
            'data' => InvoiceResource::collection(collect($invoices['data'])),
            'meta' => $invoices['meta'],
        ], language()->t('BILLING_HISTORY_RETRIEVED'));
    }

    /**
     * Obtém detalhes de uma invoice específica.
     *
     * GET /api/v1/tenant/billing/invoices/{invoiceId}
     */
    public function show(string $invoiceId): JsonResponse
    {
        Gate::authorize('viewAny', Terreno::class);

        $tenant = tenancy()->tenant;
        if (! $tenant instanceof Tenant) {
            return ApiResponseService::serverError('TENANT_CONTEXT_NOT_AVAILABLE');
        }

        $invoice = $this->billingHistoryService->findInvoice($tenant, $invoiceId);

        if ($invoice === null) {
            return ApiResponseService::notFound('INVOICE_NOT_FOUND');
        }

        return ApiResponseService::success(
            new InvoiceResource((object) $invoice),
            language()->t('INVOICE_RETRIEVED')
        );
    }

    /**
     * Redireciona para o PDF da invoice.
     *
     * GET /api/v1/tenant/billing/invoices/{invoiceId}/pdf
     */
    public function downloadPdf(string $invoiceId): RedirectResponse|JsonResponse
    {
        Gate::authorize('viewAny', Terreno::class);

        $tenant = tenancy()->tenant;
        if (! $tenant instanceof Tenant) {
            return ApiResponseService::serverError('TENANT_CONTEXT_NOT_AVAILABLE');
        }

        $pdfUrl = $this->billingHistoryService->getInvoicePdfUrl($tenant, $invoiceId);

        if ($pdfUrl === null) {
            return ApiResponseService::notFound('INVOICE_NOT_FOUND');
        }

        return redirect()->away($pdfUrl);
    }
}
