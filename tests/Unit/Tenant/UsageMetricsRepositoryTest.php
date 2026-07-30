<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant;

use App\Models\Tenant\Documento;
use App\Models\Tenant\MobileCapture;
use App\Models\Tenant\MobileCaptureAttachment;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Repositories\Tenant\UsageMetricsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UsageMetricsRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_counts_the_same_physical_object_only_once(): void
    {
        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);
        $user = User::factory()->createOne();
        $terreno = Terreno::factory()->createOne();
        $capture = MobileCapture::query()->create([
            'client_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'status' => 'committed',
            'terreno_id' => $terreno->id,
        ]);
        Documento::query()->create([
            'terreno_id' => $terreno->id,
            'nome' => 'Foto convertida',
            'file_path' => 'captures/shared.jpg',
            'tamanho' => 100,
        ]);
        MobileCaptureAttachment::query()->create([
            'mobile_capture_id' => $capture->id,
            'created_by' => $user->id,
            'original_name' => 'shared.jpg',
            'file_path' => 'captures/shared.jpg',
            'disk' => 's3',
            'size' => 100,
            'status' => 'converted',
        ]);

        $repository = app(UsageMetricsRepository::class);

        self::assertSame(100, $repository->storageUsedBytes());
        self::assertCount(1, $repository->storageObjects());
    }
}
