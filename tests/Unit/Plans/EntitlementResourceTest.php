<?php

namespace Tests\Unit\Plans;

use App\Enums\Common\EntitlementType;
use App\Http\Resources\EntitlementResource;
use App\Models\Central\Entitlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntitlementResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_includes_all_expected_fields(): void
    {
        $ent = Entitlement::create([
            'key'           => 'test.feature',
            'type'          => EntitlementType::FEATURE->value,
            'label'         => 'Test Feature',
            'description'   => 'A test feature',
            'default_value' => false,
        ]);

        $payload = (new EntitlementResource($ent))->resolve();

        foreach (['id', 'key', 'label', 'description', 'type', 'default_value', 'created_at', 'updated_at'] as $field) {
            $this->assertArrayHasKey($field, $payload, "Field [{$field}] missing from EntitlementResource");
        }

        $this->assertSame('test.feature', $payload['key']);
        // type is backed enum — value is the enum case; JSON output is the string value
        $this->assertSame(EntitlementType::FEATURE, $payload['type']);
    }

    public function test_it_does_not_expose_internal_relational_fields(): void
    {
        $ent = Entitlement::create([
            'key'           => 'feat',
            'type'          => EntitlementType::FEATURE->value,
            'label'         => 'Feat',
            'default_value' => false,
        ]);

        $payload = (new EntitlementResource($ent))->resolve();

        $this->assertArrayNotHasKey('pivot', $payload);
        $this->assertArrayNotHasKey('plans', $payload);
    }
}
