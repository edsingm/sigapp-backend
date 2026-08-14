<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\PrivacyRequestKind;
use App\Enums\PrivacyRequestStatus;
use App\Enums\PrivacySubjectType;
use App\Models\Central\PrivacyRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @phpstan-extends Factory<PrivacyRequest>
 */
class PrivacyRequestFactory extends Factory
{
    protected $model = PrivacyRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $received = now();

        return [
            'protocol' => 'LGPD-'.now()->format('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'kind' => PrivacyRequestKind::ACCESS,
            'subject_type' => PrivacySubjectType::OTHER,
            'subject_email' => fake()->unique()->safeEmail(),
            'tenant_id' => null,
            'status' => PrivacyRequestStatus::OPEN,
            'received_at' => $received,
            'due_at' => $received->copy()->addDays(15),
        ];
    }
}
