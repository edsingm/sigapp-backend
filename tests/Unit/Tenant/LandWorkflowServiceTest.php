<?php

namespace Tests\Unit\Tenant;

use App\Models\Tenant\Terreno;
use App\Services\Tenant\LandWorkflowService;
use Tests\TestCase;

class LandWorkflowServiceTest extends TestCase
{
    public function test_it_exposes_canonical_statuses_with_expected_stages(): void
    {
        $statuses = LandWorkflowService::statuses();

        $this->assertSame('captacao', $statuses['novo_lead']['stage']);
        $this->assertSame('viabilidade', $statuses['viabilidade_aprovada']['stage']);
        $this->assertSame('comite', $statuses['em_comite']['stage']);
        $this->assertSame('negociacao_contrato', $statuses['contrato_assinado']['stage']);
        $this->assertSame('registro_encerramento', $statuses['registrado']['stage']);
    }

    public function test_it_returns_available_transitions_for_the_current_status(): void
    {
        $service = app(LandWorkflowService::class);
        $terreno = new Terreno([
            'workflow_status_code' => 'viabilidade_aprovada',
        ]);

        $transitions = $service->availableTransitions($terreno);

        $this->assertContains('aguardando_comite', $transitions);
        $this->assertNotContains('contrato_assinado', $transitions);
    }

    public function test_it_defines_critical_transition_paths(): void
    {
        $matrix = LandWorkflowService::transitionMatrix();

        $this->assertContains('viabilidade_aguardando_aprovacao', $matrix['viabilidade_em_elaboracao']);
        $this->assertContains('aprovado_comite', $matrix['em_comite']);
        $this->assertContains('contrato_assinado', $matrix['contrato_em_assinatura']);
        $this->assertContains('pronto_para_registro', $matrix['legalizacao_concluida']);
        $this->assertContains('arquivado', $matrix['encerrado']);
    }
}
