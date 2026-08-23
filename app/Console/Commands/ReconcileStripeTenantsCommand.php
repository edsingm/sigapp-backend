<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ReconcileTenantBillingJob;
use App\Models\Central\Tenant;
use Illuminate\Console\Command;

class ReconcileStripeTenantsCommand extends Command
{
    protected $signature = 'billing:reconcile-tenants {--tenant= : ID ou slug específico}';

    protected $description = 'Despacha reconciliação Stripe para tenants com assinatura vinculada';

    public function handle(): int
    {
        $identifier = $this->option('tenant');
        $query = Tenant::query()
            ->whereNotNull('stripe_subscription_id')
            ->whereNull('wiped_at');

        if (is_string($identifier) && $identifier !== '') {
            $query->where(function ($query) use ($identifier): void {
                $query->whereKey($identifier)->orWhere('slug', $identifier);
            });
        }

        $count = 0;
        $query->select(['id'])->toBase()->chunkById(100, function ($rows) use (&$count): void {
            foreach ($rows as $row) {
                ReconcileTenantBillingJob::dispatch((string) $row->id);
                $count++;
            }
        });

        $this->info("Reconciliações despachadas: {$count}");

        return self::SUCCESS;
    }
}
