# Sugestões de Novas Funcionalidades para o SIGAPP

**Data:** 26 de maio de 2026  
**Autor:** Análise estratégica de produto  
**Escopo:** Novas features organizadas por prioridade e viabilidade  
**Versão:** 2.0 (revisada com análise crítica)

---

## Sumário Executivo

Este documento apresenta sugestões de novas funcionalidades para o SIGAPP, revisadas após análise crítica. A versão anterior incluía features genéricas (blockchain, AR, gamificação) sem conexão com o domínio real da plataforma. Esta versão foca em **features que resolvem dores reais de incorporadoras e loteadoras brasileiras**.

**Total de features:** 25 (reduzido de 50, eliminando 25 features genéricas, redundantes ou irrealistas)

---

## Análise Crítica da Versão Anterior

### Features Cortadas (e por quê)

| Feature | Motivo do corte |
|---------|----------------|
| Blockchain para Rastreabilidade | Ninguém no mercado imobiliário brasileiro pede isso. Hype sem demanda. |
| Realidade Aumentada (Mobile) | Cool demo, zero valor prático para análise de viabilidade. |
| Gamificação de Metas | Incorporadoras não são startups. Leaderboard funciona em times de vendas, não em análise de terrenos. |
| Modo "Foco" com Pomodoro | Feature de app de produtividade, não de plataforma B2B. |
| Análise de Sentimento de Mercado | Scraping de Twitter e Reclame Aqui é irrelevante para quem analisa terrenos. |
| Rede de Incorporadores | Fórum e comunidade são produtos separados, distratam foco. |
| Chat Interno por Terreno | Comentários + tasks já resolvem. Chat é overkill. |
| Modo Colaboração em Tempo Real | WebSockets/CRDT é overkill para o uso real. Histórico de alterações resolve. |
| Integração com Prefeituras | Cada prefeitura tem sistema diferente. Maioria não tem API. Irrealista. |
| Integração com Portais (Zap, OLX) | Scraping viola ToS. OAuth não existe nesses portais. Problemas legais. |
| Due Diligence Automatizada | Cartórios brasileiros não têm APIs públicas. É um serviço, não software. |
| Marketplace de Serviços | Two-sided marketplace é produto separado. Requer curadoria, pagamento, disputa. |
| White Label | Prematuro. Só faz sentido com 50+ tenants. |
| Modo Offline (PWA) | Alto esforço, uso real incerto. Visitas a terreno são rápidas. |
| Dashboard Personalizável (drag-and-drop) | Templates resolvem 80% do caso. Drag-and-ddrop é overkill. |
| Consultoria IA Premium | É serviço, não feature. Requer contratação de consultores. |
| Benchmark de Mercado Gratuito | Precisa de massa crítica de dados primeiro. Prematuro. |
| Certificação SIGAPP | Marketing, não produto. |
| Simulador de Impacto Urbano | Muito complexo (16-24 semanas), nicho demais. |
| Predição de Valorização | Já existe predição de aprovação e VGV. Duplicar esforço. |
| Assistente de Negociação IA | Poucos dados históricos para treinar modelo confiável. |
| Parceria com Corretores | Marketplace de terrenos é produto separado. |
| Calculadora de ROI Pública | Marketing, não produto. Não precisa de backend. |
| Demo Interativa Pública | Marketing, não produto. Pode ser feito com dados estáticos. |
| Modo "Consultor" | Nicho demais. Focar no core primeiro. |
| Trial Estendido Condicional | Configuração de produto, não feature de software. |

### Features que o Sistema Já Tem

| Sugerido como novo | Já existe no SIGAPP |
|-------------------|-------------------|
| Score de qualidade de terreno | `AiScoringService` já faz scoring com 7 fatores |
| Detecção de anomalias | `AiAnomalyDetectionService` já detecta inconsistências, duplicatas, anomalias financeiras |
| Predição de aprovação | `AiPredictiveAnalysisService` já prediz aprovação, VGV, estagnação |
| Monitoramento proativo | `ProactiveMonitorTool` + `AiMonitorService` já detectam terrenos parados |
| Insights automáticos | `AiInsightGeneratorService` já gera conversões, gargalos, tendências |

