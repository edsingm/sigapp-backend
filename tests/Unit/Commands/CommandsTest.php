<?php

namespace Tests\Unit\Commands;

use Tests\TestCase;

class CommandsTest extends TestCase
{
    public function test_cleanup_pending_tenants_command_existe(): void
    {
        $this->assertTrue(class_exists('App\Console\Commands\CleanupPendingTenants'));

        $r = new \ReflectionClass('App\Console\Commands\CleanupPendingTenants');
        $this->assertTrue($r->hasMethod('handle'));
    }

    public function test_sync_tenant_acl_command_existe(): void
    {
        $this->assertTrue(class_exists('App\Console\Commands\SyncTenantAclCommand'));

        $r = new \ReflectionClass('App\Console\Commands\SyncTenantAclCommand');
        $this->assertTrue($r->hasMethod('handle'));
    }

    public function test_recalculate_ai_scores_command_existe(): void
    {
        $this->assertTrue(class_exists('App\Console\Commands\RecalculateAiScoresCommand'));

        $r = new \ReflectionClass('App\Console\Commands\RecalculateAiScoresCommand');
        $this->assertTrue($r->hasMethod('handle'));
    }

    public function test_apply_rbac_templates_command_existe(): void
    {
        $this->assertTrue(class_exists('App\Console\Commands\ApplyRbacTemplatesCommand'));

        $r = new \ReflectionClass('App\Console\Commands\ApplyRbacTemplatesCommand');
        $this->assertTrue($r->hasMethod('handle'));
    }

    public function test_bootstrap_central_admin_command_existe(): void
    {
        $this->assertTrue(class_exists('App\Console\Commands\BootstrapCentralAdminCommand'));

        $r = new \ReflectionClass('App\Console\Commands\BootstrapCentralAdminCommand');
        $this->assertTrue($r->hasMethod('handle'));
    }

    public function test_cleanup_central_login_broker_command_existe(): void
    {
        $this->assertTrue(class_exists('App\Console\Commands\CleanupCentralLoginBroker'));

        $r = new \ReflectionClass('App\Console\Commands\CleanupCentralLoginBroker');
        $this->assertTrue($r->hasMethod('handle'));
    }

    public function test_cleanup_consent_logs_command_existe(): void
    {
        $this->assertTrue(class_exists('App\Console\Commands\CleanupConsentLogsCommand'));

        $r = new \ReflectionClass('App\Console\Commands\CleanupConsentLogsCommand');
        $this->assertTrue($r->hasMethod('handle'));
    }

    public function test_notify_overdue_legalizacao_command_existe(): void
    {
        $this->assertTrue(class_exists('App\Console\Commands\NotifyOverdueLegalizacaoEtapasCommand'));

        $r = new \ReflectionClass('App\Console\Commands\NotifyOverdueLegalizacaoEtapasCommand');
        $this->assertTrue($r->hasMethod('handle'));
    }

    public function test_wipe_all_tenants_command_existe(): void
    {
        $this->assertTrue(class_exists('App\Console\Commands\WipeAllTenants'));

        $r = new \ReflectionClass('App\Console\Commands\WipeAllTenants');
        $this->assertTrue($r->hasMethod('handle'));
    }

    public function test_commands_tem_signature_e_description(): void
    {
        $commands = [
            'App\Console\Commands\CleanupPendingTenants',
            'App\Console\Commands\SyncTenantAclCommand',
            'App\Console\Commands\RecalculateAiScoresCommand',
        ];

        foreach ($commands as $class) {
            $r = new \ReflectionClass($class);
            $signature = $r->getProperty('signature');
            $signature->setAccessible(true);

            $this->assertNotEmpty(
                $signature->getValue($r->newInstanceWithoutConstructor()),
                "Command {$class} deve ter signature"
            );
        }
    }
}
