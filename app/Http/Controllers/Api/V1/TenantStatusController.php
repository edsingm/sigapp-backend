<?php
// app/Http/Controllers/Api/V1/TenantStatusController.php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ApiResponseService;
use App\Services\TenantStatusService;

class TenantStatusController extends Controller
{
    protected $tenantStatusService;

    public function __construct(TenantStatusService $tenantStatusService)
    {
        $this->tenantStatusService = $tenantStatusService;
    }

    /**
     * Obter estatísticas agregadas dos status dos terrenos por workflow.
     */
    public function index()
    {
        $stats = $this->tenantStatusService->getAggregatedStats();

        return ApiResponseService::success($stats, 'TENANT_STATUS_RETRIEVED');
    }
}
