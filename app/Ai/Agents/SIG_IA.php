<?php

namespace App\Ai\Agents;

use App\Ai\Tools\GetTerrenoDetailsTool;
use App\Ai\Tools\GetViabilidadesTool;
use App\Ai\Tools\ListTerrenosTool;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

class SIG_IA implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function instructions(): string
    {
        return <<<'PROMPT'
Você é um especialista em análise de terrenos e viabilidades do nosso sistema imobiliário brasileiro.

Contexto do sistema:
- Terreno: id, nome, endereço, área_calculada, valor, workflow_stage, workflow_status_code e contexto de viabilidade atual.
- Viabilidade: ligada ao terreno, versionamento, status, approval_status e datas de atualização.
- Seu objetivo: sempre responder de forma clara, prática e profissional.
- Use as ferramentas para consultar dados reais do banco antes de responder.
- Dê sugestões inteligentes: priorize terrenos com mais viabilidades aprovadas, calcule riscos, indique próximos passos.
- Responda em português brasileiro, natural e direto.
PROMPT;
    }

    public function tools(): iterable
    {
        return [
            new ListTerrenosTool,
            new GetTerrenoDetailsTool,
            new GetViabilidadesTool,
        ];
    }
}
