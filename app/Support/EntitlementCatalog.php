<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Common\EntitlementScope;
use Illuminate\Support\Facades\Log;

final class EntitlementCatalog
{
    /** @var array<string, string> */
    public const LEGACY_ALIASES = [
        'projects_room' => 'projects.enabled',
        'projects.room' => 'projects.planning',
    ];

    /** @var list<string> */
    public const UI_FEATURES = [
        'home',
        'prospection.terrain_cockpit',
        'dashboard.executive',
        'dashboard.goals',
        'territorial.map_comparison',
        'onboarding.profile',
        'workspace.personalization',
        'dashboard.personalization',
        'experience.accessibility',
    ];

    /** @var list<string> */
    public const COMPOSITE_FEATURES = [
        'dashboard.enabled',
        'viabilities.enabled',
    ];

    /** @var list<string> */
    public const RESPONSE_PROJECTIONS = [
        'viabilities.summary',
        'viabilities.kpis',
        'viabilities.dre',
        'viabilities.cash_flow',
        'viabilities.comercial',
        'viabilities.premises',
        'viabilities.charts',
    ];

    public static function canonicalKey(string $key): string
    {
        $canonical = self::LEGACY_ALIASES[$key] ?? null;
        if ($canonical === null) {
            return $key;
        }

        Log::info('Legacy entitlement alias resolved.', [
            'legacy_key' => $key,
            'canonical_key' => $canonical,
            'tenant_id' => tenant('id'),
        ]);

        return $canonical;
    }

    public static function scopeForFeature(string $key): EntitlementScope
    {
        if (in_array($key, self::UI_FEATURES, true)) {
            return EntitlementScope::UI;
        }

        if (in_array($key, self::COMPOSITE_FEATURES, true)) {
            return EntitlementScope::COMPOSITE;
        }

        return EntitlementScope::API;
    }
}