### Features que Faltavam (dores reais não cobertas)

A versão anterior focou em "features legais" e esqueceu dores reais:

1. **Google Places Autocomplete** — Geocoding automático ao cadastrar terreno
2. **Cálculo de Área Útil** — Descontando APP, declividade, áreas de preservação
3. **Audit Trail de Viabilidade** — Quem mudou cada campo, quando, valor anterior vs novo
4. **Exportação de Dados (Portabilidade)** — Tenant pode exportar todos os dados
5. **Templates de Email para Proprietários** — Emails padronizados para documentos, propostas, follow-up
6. **Alertas de Legislação** — Mudanças de zoneamento/plano diretor
7. **Relatório de Portfólio para Investidores** — PDF executivo com mapa, KPIs, timeline
8. **Batch Operations** — Atualizar múltiplos terrenos de uma vez
9. **Saved Views/Filtros** — Salvar filtros como views personalizadas

---

## Features Finais (25 itens viáveis)

### TIER 1 — Quick Wins (1-3 meses)

Features de baixo esforço e alto impacto. Podem ser implementadas imediatamente.

#### 1. Importador de Planilhas (Simplificado)

**O que é:** Upload de Excel/CSV com terrenos para importação em lote.

**Por que importa:** Empresas migram de planilhas. Sem isso, onboarding leva dias.

**Implementação:**
- Upload de arquivo Excel/CSV
- Mapeamento manual de colunas (usuário seleciona qual coluna é nome, endereço, área, etc.)
- Preview com validação antes de importar
- Relatório de sucesso/falha
- Geocoding automático via Google Places após import

**Esforço:** 2 semanas

---

#### 2. Google Places Autocomplete

**O que é:** Autocomplete de endereço ao cadastrar terreno, preenchendo cidade, estado, CEP e coordenadas automaticamente.

**Por que importa:** Elimina erro de digitação, garante dados consistentes, melhora qualidade do banco.

**Implementação:**
- Integração com Google Places API no frontend
- Ao selecionar endereço, preenche: cidade, estado, CEP, latitude, longitude
- Busca cidade correspondente na tabela `cidades` pelo código IBGE
- Geocoding reverso para validar

**Esforço:** 1 semana

---

#### 3. Alertas Proativos por Email

**O que é:** Notificações automáticas quando algo precisa de atenção.

**Por que importa:** Usuário não pode ficar logado o tempo todo. Alertas previnem estagnação.

**Implementação:**
- Estender `AiMonitorService` que já existe
- Enviar email quando:
  - Terreno parado > 30 dias no mesmo estágio
  - Etapa de legalização vence em 7 dias
  - Viabilidade aguardando aprovação > 15 dias
  - Tarefa atribuída ao usuário está atrasada
- Frequência: diário (agregar alertas, não enviar um por um)
- Configurável por usuário (ligar/desligar por tipo)

**Esforço:** 2 semanas

---

#### 4. Relatórios Semanais Automáticos

**O que é:** Email toda segunda-feira com resumo da semana anterior.

**Por que importa:** Mantém gestores informados sem precisar fazer login.

**Implementação:**
- Job agendado (domingo à noite ou segunda cedo)
- Resumo inclui:
  - Terrenos criados na semana
  - Viabilidades aprovadas/reprovadas
  - Contratos assinados
  - Etapas de legalização concluídas
  - Terrenos que precisam de atenção
  - Próximos vencimentos (7 dias)
- Personalizável: usuário escolhe quais métricas receber
- Opt-in/opt-out por usuário

**Esforço:** 1-2 semanas

---

#### 5. Programa de Indicação

**O que é:** Tenant indica outro tenant, ambos ganham recompensa.

**Por que importa:** Indicações têm 4x mais conversão que outros canais. CAC reduzido.

**Implementação:**
- Link de indicação único por tenant
- Ambos ganham 1 mês grátis ou 20% de desconto por 3 meses
- Dashboard simples de indicações (quem indicou, status)
- Rastreamento via cookie + registro no signup

