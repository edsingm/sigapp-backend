<?php

use App\Http\Controllers\Api\V1\Tenant\ActivityController;
use App\Http\Controllers\Api\V1\Tenant\Admin\DepartmentController as AdminDepartmentController;
use App\Http\Controllers\Api\V1\Tenant\Admin\PermissionController as AdminPermissionController;
use App\Http\Controllers\Api\V1\Tenant\Admin\RoleController as AdminRoleController;
use App\Http\Controllers\Api\V1\Tenant\Admin\UserManagementController as AdminUserManagementController;
use App\Http\Controllers\Api\V1\Tenant\BillingHistoryController;
use App\Http\Controllers\Api\V1\Tenant\GlobalSearchController;
use App\Http\Controllers\Api\V1\Tenant\ReportBuilderController;
use App\Http\Controllers\Api\V1\Tenant\SavedViewController;
use App\Http\Controllers\Api\V1\Tenant\ShortlistController;
use App\Http\Controllers\Api\V1\Tenant\TaskController;
use App\Http\Controllers\Api\V1\Tenant\TenantController;
use App\Http\Controllers\Api\V1\Tenant\TenantExportController;
use App\Http\Controllers\Api\V1\Tenant\UserController;
use App\Http\Controllers\Api\V1\Tenant\UserOnboardingController;
use App\Http\Controllers\Api\V1\Tenant\UserPreferencesController;
use Illuminate\Support\Facades\Route;

// Tenant info
Route::get('/tenant', [TenantController::class, 'show']);
Route::get('/tenant/usage', [TenantController::class, 'usage']);

// Billing history
Route::prefix('tenant/billing')->group(function () {
    Route::get('/history', [BillingHistoryController::class, 'index']);
    Route::get('/invoices/{invoiceId}', [BillingHistoryController::class, 'show']);
    Route::get('/invoices/{invoiceId}/pdf', [BillingHistoryController::class, 'downloadPdf']);
});

// Users (select inputs for tenant forms)
Route::get('/users/for-select', [UserController::class, 'usersForSelect']);

Route::prefix('exports')->group(function () {
    Route::post('/', [TenantExportController::class, 'store'])
        ->middleware('throttle:exports')
        ->name('tenant.exports.store');
    Route::get('/{export}', [TenantExportController::class, 'show'])
        ->whereNumber('export')
        ->name('tenant.exports.show');
    Route::get('/{export}/download', [TenantExportController::class, 'download'])
        ->whereNumber('export')
        ->name('tenant.exports.download');
});

Route::get('/me/preferences', [UserPreferencesController::class, 'show']);
Route::patch('/me/preferences', [UserPreferencesController::class, 'update']);
Route::middleware('check.feature:onboarding.profile')->prefix('me/onboarding')->group(function () {
    Route::get('/', [UserOnboardingController::class, 'show']);
    Route::post('/events', [UserOnboardingController::class, 'event']);
    Route::post('/dismiss', [UserOnboardingController::class, 'dismiss']);
    Route::post('/resume', [UserOnboardingController::class, 'resume']);
});

Route::middleware('check.feature:search.global')->group(function () {
    Route::get('/search', [GlobalSearchController::class, 'index']);
});

Route::middleware('check.feature:reports.builder')->prefix('reports')->group(function () {
    Route::get('/templates', [ReportBuilderController::class, 'templates']);
    Route::post('/templates', [ReportBuilderController::class, 'storeTemplate']);
    Route::get('/templates/{template}', [ReportBuilderController::class, 'showTemplate'])->whereNumber('template');
    Route::put('/templates/{template}', [ReportBuilderController::class, 'updateTemplate'])->whereNumber('template');
    Route::delete('/templates/{template}', [ReportBuilderController::class, 'destroyTemplate'])->whereNumber('template');
    Route::post('/runs', [ReportBuilderController::class, 'storeRun']);
    Route::get('/runs/{run}', [ReportBuilderController::class, 'showRun'])->whereNumber('run');
    Route::get('/runs/{run}/download', [ReportBuilderController::class, 'download'])->whereNumber('run');
});

