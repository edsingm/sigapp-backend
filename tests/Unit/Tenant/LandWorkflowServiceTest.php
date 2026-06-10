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

        $this->assertSame('captacao', $statuses['em_analise']['stage']);
        $this->assertSame('viabilidade', $statuses['viabilidade_aprovada']['stage']);
        $this->assertSame('comite', $statuses['aguardando_comite']['stage']);
        $this->assertSame('negociacao_contrato', $statuses['contrato_assinado']['stage']);
        $this->assertSame('encerramento', $statuses['legalizado_finalizado']['stage']);
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

        $this->assertContains('aguardando_viabilidade', $matrix['em_analise']);
        $this->assertContains('negociacao_minuta', $matrix['aguardando_comite']);
        $this->assertContains('contrato_assinado', $matrix['negociacao_minuta']);
        $this->assertContains('legalizado_finalizado', $matrix['legalizando']);
        $this->assertContains('arquivado', $matrix['legalizado_finalizado']);
    }

    public function test_viabilidade_aprovada_can_skip_committee_and_go_to_negociacao(): void
    {
        $matrix = LandWorkflowService::transitionMatrix();

        $this->assertContains('negociacao_minuta', $matrix['viabilidade_aprovada']);
        $this->assertContains('aguardando_comite', $matrix['viabilidade_aprovada']);
    }
}