**Esforço:** 1 semana

---

#### 6. Integração Google Calendar

**O que é:** Sync de prazos e reuniões com Google Calendar.

**Por que importa:** Usuários já usam calendário. Prazos de legalização e reuniões de comitê precisam estar visíveis.

**Implementação:**
- OAuth com Google Calendar API
- Criar eventos automaticamente para:
  - Prazos de etapas de legalização
  - Reuniões de comitê
  - Vencimentos de tarefas
- Sync one-way (SIGAPP → Calendar)
- Configurável por usuário

**Esforço:** 2 semanas

---

#### 7. Batch Operations

**O que é:** Ações em lote sobre múltiplos terrenos.

**Por que importa:** Gestores com 50+ terrenos precisam atualizar status, atribuir responsável, exportar seleção em lote.

**Implementação:**
- Seleção múltiplica na listagem de terrenos
- Ações disponíveis:
  - Mudar responsável
  - Mudar regional
  - Exportar seleção (PDF/Excel)
  - Arquivar/descartar
- Confirmação antes de executar

**Esforço:** 1-2 semanas

---

#### 8. Saved Views / Filtros Salvos

**O que é:** Salvar combinações de filtros como views personalizadas.

**Por que importa:** Usuários aplicam os mesmos filtros repetidamente. Salvar economiza tempo.

**Implementação:**
- Botão "Salvar filtro atual como view"
- Views aparecem como tabs/abas na listagem de terrenos
- Views compartilháveis entre usuários do mesmo tenant
- Views padrão: "Todos", "Em análise", "Meus terrenos", "Atrasados"

**Esforço:** 1 semana

---

### TIER 2 — Features Core (3-6 meses)

Features de médio esforço e alto impacto. Melhoram significativamente a experiência.

#### 9. Comparador de Terrenos (Side-by-Side)

**O que é:** Comparar 2-4 terrenos lado a lado com dados reais.

**Por que importa:** Gestores precisam decidir qual terreno priorizar. Comparação visual facilita decisão.

**Implementação:**
- Seleção de 2-4 terrenos na listagem
- Tabela comparativa com:
  - Área, valor, preço/m²
  - Cidade, estado, regional
  - Status workflow, score IA
  - VGV estimado, margem
  - Número de documentos, tarefas pendentes
  - Dias no estágio atual
- Destaque do "melhor" em cada métrica
- Export como PDF

**Esforço:** 2 semanas

---

#### 10. Templates de Viabilidade por Tipologia

**O que é:** Premissas pré-configuradas por tipo de projeto.

**Por que importa:** Configurar premissas do zero é trabalhoso e propenso a erros. Templates aceleram e padronizam.

**Implementação:**
- Templates pré-configurados:
  - Loteamento aberto (econômico, médio, alto padrão)
  - Condomínio horizontal
  - Vertical econômico (MCMV)
  - Vertical médio padrão
- Cada template carrega premissas completas (impostos, custos, curvas, financiamento)
- Extensão do model `PremissasViabilidade` (campo `template_slug`)
- Usuário pode criar seus próprios templates a partir de premissas existentes

**Esforço:** 2-3 semanas

---

#### 11. Comparador de Cenários "What-If"

**O que é:** Criar cópias de viabilidade com parâmetros diferentes e comparar KPIs.

**Por que importa:** "E se o VGV fosse 10% maior? E se o prazo de obra fosse 30 meses?" — perguntas que todo gestor faz.

**Implementação:**
- Botão "Duplicar como cenário" na viabilidade (já existe `duplicate`)
- Usuário altera parâmetros na cópia
- Tela de comparação com:
  - KPIs lado a lado (VGV, margem, TIR, payback, lucro líquido)
  - Diferença percentual entre cenários
  - Gráfico de sensibilidade simples (margem vs VGV)
- Até 4 cenários simultâneos

**Esforço:** 2-3 semanas

---

#### 12. Audit Trail de Viabilidade

**O que é:** Log completo de quem alterou cada campo da viabilidade, quando, valor anterior vs novo.