Route::middleware('check.feature:workspace.saved_views')->group(function () {
    Route::get('/saved-views', [SavedViewController::class, 'index']);
    Route::post('/saved-views', [SavedViewController::class, 'store']);
    Route::get('/saved-views/{id}', [SavedViewController::class, 'show'])->whereNumber('id');
    Route::put('/saved-views/{id}', [SavedViewController::class, 'update'])->whereNumber('id');
    Route::delete('/saved-views/{id}', [SavedViewController::class, 'destroy'])->whereNumber('id');
    Route::post('/saved-views/{id}/set-default', [SavedViewController::class, 'setDefault'])->whereNumber('id');
});

// Atividades unificadas — a feature permanece desligada até o
// slice colaborativo estar liberado por plano.
Route::middleware('check.feature:collaboration.inbox')->group(function () {
    Route::get('/activities', [ActivityController::class, 'index'])
        ->middleware('permission.gate:prospection,terrains');
    Route::get('/activities/{entityType}/{entityId}', [ActivityController::class, 'forEntity'])
        ->whereNumber('entityId')
        ->middleware('permission.gate:prospection,terrains');
});

// Tarefas colaborativas. O middleware de permissão preserva a
// ACL de prospecção até existir um módulo collaboration próprio.
Route::middleware('check.feature:collaboration.tasks')
    ->middleware('permission.gate:prospection,terrains')
    ->prefix('tasks')
    ->group(function () {
        Route::get('/my-queue', [TaskController::class, 'myQueue']);
        Route::get('/', [TaskController::class, 'index']);
        Route::post('/', [TaskController::class, 'store']);
        Route::get('/{task}', [TaskController::class, 'show']);
        Route::put('/{task}', [TaskController::class, 'update']);
        Route::delete('/{task}', [TaskController::class, 'destroy']);
        Route::get('/{task}/comments', [TaskController::class, 'listComments']);
        Route::post('/{task}/comments', [TaskController::class, 'comments']);
    });

Route::middleware([
    'check.feature:prospection.comparison',
    'permission.gate:prospection,terrains',
])->group(function () {
    Route::get('/shortlists', [ShortlistController::class, 'index']);
    Route::post('/shortlists', [ShortlistController::class, 'store']);
    Route::get('/shortlists/{shortlist}', [ShortlistController::class, 'show']);
    Route::put('/shortlists/{shortlist}', [ShortlistController::class, 'update']);
    Route::delete('/shortlists/{shortlist}', [ShortlistController::class, 'destroy']);
    Route::post('/shortlists/{shortlist}/items', [ShortlistController::class, 'addItem']);
    Route::delete('/shortlists/{shortlist}/items/{terreno}', [ShortlistController::class, 'removeItem']);
});

// Tenant admin (users, roles and permissions)
Route::prefix('tenant-admin')
    ->middleware('tenant.admin')
    ->as('tenant-admin.')
    ->group(function () {
        Route::post('users', [AdminUserManagementController::class, 'store'])
            ->middleware('enforce.limits:users')
            ->middleware('tenant.admin')
            ->name('tenant-admin.users.store');
        Route::apiResource('users', AdminUserManagementController::class)->except(['store']);
        Route::post('users/{id}/send-invite', [AdminUserManagementController::class, 'sendInvite'])
            ->middleware('tenant.admin')
            ->name('tenant-admin.users.send-invite');
        Route::put('users/{id}/module-permissions', [AdminUserManagementController::class, 'updateModulePermissions'])
            ->middleware('tenant.admin')
            ->name('tenant-admin.users.module-permissions');
        Route::get('roles/select', [AdminRoleController::class, 'forSelect'])
            ->name('tenant-admin.roles.select');
        Route::apiResource('roles', AdminRoleController::class)
            ->middleware('permission.gate:admin');
        Route::apiResource('permissions', AdminPermissionController::class)
            ->middleware('permission.gate:admin');

        // Departments
        Route::get('departments/select', [AdminDepartmentController::class, 'forSelect'])
            ->name('tenant-admin.departments.select');
        Route::apiResource('departments', AdminDepartmentController::class)
            ->middleware('permission.gate:admin');
    });
