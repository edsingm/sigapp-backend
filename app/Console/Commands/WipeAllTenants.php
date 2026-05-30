<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use Illuminate\Console\Command;

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
    public function handle(): int
    {
        if (! $this->confirm('Tem certeza? Isso vai apagar TODOS os schemas de tenants!')) {
            return self::SUCCESS;
        }

        $count = Tenant::query()->count();

        Tenant::query()->get()->each->delete();

        $this->info("✅ {$count} tenants e seus schemas foram apagados com sucesso!");

        return self::SUCCESS;
    }
}