**Por que importa:** Viabilidades são documentos financeiros críticos. Rastreabilidade é essencial para governança.

**Implementação:**
- Model `ViabilidadeAuditLog` (viabilidade_id, campo, valor_anterior, valor_novo, user_id, timestamp)
- Observers no model `Viabilidade` para capturar mudanças
- Endpoint `GET /viabilidades/{id}/audit-log`
- Filtros por campo, usuário, período

**Esforço:** 1-2 semanas

---

#### 13. Exportação de Portfólio para Investidores

**O que é:** PDF executivo com visão agregada de todos os terrenos do tenant.

**Por que importa:** Incorporadoras apresentam portfólio para investidores, bancos e sócios. Hoje fazem manualmente.

**Implementação:**
- Endpoint `GET /terrenos/export/portfolio-pdf`
- Seções do PDF:
  - Resumo executivo (total de terrenos, VGV agregado, margem média)
  - Mapa com todos os terrenos plotados
  - Tabela com top 10 terrenos por VGV
  - Gráfico de distribuição por estágio
  - Timeline de eventos recentes
- Template personalizável com logo do tenant

**Esforço:** 2-3 semanas

---

#### 14. Cálculo de Área Útil

**O que é:** A partir do polígono do terreno, calcular área descontando APP, declividade e áreas de preservação.

**Por que importa:** Área total ≠ área útil. Incorporadoras precisam saber quanto podem efetivamente lotar.

