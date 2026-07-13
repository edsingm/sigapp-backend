<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\ViabilidadeApprovalStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ViabilidadeApprovalStatusTest extends TestCase
{
    #[DataProvider('fromMixedProvider')]
    public function test_from_mixed_resolve_canonical_and_legacy_aliases(
        mixed $value,
        ?string $legacyStatus,
        ViabilidadeApprovalStatus $expected,
    ): void {
        $this->assertSame($expected, ViabilidadeApprovalStatus::fromMixed($value, $legacyStatus));
    }

    /**
     * @return array<string, array{0: mixed, 1: string|null, 2: ViabilidadeApprovalStatus}>
     */
    public static function fromMixedProvider(): array
    {
        return [
            'canonical rejeitada' => ['rejeitada', null, ViabilidadeApprovalStatus::Rejeitada],
            'legacy reprovada' => ['reprovada', null, ViabilidadeApprovalStatus::Rejeitada],
            'legacy rejected' => ['rejected', null, ViabilidadeApprovalStatus::Rejeitada],
            'legacy reject' => ['reject', null, ViabilidadeApprovalStatus::Rejeitada],
            'canonical aprovada' => ['aprovada', null, ViabilidadeApprovalStatus::Aprovada],
            'legacy approved' => ['approved', null, ViabilidadeApprovalStatus::Aprovada],
            'canonical revogada' => ['revogada', null, ViabilidadeApprovalStatus::Revogada],
            'legacy revoked' => ['revoked', null, ViabilidadeApprovalStatus::Revogada],
            'canonical pendente' => ['pendente', null, ViabilidadeApprovalStatus::Pendente],
            'legacy pending' => ['pending', null, ViabilidadeApprovalStatus::Pendente],
            'em_aprovacao' => ['em_aprovacao', null, ViabilidadeApprovalStatus::EmAprovacao],
            'unknown falls to pendente' => ['xyz', null, ViabilidadeApprovalStatus::Pendente],
            'empty falls to pendente' => ['', null, ViabilidadeApprovalStatus::Pendente],
            'legacy ativo status' => [null, 'ativo', ViabilidadeApprovalStatus::Aprovada],
            'enum instance' => [ViabilidadeApprovalStatus::Revogada, null, ViabilidadeApprovalStatus::Revogada],
        ];
    }

    public function test_rejeitada_is_locked_and_allows_new_version_actions(): void
    {
        $status = ViabilidadeApprovalStatus::Rejeitada;

        $this->assertTrue($status->isLocked());
        $this->assertFalse($status->canSubmit());
        $this->assertFalse($status->canDecide());
        $this->assertSame(
            ['duplicate', 'recalculate_as_new_version'],
            $status->allowedActions(),
        );
    }
}
