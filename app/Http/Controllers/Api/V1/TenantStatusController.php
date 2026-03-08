<?php
// app/Http/Controllers/Api/V1/TenantStatusController.php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\TenantStatusService;

class TenantStatusController extends Controller
{
    protected $tenantStatusService;

    public function __construct(TenantStatusService $tenantStatusService)
    {
        $this->tenantStatusService = $tenantStatusService;
    }

    public function index()
    {
        $stats = $this->tenantStatusService->getAggregatedStats();

        return response()->json($stats);
    }
}