**Implementação:**
- Integrar com dados de topografia (SRTM/DEM) para declividade
- Integrar com dados de APP (hidrografia, nascentes)
- Calcular automaticamente:
  - Área total
  - Área com declividade > 30% (inutilizável)
  - Área de APP (raio de 50m de cursos d'água, 15m de nascentes)
  - Área útil = total - APP - declividade
  - % de aproveitamento
- Exibir no detalhe do terreno
- Recalcular quando polígono for alterado

**Esforço:** 3-4 semanas

---

#### 15. Notificações Configuráveis

**O que é:** Usuário escolhe quais notificações receber e por qual canal.

**Por que importa:** Nem todos querem as mesmas notificações. Alguns preferem email, outros push, outros nenhum.

**Implementação:**
- Tela de configurações de notificação por usuário
- Categorias:
  - Workflow (transições de status)
  - Viabilidade (aprovações, reprovações)
  - Legalização (etapas atrasadas, concluídas)
  - Tarefas (atribuições, vencimentos)
  - Comitê (pareceres, decisões)
- Canais por categoria: email, push, in-app, nenhum
- Default: todas ativas por email

**Esforço:** 2 semanas

---

### TIER 3 — Expansão (6-12 meses)

Features de alto esforço e alto impacto. Expansão de receita e ciclo de vida.

#### 16. Módulo de Permutas (Integrado à Viabilidade)

**O que é:** Gestão de cenários de permuta (terreno por unidades) integrada ao motor de viabilidade.

**Por que importa:** Permuta é comum no mercado brasileiro. Impacta diretamente o DRE e o fluxo de caixa.

**Implementação:**
- Campos em `TerrenoProduto`: `permuta_percentual`, `permuta_unidades`, `permuta_valor_unitario`
- Cálculo automático no DRE:
  - Receita de permuta (unidades × valor)
  - Custo de permuta (construção das unidades)
  - Impacto no fluxo de caixa (entrega escalonada)
  - Impostos específicos (IR sobre permuta, ITBI)
- Cronograma de entrega de unidades
- Controle de unidades entregues vs pendentes

**Pricing:** Incluso no plano (não add-on). Diferencial competitivo.

**Esforço:** 4-6 semanas

---

#### 17. Módulo Financeiro Pós-Contrato

**O que é:** Acompanhamento financeiro após assinatura de contrato.

**Por que importa:** O sistema termina no contrato assinado. Mas o pagamento ao proprietário é parcelado, e as liberações CEF são ao longo da obra.

**Implementação:**
- Novos models: `PagamentoTerreno`, `LiberacaoCEF`
- Cronograma de pagamentos ao proprietário
- Controle de pagamentos realizados vs pendentes
- Solicitação de liberação CEF com documentos
- Acompanhamento de medições
- Fluxo de caixa projetado do terreno
- Alertas de vencimentos

**Pricing:** Incluso no plano Master/Pro. Diferencial competitivo.

**Esforço:** 6-8 semanas

---

#### 18. API Pública

**O que é:** REST API documentada para integrações customizadas.

**Por que importa:** Grandes clientes precisam integrar com ERPs (SAP, TOTVS), CRMs (Salesforce) e sistemas internos.

**Implementação:**
- Documentação OpenAPI/Swagger
- Autenticação OAuth2 (além de Sanctum)
- Webhooks configuráveis:
  - Terreno criado/atualizado
  - Viabilidade aprovada/reprovada
  - Contrato assinado
  - Etapa de legalização concluída
- Rate limiting por plano
- Sandbox para testes
- SDKs (JavaScript, Python) — gerados automaticamente do OpenAPI

**Pricing:** Add-on Enterprise (+R$2.000/mês)

**Esforço:** 8-12 semanas

---

#### 19. Templates de Email para Proprietários

**O que é:** Templates de email padronizados para comunicação com proprietários.

**Por que importa:** Incorporadoras enviam os mesmos emails repetidamente (solicitar documentos, enviar proposta, follow-up). Templates economizam tempo e padronizam comunicação.

**Implementação:**
- Templates pré-configurados:
  - Solicitação de documentos (RG, CPF, certidões)
  - Envio de proposta
  - Follow-up após proposta
  - Agradecimento após reunião
  - Convite para assinatura
- Variáveis automáticas: nome do proprietário, nome do terreno, valor, data
- Histórico de emails enviados por proprietário
- Integração com Resend (já usado pelo sistema)

**Esforço:** 2 semanas

---

#### 20. Alertas de Legislação

**O que é:** Monitorar mudanças de zoneamento e plano diretor nas cidades onde o tenant tem terrenos.

**Por que importa:** Mudança de zoneamento pode inviabilizar ou valorizar um terreno. Gestores precisam saber rápido.

**Implementação:**
- Monitorar fontes públicas:
  - Diário Oficial do município
  - Site da prefeitura (quando disponível)
- Alertar quando detectar:
  - Mudança de zoneamento
  - Revisão de plano diretor
  - Nova lei de uso do solo
  - Alteração de coeficiente de aproveitamento
- Vincular alerta aos terrenos afetados (por cidade/bairro)
- Notificação por email + in-app

**Esforço:** 4-6 semanas (complexidade varia muito por cidade)

---

#### 21. Exportação de Dados (Portabilidade)

**O que é:** Tenant pode exportar todos os seus dados a qualquer momento.

**Por que importa:** Transparência e compliance. Tenants precisam saber que podem sair com seus dados. LGPD.

**Implementação:**
- Endpoint `GET /tenant/export`
- Exporta em JSON/CSV:
  - Terrenos com todos os campos
  - Viabilidades com DREs
  - Documentos (ZIP com arquivos)
  - Usuários, roles, permissões
  - Comentários, tarefas, atividades
- Job assíncrono (pode ser pesado)
- Notificação quando exportação estiver pronta
- Link de download com expiração (7 dias)

**Esforço:** 2-3 semanas

---

#### 22. Relatório de Conformidade por Terreno

**O que é:** Checklist dinâmico mostrando o que falta para avançar o terreno para o próximo estágio.

**Por que importa:** Gestores precisam saber rapidamente "o que falta para este terreno sair de 'em análise' para 'viabilidade aprovada'?"

**Implementação:**
- Endpoint `GET /terrenos/{id}/checklist`
- Para cada transição de workflow, listar:
  - Documentos obrigatórios faltantes
  - Dados obrigatórios não preenchidos
  - Aprovações pendentes
  - Tarefas não concluídas
- Status visual: completo (verde), pendente (amarelo), bloqueado (vermelho)
- Percentual de completude

**Esforço:** 2 semanas

---

### TIER 4 — Futuro (12+ meses)

Features de muito alto esforço ou que dependem de maturidade do produto.

#### 23. Análise de Imagens de Satélite

**O que é:** IA analisa imagens de satélite do terreno para identificar vegetação, uso do solo, vizinhança.

**Implementação:** Integração com Sentinel/Planet Labs, computer vision. 8-12 semanas.

#### 24. Integração com Cartórios (quando viável)

**O que é:** Consulta automática de matrícula e certidões.

**Implementação:** Depende de APIs que hoje não existem na maioria dos cartórios. Monitorar evolução do e-Notariado e Registrato.

#### 25. Marketplace de Dados

**O que é:** Venda de dados agregados de mercado (VGV médio, preço m², tempo de legalização por cidade).

**Implementação:** Depende de massa crítica de tenants. Começar a coletar dados anonimizados agora, lançar quando tiver 30+ tenants ativos.

---

## Matriz de Prioridade

| Tier | Features | Esforço Total | Impacto | Prazo |
|------|----------|---------------|---------|-------|
| **1 — Quick Wins** | 8 | ~10 semanas | Alto | 1-3 meses |
| **2 — Core** | 7 | ~18 semanas | Muito Alto | 3-6 meses |
| **3 — Expansão** | 7 | ~28 semanas | Alto | 6-12 meses |
| **4 — Futuro** | 3 | ~24 semanas | Médio-Alto | 12+ meses |

---

## Roadmap

### Trimestre 1 — Onboarding e Quick Wins

| Feature | Semanas | Impacto |
|---------|---------|---------|
| Google Places Autocomplete | 1 | Alto |
| Saved Views / Filtros Salvos | 1 | Médio |
| Importador de Planilhas | 2 | Muito Alto |
| Alertas Proativos por Email | 2 | Alto |
| Relatórios Semanais | 2 | Médio |
| Programa de Indicação | 1 | Médio |
| Integração Google Calendar | 2 | Médio |
| Batch Operations | 2 | Médio |

### Trimestre 2 — Análise e Produtividade

| Feature | Semanas | Impacto |
|---------|---------|---------|
| Audit Trail de Viabilidade | 2 | Alto |
| Templates de Viabilidade | 3 | Alto |
| Comparador de Terrenos | 2 | Alto |
| Comparador de Cenários What-If | 3 | Muito Alto |
| Relatório de Conformidade | 2 | Alto |
| Exportação de Portfólio | 3 | Alto |

### Trimestre 3 — Expansão de Receita

| Feature | Semanas | Impacto |
|---------|---------|---------|
| Cálculo de Área Útil | 4 | Alto |
| Notificações Configuráveis | 2 | Médio |
| Módulo de Permutas | 6 | Muito Alto |
| Templates de Email | 2 | Médio |
| Exportação de Dados | 3 | Médio |

### Trimestre 4 — Escala

| Feature | Semanas | Impacto |
|---------|---------|---------|
| Módulo Financeiro Pós-Contrato | 8 | Muito Alto |
| API Pública | 12 | Alto |
| Alertas de Legislação | 6 | Médio |

---

## O que NÃO fazer (lições aprendidas)

1. **Não implementar features genéricas** — Blockchain, AR, gamificação não resolvem dores de incorporadoras
2. **Não duplicar o que a IA já faz** — O sistema já tem scoring, predição, anomalias, insights. Estender, não recriar.
3. **Não criar marketplaces** — Two-sided marketplace é produto separado, não feature
4. **Não integrar com sistemas que não têm API** — Cartórios e prefeituras brasileiras na maioria não têm APIs. Scraping é frágil.
5. **Não cobrar por features que deveriam ser core** — Permutas impactam o DRE. Devem estar no plano, não como add-on.
6. **Não criar 50 features** — 25 features bem feitas > 50 features pela metade

---

**Documento atualizado em:** 26 de maio de 2026  
**Total de features:** 25 (reduzido de 50)  
**Esforço estimado total:** 12 meses de desenvolvimento contínuo
