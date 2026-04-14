<?php

namespace Tests\Unit\Plans;

use App\Enums\Common\EntitlementType;
use App\Models\Central\Entitlement;
use App\Services\EntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class EntitlementServiceTest extends TestCase
{
    use RefreshDatabase;

    private EntitlementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EntitlementService::class);
    }

    public function test_it_creates_entitlement(): void
    {
        $ent = $this->service->create([
            'key' => 'test.feature',
            'type' => EntitlementType::FEATURE->value,
            'label' => 'Test Feature',
            'default_value' => false,
        ]);

        $this->assertInstanceOf(Entitlement::class, $ent);
        $this->assertSame('test.feature', $ent->key);
        $this->assertDatabaseHas('entitlements', ['key' => 'test.feature']);
    }

    public function test_it_rejects_duplicate_key_on_create(): void
    {
        $this->service->create([
            'key' => 'dup.feature',
            'type' => EntitlementType::FEATURE->value,
            'label' => 'Dup',
            'default_value' => false,
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->create([
            'key' => 'dup.feature',
            'type' => EntitlementType::FEATURE->value,
            'label' => 'Dup2',
            'default_value' => false,
        ]);
    }

    public function test_it_updates_entitlement_label(): void
    {
        $ent = $this->service->create([
            'key' => 'up.feature',
            'type' => EntitlementType::FEATURE->value,
            'label' => 'Original Label',
            'default_value' => false,
        ]);

        $updated = $this->service->update($ent->id, ['label' => 'New Label']);

        $this->assertSame('New Label', $updated->label);
    }

    public function test_it_rejects_update_with_existing_key_of_another_record(): void
    {
        $a = $this->service->create(['key' => 'key.a', 'type' => EntitlementType::FEATURE->value, 'label' => 'A', 'default_value' => false]);
        $this->service->create(['key' => 'key.b', 'type' => EntitlementType::FEATURE->value, 'label' => 'B', 'default_value' => false]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->update($a->id, ['key' => 'key.b']);
    }

    public function test_it_deletes_entitlement(): void
    {
        $ent = $this->service->create([
            'key' => 'del.feature',
            'type' => EntitlementType::FEATURE->value,
            'label' => 'Del',
            'default_value' => false,
        ]);

        $this->service->delete($ent->id);

        $this->assertDatabaseMissing('entitlements', ['id' => $ent->id]);
    }

    public function test_find_or_fail_throws_for_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->findOrFail(99999);
    }

    public function test_it_lists_all_entitlements(): void
    {
        $this->service->create(['key' => 'list.a', 'type' => EntitlementType::FEATURE->value, 'label' => 'A', 'default_value' => false]);
        $this->service->create(['key' => 'list.b', 'type' => EntitlementType::LIMIT->value, 'label' => 'B', 'default_value' => 0]);

        $all = $this->service->list();

        $this->assertCount(2, $all);
    }
}
