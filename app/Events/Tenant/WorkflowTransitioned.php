<?php

declare(strict_types=1);

namespace App\Events\Tenant;

use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use Illuminate\Foundation\Events\Dispatchable;

class WorkflowTransitioned
{
    use Dispatchable;

    public function __construct(
        public readonly Terreno $terreno,
        public readonly string $previousStatus,
        public readonly string $previousStage,
        public readonly string $newStatus,
        public readonly string $newStage,
        public readonly string $newLabel,
        public readonly ?User $user,
        public readonly ?string $reasonCode,
        public readonly ?string $reasonNotes,
        public readonly array $context = [],
    ) {}
}
