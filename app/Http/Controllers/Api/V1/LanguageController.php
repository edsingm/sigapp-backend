<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ApiResponseService;
use App\Services\LanguageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LanguageController extends Controller
{
    /**
     * Atualiza o locale da aplicação para o usuário atual.
     *
     * PUT /api/v1/locale
     */
    public function set(Request $request): JsonResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'string', 'in:' . implode(',', LanguageService::SUPPORTED_LOCALES)],
        ]);

        LanguageService::setLocale($data['locale']);

        return ApiResponseService::success(
            ['locale' => $data['locale']],
            language()->t('LANGUAGE_UPDATED_SUCCESFULLY')
        );
    }
}
