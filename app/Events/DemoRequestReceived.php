<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Central\DemoRequest;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class DemoRequestReceived implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly DemoRequest $demoRequest,
    ) {}
}
