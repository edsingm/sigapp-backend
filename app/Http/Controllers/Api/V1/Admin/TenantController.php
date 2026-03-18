<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Central\Tenant;
use App\Services\ApiResponseService;
use App\Services\Billing\TenantBillingService;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function __construct(
        protected TenantBillingService $billingService
    ) {
    }

    /**
     * Lista todos os tenants com paginação e filtros.
     */
    public function index(Request $request)
    {
        $query = Tenant::with(['plan']);

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('admin_email', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->get('status') !== 'all') {
            $query->where('status', $request->get('status'));
        }

        $tenants = $query->latest()->paginate(15);

        return ApiResponseService::success($tenants, 'Lista de tenants recuperada');
    }

    /**
     * Obtém detalhes de um tenant específico.
     */
    public function show($id)
    {
        $tenant = Tenant::with(['plan'])->findOrFail($id);

        // Obtém estatísticas de uso
        $stats = [
            'users_count' => 0,
            'terrenos_count' => 0,
            'products_count' => 0,
            'storage_used' => 0 // Espaço reservado
        ];

        try {
            // Precisamos mudar para o contexto do tenant para obter essas contagens
            tenancy()->initialize($tenant);

            $stats['users_count'] = \App\Models\Tenant\User::count();
            $stats['terrenos_count'] = \App\Models\Tenant\Terreno::count();
            $stats['products_count'] = \App\Models\Tenant\Produto::count();
        } catch (\Exception $e) {
            // Se o banco de dados não estiver criado ou acessível, retornamos 0
            // Logar erro se necessário: \Log::error("Failed to get tenant stats: " . $e->getMessage());
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }

        $data = $tenant->toArray();
        $data['plan'] = $tenant->plan ? (new PlanResource($tenant->plan))->resolve() : null;
        $data['stats'] = $stats;
        $data['on_trial'] = $tenant->onTrial();
        $data['trial_ended'] = $tenant->trialEnded();

        // Dados Financeiros (Stripe/Cashier)
        $finance = [
            'has_payment_method' => false,
            'card_brand' => null,
            'card_last4' => null,
            'card_exp_month' => null,
            'card_exp_year' => null,
            'invoices' => [],
            'subscription_status' => $tenant->status, // Padrão para status local
            'renews_at' => null,
            'canceled_at' => null,
            'error' => null
        ];

        try {
            if ($tenant->stripe_id) {
                $finance['has_payment_method'] = $tenant->hasDefaultPaymentMethod();

                if ($finance['has_payment_method']) {
                    $paymentMethod = $tenant->defaultPaymentMethod();
                    if ($paymentMethod) {
                        $finance['card_brand'] = $paymentMethod->card->brand;
                        $finance['card_last4'] = $paymentMethod->card->last4;
                        $finance['card_exp_month'] = $paymentMethod->card->exp_month;
                        $finance['card_exp_year'] = $paymentMethod->card->exp_year;
                    }
                }

                if ($subscription = $tenant->subscription('default')) {
                    $finance['subscription_status'] = $subscription->stripe_status;
                    $finance['renews_at'] = $subscription->ends_at ? null : $subscription->asStripeSubscription()->current_period_end; // Timestamp
                    $finance['canceled_at'] = $subscription->ends_at;
                }

                // Obtém as últimas 5 faturas
                $invoices = $tenant->invoicesIncludingPending(['limit' => 5]);
                foreach ($invoices as $invoice) {
                    $finance['invoices'][] = [
                        'id' => $invoice->id,
                        'number' => $invoice->number,
                        'total' => $invoice->total(), // String formatada
                        'status' => $invoice->status,
                        'created_at' => $invoice->created, // Timestamp
                        'pdf' => $invoice->hosted_invoice_url, // URL para visualizar fatura
                        'download' => $invoice->invoice_pdf, // URL para baixar PDF
                    ];
                }
            } else {
                // Ainda não é um tenant do Stripe (Trial Local ou Gratuito)
                if ($tenant->onTrial()) {
                    $finance['subscription_status'] = 'trialing';
                    $finance['renews_at'] = $tenant->trial_ends_at ? $tenant->trial_ends_at->timestamp : null;
                }
            }
        } catch (\Exception $e) {
            // Logar erro do Stripe mas não falhar a requisição
            // \Log::error("Stripe error for tenant {$tenant->id}: " . $e->getMessage());
            $finance['error'] = 'Erro ao carregar dados do Stripe: ' . $e->getMessage();
        }

        $data['finance'] = $finance;

        return ApiResponseService::success($data, 'Detalhes do tenant recuperados');
    }

    /**
     * Ativa um tenant.
     */
    public function activate(Request $request, $id)
    {
        $tenant = Tenant::findOrFail($id);

        if ($tenant->isActive()) {
            return ApiResponseService::error('ALREADY_ACTIVE', 'Tenant já está ativo');
        }

        try {
            $reconciliation = $this->billingService->reconcileTenantActivation($tenant);
        } catch (\Exception $e) {
            return ApiResponseService::error(
                'BILLING_RECONCILIATION_ERROR',
                'UNKNOWN_ERROR',
                app()->environment('local') ? $e->getMessage() : null,
                500
            );
        }

        if (!($reconciliation['eligible'] ?? false)) {
            return ApiResponseService::conflict('BILLING_STATE_INVALID');
        }

        // Registrar ação
        $this->audit('tenant.activated', "Tenant {$tenant->name} ({$tenant->id}) ativado após reconciliação de billing.", [
            'tenant_id' => $tenant->id,
            'source' => $reconciliation['source'] ?? null,
            'stripe_status' => $reconciliation['stripe_status'] ?? null,
        ]);

        return ApiResponseService::success($tenant, 'Tenant ativado com sucesso');
    }

    /**
     * Suspende um tenant.
     */
    public function suspend(Request $request, $id)
    {
        $tenant = Tenant::findOrFail($id);

        if ($tenant->status === Tenant::STATUS_SUSPENDED) {
            return ApiResponseService::error('ALREADY_SUSPENDED', 'Tenant já está suspenso');
        }

        $tenant->suspend();

        // Registrar ação
        $this->audit('tenant.suspended', "Tenant {$tenant->name} ({$tenant->id}) suspenso manualmente.", [
            'tenant_id' => $tenant->id,
        ]);

        return ApiResponseService::success($tenant, 'Tenant suspenso com sucesso');
    }
}
