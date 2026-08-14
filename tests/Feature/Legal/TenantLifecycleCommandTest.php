<?php

declare(strict_types=1);

namespace Tests\Feature\Legal;

use App\Models\Central\Tenant;
use App\Notifications\TenantWipeUpcomingNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TenantLifecycleCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_purge_command_sends_d60_and_d83_notices_without_wiping_when_flag_is_off(): void
    {
        Notification::fake();
        config(['privacy.auto_wipe_enabled' => false]);

        $dueFirst = Tenant::query()->create([
            'name' => 'Aviso 60',
            'slug' => 'aviso-60',
            'status' => Tenant::STATUS_CANCELLED,
            'admin_email' => 'admin60@example.com',
            'database_created' => false,
        ]);
        $dueFirst->forceFill([
            'cancelled_at' => now()->subDays(60),
            'wipe_scheduled_at' => now()->addDays(30),
        ])->save();

        $dueFinal = Tenant::query()->create([
            'name' => 'Aviso 83',
            'slug' => 'aviso-83',
            'status' => Tenant::STATUS_CANCELLED,
            'admin_email' => 'admin83@example.com',
            'database_created' => false,
        ]);
        $dueFinal->forceFill([
            'cancelled_at' => now()->subDays(83),
            'wipe_scheduled_at' => now()->addDays(7),
            'wipe_notice_d60_sent_at' => now()->subDays(23),
        ])->save();

        $this->assertSame(0, Artisan::call('privacy:purge-cancelled-tenants'));
        $output = Artisan::output();
        $this->assertStringContainsString('Avisos de wipe enviados: 2', $output);
        $this->assertStringContainsString('PRIVACY_AUTO_WIPE_ENABLED=false', $output);

        $first = $dueFirst->fresh();
        $final = $dueFinal->fresh();
        $this->assertInstanceOf(Tenant::class, $first);
        $this->assertInstanceOf(Tenant::class, $final);
        Notification::assertSentTo($first, TenantWipeUpcomingNotification::class);
        Notification::assertSentTo($final, TenantWipeUpcomingNotification::class);
        $this->assertNotNull($first->getAttribute('wipe_notice_d60_sent_at'));
        $this->assertNotNull($final->getAttribute('wipe_notice_d83_sent_at'));
        $this->assertNull($first->getAttribute('wiped_at'));
        $this->assertNull($final->getAttribute('wiped_at'));
    }

    public function test_purge_command_wipes_due_tenants_with_force(): void
    {
        Notification::fake();
        config(['privacy.auto_wipe_enabled' => false]);

        $tenant = Tenant::query()->create([
            'name' => 'Due Wipe',
            'slug' => 'due-wipe',
            'status' => Tenant::STATUS_CANCELLED,
            'admin_email' => 'due@example.com',
            'stripe_id' => 'cus_due',
            'database_created' => false,
        ]);
        $tenant->forceFill([
            'billing_tax_id' => '52998224725',
            'cancelled_at' => now()->subDays(91),
            'wipe_scheduled_at' => now()->subDay(),
        ])->save();

        $this->assertSame(0, Artisan::call('privacy:purge-cancelled-tenants', ['--force' => true]));
        $this->assertStringContainsString('Tenants removidos: 1', Artisan::output());

        $tenant->refresh();
        $this->assertNotNull($tenant->getAttribute('wiped_at'));
        $this->assertSame('cus_due', $tenant->getAttribute('stripe_id'));
        $this->assertSame('52998224725', $tenant->getAttribute('billing_tax_id'));
    }
}
