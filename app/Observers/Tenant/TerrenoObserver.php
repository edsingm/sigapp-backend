<?php

namespace App\Observers\Tenant;

use App\Jobs\CalculateUsableAreaJob;
use App\Models\Tenant\Terreno;
use Illuminate\Support\Facades\Log;

/**
 * Observer que dispara o cálculo automático de área útil
 * sempre que o polígono de um terreno é alterado.
 */
class TerrenoObserver
{
    public function created(Terreno $terreno): void
    {
        if ($terreno->polygon_coords !== null) {
            $this->dispatchCalculation($terreno);
        }
    }

    public function updated(Terreno $terreno): void
    {
        if ($terreno->wasChanged('polygon_coords')) {
            $this->dispatchCalculation($terreno);
        }
    }

    private function dispatchCalculation(Terreno $terreno): void
    {
        Log::info('polygon_coords alterado, disparando cálculo de área útil', [
            'terreno_id' => $terreno->id,
        ]);

        $tenant = tenancy()->tenant;

        if ($tenant === null) {
            Log::warning('Não foi possível disparar cálculo de área útil sem tenant inicializado', [
                'terreno_id' => $terreno->id,
            ]);

            return;
        }

        CalculateUsableAreaJob::dispatch($terreno->id, (string) $tenant->id);
    }
}
