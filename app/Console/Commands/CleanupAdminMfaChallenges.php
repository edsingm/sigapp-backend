<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\Contracts\AdminMfaRepositoryInterface;
use Illuminate\Console\Command;

class CleanupAdminMfaChallenges extends Command
{
    protected $signature = 'admin:mfa-cleanup';

    protected $description = 'Remove desafios MFA administrativos expirados ou consumidos';

    public function handle(AdminMfaRepositoryInterface $repository): int
    {
        $deleted = $repository->deleteExpiredChallenges();
        $this->info("{$deleted} desafio(s) MFA removido(s).");

        return self::SUCCESS;
    }
}
