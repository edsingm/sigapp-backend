<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Tenant\ComiteRevisao;
use App\Models\Tenant\Legalizacao;
use App\Models\Tenant\Negociacao;
use App\Models\Tenant\Projeto;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Models\Tenant\Viabilidade;

class GlobalSearchRepository
{
    /**
     * @param  array<int, string>  $types
     * @return array<int, array<string, mixed>>
     */
    public function search(array $types, string $term, int $limit): array
    {
        $results = [];
        foreach ($types as $type) {
            $remaining = $limit - count($results);
            if ($remaining <= 0) {
                break;
            }

            $rows = match ($type) {
                'terreno' => Terreno::query()->where(function ($query) use ($term): void {
                    $query->where('nome', 'like', "%{$term}%")->orWhere('codigo', 'like', "%{$term}%");
                })->limit($remaining)->get(['id', 'nome', 'codigo']),
                'viabilidade' => Viabilidade::query()->with('terreno')->whereHas('terreno', fn ($query) => $query->where('nome', 'like', "%{$term}%"))->limit($remaining)->get(),
                'comite' => ComiteRevisao::query()->with('terreno')->whereHas('terreno', fn ($query) => $query->where('nome', 'like', "%{$term}%"))->limit($remaining)->get(),
                'negociacao' => Negociacao::query()->with('terreno')->whereHas('terreno', fn ($query) => $query->where('nome', 'like', "%{$term}%"))->limit($remaining)->get(),
                'legalizacao' => Legalizacao::query()->with('terreno')->where(function ($query) use ($term): void {
                    $query->where('nome', 'like', "%{$term}%")->orWhereHas('terreno', fn ($terrain) => $terrain->where('nome', 'like', "%{$term}%"));
                })->limit($remaining)->get(),
                'projeto' => Projeto::query()->with('terreno')->where(function ($query) use ($term): void {
                    $query->where('nome', 'like', "%{$term}%")->orWhereHas('terreno', fn ($terrain) => $terrain->where('nome', 'like', "%{$term}%"));
                })->limit($remaining)->get(),
                'pessoa' => User::query()->where(function ($query) use ($term): void {
                    $query->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%");
                })->limit($remaining)->get(['id', 'name', 'email']),
                default => collect(),
            };

            foreach ($rows as $row) {
                $title = match ($type) {
                    'pessoa' => (string) $row->name,
                    'viabilidade', 'comite', 'negociacao' => (string) ($row->terreno?->nome ?? "{$type} #{$row->id}"),
                    default => (string) $row->nome,
                };
                $subtitle = $type === 'pessoa' ? (string) $row->email : "{$type} #{$row->id}";
                $results[] = [
                    'id' => $row->id,
                    'type' => $type,
                    'title' => $title,
                    'subtitle' => $subtitle,
                    'description' => null,
                    'href' => $this->href($type, (int) $row->id),
                    'icon' => $type,
                    'score' => str_starts_with(mb_strtolower($title), mb_strtolower($term)) ? 1.0 : 0.75,
                    'highlights' => [$title],
                    'entity' => ['id' => $row->id, 'type' => $type, 'label' => $title, 'href' => $this->href($type, (int) $row->id)],
                    'data' => ['status' => $row->status ?? null],
                ];
            }
        }

        return $results;
    }

    private function href(string $type, int $id): string
    {
        return match ($type) {
            'terreno' => "/sig/terrenos/{$id}",
            'viabilidade' => "/sig/viabilidades/{$id}",
            'comite' => "/sig/comite/{$id}",
            'negociacao' => "/sig/negociacoes/{$id}",
            'legalizacao' => "/sig/legalizacoes/{$id}",
            'projeto' => "/sig/projetos/{$id}",
            'pessoa' => "/sig/admin/usuarios/{$id}",
            default => '/sig',
        };
    }
}
