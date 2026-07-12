<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\Shortlist;
use App\Models\Tenant\ShortlistItem;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShortlistResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Shortlist $shortlist */
        $shortlist = $this->resource;

        return [
            'id' => $shortlist->getAttribute('id'),
            'name' => $shortlist->getAttribute('name'),
            'description' => $shortlist->getAttribute('description'),
            'scope' => $shortlist->getAttribute('scope'),
            'is_default' => $shortlist->getAttribute('is_default'),
            'owner' => $this->when($shortlist->relationLoaded('owner'), function () use ($shortlist): ?array {
                $owner = $shortlist->getRelationValue('owner');

                return $owner instanceof User ? [
                    'id' => $owner->getAttribute('id'),
                    'name' => $owner->getAttribute('name'),
                    'email' => $owner->getAttribute('email'),
                ] : null;
            }),
            'items' => $this->when($shortlist->relationLoaded('items'), function () use ($shortlist): array {
                $items = $shortlist->getRelationValue('items');
                if (! $items instanceof Collection) {
                    return [];
                }

                return $items->map(function (Model $item): array {
                    if (! $item instanceof ShortlistItem) {
                        return [];
                    }

                    $terreno = $item->getRelationValue('terreno');

                    return [
                        'id' => $item->getAttribute('id'),
                        'position' => $item->getAttribute('position'),
                        'terreno' => $terreno instanceof Terreno ? [
                            'id' => $terreno->getAttribute('id'),
                            'nome' => $terreno->getAttribute('nome'),
                        ] : null,
                    ];
                })->values()->all();
            }),
            'created_at' => $shortlist->getAttribute('created_at')?->toIso8601String(),
            'updated_at' => $shortlist->getAttribute('updated_at')?->toIso8601String(),
        ];
    }
}
