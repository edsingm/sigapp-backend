# Plano de saneamento de features, entitlements e limites

## Resumo

Executar os ajustes em três fases:

1. Integridade operacional: armazenamento, validação, concorrência e cache.
2. Consistência do catálogo: escopos, aliases, defaults e projeção da viabilidade.
3. Integração: matriz efetiva no `/start`, relação com módulos/RBAC e saneamento arquitetural.

A matriz comercial Broker → Básico → Master → Pro permanece, assim como preços e limites atuais. O Broker mantém `mobile.capture`, mas anexos são bloqueados por possuir `storage_gb = 0`.

## Mudanças de implementação

### Fase 1 — Integridade e limites

- Criar validação orientada ao `EntitlementType` em todas as APIs administrativas:
  - `feature`: boolean estrito.
  - limites: inteiro `>= 0` ou exatamente `-1`.
  - `ai_budget`: número não negativo.
  - Rejeitar valores incompatíveis, IDs duplicados na sincronização e mudança de tipo quando houver vínculos.
- Manter `default_value` como template administrativo:
  - Não participa da autorização.
  - É validado conforme o tipo.
  - Se uma associação for criada sem `value`, copiar o default para o vínculo.
  - Persistir sempre um valor explícito em `plan_entitlements` e `tenant_entitlements`.
- Tornar alterações de catálogo e matriz transacionais. Atualização, exclusão, mudança de chave/tipo, sincronização e seeder devem invalidar imediatamente o cache de todos os planos afetados.
- Centralizar o cálculo de armazenamento em repository e contabilizar objetos físicos únicos por `(disk, path)`:
  - documentos;
  - relatórios de IA;
  - anexos mobile;
  - execuções do report builder;
  - exports assíncronos.
  Registros que apontam para o mesmo arquivo, como anexo mobile já convertido em documento, contam uma vez. Arquivos expirados continuam contando até serem fisicamente removidos e terem o caminho limpo.
- Aplicar `enforce.limits:storage_gb` ao upload de anexos mobile. No Broker, captura textual continua permitida e qualquer anexo não vazio retorna `PLAN_LIMIT_EXCEEDED`.
- Em Jobs que geram arquivos, verificar o tamanho real sob lock antes de concluir. Se exceder a franquia, apagar o arquivo recém-gerado, marcar a execução como falha segura e não contabilizá-lo.
- Proteger `users`, `terrenos`, `products` e `storage_gb` com lock distribuído por tenant/recurso envolvendo verificação e criação/finalização. Falha ao adquirir o lock retorna erro traduzido e não cria o recurso.
- Adicionar uma auditoria operacional `plans:audit-entitlements`, read-only por padrão, para detectar valores inválidos, aliases legados, planos sem matriz, arquivos não contabilizados e dependências inconsistentes.

### Fase 2 — Catálogo, aliases e enforcement

- Adicionar `scope` ao catálogo, tipado por enum:
  - `api`: autorização em rota ou projeção de resposta;
  - `ui`: visibilidade/comportamento exclusivamente do frontend;
  - `composite`: capacidade formada por outras features;
  - `internal`: limite ou comportamento operacional.
- Expor `scope` no recurso administrativo de entitlement. Features exigem scope; limites usam `internal`.
- Criar teste de arquitetura que confronte o catálogo com:
  - gates `check.feature:*`;
  - projeções de resposta registradas;
  - aliases oficialmente reconhecidos.
  Toda feature `api` deve possuir enforcement; chaves desconhecidas nas rotas devem falhar o teste.
- Substituir os aliases de projetos:
  - `projects.enabled`: CRUD do módulo.
  - `projects.planning`: milestones, dependências e riscos.
  - Migrar valores de `projects_room` e `projects.room` para as novas chaves.
  - Rotas passam a usar as chaves novas.
  - Resolver e serializar os aliases antigos por duas releases para compatibilidade, sem mantê-los como entitlements comerciais independentes.
- Dar semântica distinta às features do comitê:
  - `committee.meeting`: sessões, agenda e participantes.
  - `committee.meeting_mode`: iniciar/encerrar reunião, ata e aprovação.
