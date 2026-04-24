<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SetLocaleRequest;
use App\Http\Resources\LocaleResource;
use App\Services\ApiResponseService;
use App\Services\LanguageService;
use Illuminate\Http\JsonResponse;

class LanguageController extends Controller
{
    /**
     * Atualiza o locale da aplicação para o usuário atual.
     *
     * PUT /api/v1/locale
     */
    public function set(SetLocaleRequest $request): JsonResponse
    {
        $data = $request->validated();

        LanguageService::setLocale($data['locale']);

        return ApiResponseService::success(
            new LocaleResource(['locale' => $data['locale']]),
            language()->t('LANGUAGE_UPDATED_SUCCESFULLY')
        );
    }
}
