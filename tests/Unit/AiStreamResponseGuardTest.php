<?php

namespace Tests\Unit;

use App\Services\Ai\AiStreamResponseGuard;
use Tests\TestCase;

class AiStreamResponseGuardTest extends TestCase
{
    public function test_empty_text_is_incomplete(): void
    {
        $this->assertTrue(AiStreamResponseGuard::looksIncomplete(''));
        $this->assertTrue(AiStreamResponseGuard::looksIncomplete('   '));
        $this->assertTrue(AiStreamResponseGuard::looksIncomplete(null));
    }

    public function test_progress_monologue_is_incomplete(): void
    {
        $text = 'Nenhum terreno foi encontrado com o status "Viabilidade aprovada". '
            .'Vou ampliar a busca. Encontrei uma viabilidade aprovada! Vou buscar os detalhes completos do terreno.';

        $this->assertTrue(AiStreamResponseGuard::looksIncomplete($text));
    }

    public function test_complete_business_answer_is_not_incomplete(): void
    {
        $text = '**Resumo Executivo**\n'
            .'Terreno **#385** possui viabilidade aprovada (versão 2), VGV R$ 12,5 mi e margem líquida de 18%.\n\n'
            .'**Principais Evidências**\n'
            .'- approval_status: aprovada\n'
            .'- workflow: Negociação/Minuta';

        $this->assertFalse(AiStreamResponseGuard::looksIncomplete($text));
    }

    public function test_fallback_messages_are_non_empty(): void
    {
        $this->assertNotSame('', AiStreamResponseGuard::emptyFallbackMessage());
        $this->assertNotSame('', AiStreamResponseGuard::incompleteFallbackMessage());
        $this->assertStringContainsString('incompleta', AiStreamResponseGuard::incompleteFallbackMessage());
    }
}