- Aplicar as features granulares da viabilidade no backend por meio de um projetor único:
  - `summary`: resumo e resumo de produtos.
  - `kpis`: indicadores.
  - `dre`: DRE gerencial, caixa, POC e reconciliação.
  - `cash_flow`: fluxos mensais e totais.
  - `comercial`: dados detalhados de produtos/comercialização.
  - `premises`: parâmetros utilizados e snapshot de premissas.
  - `charts`: séries próprias para gráficos.
- O payload persistido `resultados_dre` nunca deve sair cru. Manter a chave por compatibilidade, mas filtrar seu conteúdo pela matriz efetiva. Se uma seção desabilitada for pedida explicitamente em `include`, responder `403 PLAN_FEATURE_DISABLED`; no payload padrão, apenas omiti-la.
- Classificar como UI as capacidades sem dados exclusivos no backend, como `home`, acessibilidade e personalizações puramente visuais.
- Mover consultas de extras e módulos dos Services para repositories, conforme a arquitetura do projeto.

### Fase 3 — Bootstrap, módulos e matriz efetiva

- Manter o contrato atual de `GET /api/v1/modules` inalterado.
- Adicionar ao `GET /api/v1/start` um campo aditivo:

```json
{
  "access": {
    "features": {},
    "limits": {},
    "modules": {
      "projects": {
        "plan_enabled": false,
        "rbac_allowed": true,
        "available": false,
        "reasons": ["plan"]
      }
    }
  }
}
```

- `features` e `limits` devem refletir plano mais overrides do tenant, não apenas o plano base.
- Calcular `available` como interseção de módulo ativo, plano e RBAC. Mapas compostos:
  - `data`: qualquer uma entre `product_settings`, `regionals` e `territorial_base`.
  - `configurations` e `admin`: somente módulo ativo + RBAC.
  - `brokers`: feature `prospection`.
  - `reports`: `reports.builder`.
  - Demais módulos usam sua feature principal correspondente.
- O frontend pode continuar consumindo `/modules`, mas passa a usar `/start.access` como fonte oficial para disponibilidade, upgrade e overrides.
- Atualizar o `AGENTS.md` com o novo catálogo, scopes, aliases, política de storage, projeção da viabilidade e contrato do `/start`.

## Testes e critérios de aceitação

- Unitários para normalização de booleanos, limites, `-1`, orçamento de IA, defaults e rejeição de valores incompatíveis.
- Feature tests das APIs administrativas para tipo incorreto, duplicidade, mudança de tipo vinculada, default omitido e invalidação imediata do cache.
- Testes de storage cobrindo deduplicação, anexos mobile pendentes/convertidos, relatórios, exports, expiração e Broker sem anexos.
- Teste concorrente garantindo que duas criações no último slot não ultrapassem o limite.
- Testes de Jobs garantindo remoção do arquivo quando o tamanho final exceder a franquia.
- Testes por plano e override tenant para todas as seções da viabilidade, inclusive bloqueio de `include` explícito e ausência de `resultados_dre` cru.
- Testes de migration e compatibilidade dos aliases de projetos em SQLite e PostgreSQL.
- Teste contratual garantindo `/modules` inalterado e `/start.access` aditivo, com plano, RBAC e override.
- Teste de arquitetura catálogo × routes/projections.
- Rodar ao final: suíte Architecture, testes focados de planos/tenant/storage/viabilidade, suíte completa, PHPStan nível 8 e Pint em modo `--test`.

## Rollout e premissas

- Executar migration central, seeder idempotente e auditoria antes de liberar o novo código.
- Manter aliases antigos de projetos por duas releases; registrar uso legado e removê-los somente quando não houver consumidores.
- A projeção da viabilidade é uma correção de enforcement comercial: clientes de planos inferiores deixarão de receber seções não contratadas.
- Overrides continuam administrativos e sobrepõem o plano base. Integração do preço desses extras com itens de assinatura Stripe não faz parte deste ajuste.
- Nenhum preço ou limite da matriz muda; especificamente, Broker permanece com 0 GB e captura sem anexos.
