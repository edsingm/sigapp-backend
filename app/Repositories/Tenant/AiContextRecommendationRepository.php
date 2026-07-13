<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Tenant\AiContextRecommendation;

class AiContextRecommendationRepository
{
    public function create(array $data): AiContextRecommendation
    {
        return AiContextRecommendation::create($data);
    }

    public function findForUserOrFail(int $userId, int $id): AiContextRecommendation
    {
        return AiContextRecommendation::query()->whereKey($id)->where('created_by', $userId)->findOrFail($id);
    }

    public function update(AiContextRecommendation $recommendation, array $data): AiContextRecommendation
    {
        $recommendation->update($data);

        return $recommendation->refresh();
    }
}
