<?php

namespace App\Console\Commands;


use Illuminate\Console\Command;
use Stancl\Tenancy\Database\Models\Tenant;

class WipeAllTenants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:wipe-all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Apaga TODOS os tenants e seus schemas';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->confirm('Tem certeza? Isso vai apagar TODOS os schemas de tenants!')) {
            return;
        }

        $count = Tenant::count();

        Tenant::all()->each->delete();

        $this->info("✅ {$count} tenants e seus schemas foram apagados com sucesso!");
    }
}
