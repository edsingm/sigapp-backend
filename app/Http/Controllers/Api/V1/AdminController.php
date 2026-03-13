<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    /**
     * Admin Login
     *
     * POST /api/v1/admin/login
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string'],
        ]);

        $requestId = $request->header('X-Request-ID');

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password) || !$user->is_admin) {
            Log::warning('Admin login rejected', [
                'request_id' => $requestId,
                'status' => 'rejected',
            ]);

            return ApiResponseService::error(
                'UNAUTHORIZED',
                language()->t('INVALID_CREDENTIALS'),
                null,
                401
            );
        }

        Log::info('Admin login accepted', [
            'request_id' => $requestId,
            'user_id' => $user->id,
            'status' => 'accepted',
        ]);

        if ($request->has('device_name')) {
            $user->tokens()->where('name', $credentials['device_name'])->delete();
        }

        $tokenResult = $user->createToken(
            $credentials['device_name'] ?? 'admin-token',
            ['admin'], // Add specific ability for admin
            now()->addHours(12)
        );

        return ApiResponseService::success([
            'user' => $user,
            'token' => $tokenResult->plainTextToken,
            'expires_at' => $tokenResult->accessToken->expires_at?->toIso8601String(),
        ], language()->t('LOGIN_SUCCESS'));
    }

    /**
     * Admin Dashboard Stats
     *
     * GET /api/v1/admin/dashboard
     */
    public function dashboard()
    {
        // For now, return some simple stats
        // We can expand this later to fetch real counts from Tenant/Central tables
        // Assuming we have access to Central models here since this runs in central context

        $totalTenants = \App\Models\Central\Tenant::count();
        $recentTenants = \App\Models\Central\Tenant::latest()->take(5)->get();

        return ApiResponseService::success([
            'stats' => [
                'total_tenants' => $totalTenants,
                'active_tenants' => $totalTenants, // Placeholder logic
            ],
            'recent_tenants' => $recentTenants
        ], language()->t('DASHBOARD_DATA_RETRIEVED'));
    }
}
