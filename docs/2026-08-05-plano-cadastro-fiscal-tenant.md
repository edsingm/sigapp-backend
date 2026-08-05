# Cadastro fiscal obrigatório do tenant após o primeiro login

**Data:** 2026-08-05  
**Escopo:** backend Laravel; contrato para implementação posterior no `frontend_tenant`  
**Ticket:** [SIG-17](https://sigapp.youtrack.cloud/issue/SIG-17)

## Objetivo

Manter o cadastro público atual sem novos campos e, depois que o tenant for provisionado, exigir que o administrador complete os dados necessários para futura emissão de nota fiscal antes de acessar os módulos de negócio.

O backend deve ser a fonte de verdade da pendência. O frontend apenas interpreta o estado retornado no login/`GET /api/v1/start`, redireciona para a página de conclusão e trata a resposta de pré-condição quando tentar acessar uma rota bloqueada.

## Compatibilidade com o CNPJ alfanumérico

A Receita Federal iniciou a entrada em produção do CNPJ alfanumérico em julho de 2026. Os CNPJs existentes permanecem válidos e não são alterados; os formatos numérico e alfanumérico coexistem. O novo identificador mantém 14 posições: as 12 primeiras aceitam números e letras maiúsculas de `A` a `Z`, enquanto as duas últimas permanecem como dígitos verificadores numéricos. O cálculo dos verificadores continua sendo módulo 11, atribuindo a cada caractere o valor ASCII menos 48 e aplicando os pesos de 2 a 9 da direita para a esquerda.

Consequências para este projeto:

- o backend não pode normalizar CNPJ com uma função que remova todas as não-numéricas;
- o campo permanece textual, sem cast numérico e sem limite que descarte letras;
- a validação aceita CNPJs numéricos legados e alfanuméricos novos;
- a formatação segue `XX.XXX.XXX/XXXX-XX`, com letras preservadas;
- textos de IA/telemetria devem mascarar também CNPJs alfanuméricos.

Fontes oficiais consultadas: [portal do CNPJ Alfanumérico da Receita Federal](https://www.gov.br/receitafederal/pt-br/acesso-a-informacao/acoes-e-programas/programas-e-atividades/cnpj-alfanumerico), [manual do cálculo do DV](https://www.gov.br/receitafederal/pt-br/centrais-de-conteudo/publicacoes/documentos-tecnicos/cnpj/manual-dv-cnpj.pdf) e [perguntas e respostas da Receita Federal](https://www.gov.br/receitafederal/pt-br/centrais-de-conteudo/publicacoes/perguntas-e-respostas/cnpj/cnpj-alfanumerico.pdf).

## Estado atual relevante

- O signup público (`SignupController` -> `TenantSignupService` -> Stripe -> `CreateFullTenantJob`) coleta somente plano, organização, slug e credenciais do administrador.
- O Stripe Checkout já solicita Tax ID e endereço, mas esses dados não são persistidos como cadastro fiscal canônico do SIGAPP.
- O primeiro usuário provisionado é o ADMIN do tenant.
- `GET /api/v1/start` já é o bootstrap oficial de tenant, usuário, módulos e matriz efetiva de acesso.
- Existe onboarding de produto por usuário em `/me/onboarding`, porém ele é opcional, depende de entitlement e não deve ser reutilizado para uma obrigação fiscal do tenant.

## Decisões de produto e arquitetura

1. O cadastro fiscal pertence ao tenant central (`App\Models\Central\Tenant`), não ao usuário e não ao schema de cada tenant.
2. O nome comercial atual do workspace (`tenants.name`) permanece independente da razão social/nome fiscal.
3. O signup público não muda. Dados já disponíveis, como nome da organização e e-mail do administrador, podem ser usados apenas como sugestão inicial; não marcam o perfil como concluído.
4. Somente ADMIN pode consultar ou alterar os dados fiscais completos.
5. Todos os usuários recebem o estado resumido da pendência no `/start`; usuários sem permissão recebem `can_complete: false` para que o frontend mostre uma orientação em vez de um formulário editável.
6. A obrigação é garantida também no backend por middleware. Não será apenas um redirect de interface.
7. Tenants existentes no momento do deploy serão considerados liberados para evitar bloqueio retroativo. Novos tenants nascerão com o perfil pendente.
8. O endereço é armazenado de forma estruturada. A consulta/autopreenchimento por CEP pertence ao frontend; o backend valida e normaliza o resultado recebido, sem introduzir dependência externa para CEP.
9. CPF/CNPJ e demais dados fiscais não serão gravados em logs, auditoria ou respostas genéricas. O documento fiscal será armazenado normalizado e criptografado em repouso.
10. O cadastro local será a fonte canônica para emissão de NFS-e. A sincronização do Customer no Stripe pode ocorrer depois da persistência local, mas falha no Stripe não pode apagar ou invalidar os dados já salvos.

## Modelo de dados

Criar uma migration central nova para `tenants`; migrations já aplicadas não serão alteradas.

| Campo | Tipo sugerido | Regra |
|---|---|---|
| `billing_profile_type` | string/enum | obrigatório: `pf` ou `pj` |
| `billing_tax_id` | text criptografado | CPF com 11 dígitos para PF; CNPJ com 14 para PJ; checksum válido |
| `billing_legal_name` | string | nome completo para PF; razão social para PJ |
| `billing_trade_name` | string nullable | obrigatório somente para PJ |
| `billing_email` | string | e-mail de faturamento válido |
| `billing_phone` | string | telefone normalizado, com DDD |
| `billing_postal_code` | string | CEP brasileiro com 8 dígitos |
| `billing_street` | string | obrigatório |
| `billing_number` | string | obrigatório; aceita `S/N` |
| `billing_complement` | string nullable | opcional |
| `billing_neighborhood` | string | obrigatório |
| `billing_city` | string | obrigatório |
| `billing_state` | char(2) | UF válida |
| `billing_country` | char(2) | default `BR` |
| `billing_municipal_registration` | string nullable | opcional e permitido somente para PJ |
| `billing_tax_regime` | string/enum nullable | opcional, somente PJ |
| `billing_profile_required` | boolean | `true` para novos tenants; `false` somente para legado dispensado no rollout |
| `billing_profile_completed_at` | timestamp nullable | preenchido atomicamente quando todos os obrigatórios forem válidos |

Adicionar enum nativo para tipo de pessoa e, se o conjunto de regimes for fechado pelo produto/contabilidade, enum específico para regime tributário. Caso o catálogo ainda não esteja aprovado, aceitar `billing_tax_regime` como string curta normalizada nesta entrega e fechar o catálogo antes da integração NFS-e.

O model `Tenant` deve atualizar `#[Fillable]`, PHPDoc e casts. `billing_tax_id` deve usar cast criptografado e `billing_profile_completed_at` deve usar datetime. Não criar índice de unicidade no CPF/CNPJ: uma mesma entidade jurídica pode legitimamente operar mais de um workspace.

### Migração e compatibilidade

- Adicionar todas as colunas como nullable para deploy compatível.
- Adicionar `billing_profile_required` com default `true` e, na própria migration, alterar somente os tenants já existentes para `false`. Isso cria uma dispensa de rollout explícita sem fabricar CPF/CNPJ, endereço ou um falso `completed_at`.
- Novos tenants permanecem com `billing_profile_required = true` e `billing_profile_completed_at = null`.
- `down()` remove todas as colunas novas.
- A migration deve funcionar em PostgreSQL e SQLite `:memory:`.

## Regras de validação

Criar `UpdateTenantBillingProfileRequest` em `app/Http/Requests/Tenant/` com autorização real de ADMIN.

- Remover máscara de CPF/CNPJ, CEP e telefone antes da validação/persistência.
- Validar CPF/CNPJ por tamanho e dígitos verificadores, rejeitando sequências repetidas.
- Aplicar campos condicionais pelo tipo:
  - PF: CPF e nome completos obrigatórios; nome fantasia, inscrição municipal e regime tributário proibidos ou zerados.
  - PJ: CNPJ, razão social e nome fantasia obrigatórios; inscrição municipal e regime tributário opcionais.
- Validar e-mail, telefone com DDD, CEP com 8 dígitos, UF brasileira e limites de tamanho.
- Não aceitar `billing_profile_completed_at` do cliente; o Service define o timestamp.
- Usar somente `$request->validated()`.
- Chaves de erro e sucesso devem existir em `pt-br.json` e `en-us.json`.

## API proposta

As rotas ficam em `routes/tenant/account-billing.php`, fora do bloqueio de assinatura e fora do próprio bloqueio de perfil, mas dentro de `tenant.context`, `auth:sanctum`, `auth.tenant` e `throttle:api-auth` herdados do agregador.

### `GET /api/v1/tenant/billing-profile`

- Requer `tenant.admin`.
- Retorna `TenantBillingProfileResource` com os campos editáveis, estado, campos faltantes e `completed_at`.
- Nunca retorna IDs internos do Stripe.

### `PUT /api/v1/tenant/billing-profile`

- Requer `tenant.admin` e `UpdateTenantBillingProfileRequest`.
- Delega para `TenantBillingProfileService`.
- Persiste o conjunto completo em transaction, calcula a completude no servidor e atualiza `billing_profile_completed_at` atomicamente.
- Retorna o Resource atualizado e `TENANT_BILLING_PROFILE_UPDATED`.
- Em alteração posterior, mantém o perfil completo somente se o payload continuar válido.

Exemplo de entrada:

```json
{
  "type": "pj",
  "tax_id": "12.345.678/0001-95",
  "legal_name": "Empresa Exemplo Ltda.",
  "trade_name": "Empresa Exemplo",
  "email": "financeiro@exemplo.com.br",
  "phone": "+55 11 99999-9999",
  "address": {
    "postal_code": "01310-100",
    "street": "Avenida Paulista",
    "number": "1000",
    "complement": "Conjunto 101",
    "neighborhood": "Bela Vista",
    "city": "São Paulo",
    "state": "SP",
    "country": "BR"
  },
  "municipal_registration": null,
  "tax_regime": "simples_nacional"
}
```

## Contrato de login e bootstrap

Adicionar ao `TenantResource` usado por `GET /api/v1/start` apenas um resumo seguro:

```json
{
  "billing_profile": {
    "status": "incomplete",
    "required": true,
    "completed": false,
    "completed_at": null,
    "missing_fields": ["type", "tax_id", "legal_name", "phone", "address"],
    "required_action": "complete_tenant_billing_profile",
    "can_complete": true
  }
}
```

O mesmo estado deve estar disponível nas duas respostas que concluem o login tenant:

- login direto no subdomínio;
- troca de transfer ticket emitido pelo broker central.

Para evitar divergência entre os dois fluxos, montar esse fragmento em um único Service/Resource reutilizável. O frontend poderá redirecionar imediatamente e confirmar o estado no `/start` após recarregar a aplicação.

Não usar `setup_completed_at`: esse campo representa provisionamento técnico do tenant. Não usar `/me/onboarding`: ele representa descoberta de produto por usuário.

## Enforcement no backend

Criar middleware com alias `tenant.billing-profile.complete` e aplicá-lo ao grupo de módulos de negócio hoje protegido por `CheckSubscriptionStatus` em `routes/tenant.php`.

Enquanto o perfil estiver incompleto, devem continuar acessíveis:

- login direto e exchange ticket;
- `GET /api/v1/start` e `GET /api/v1/auth/me`;
- logout, refresh e locale;
- `GET`/`PUT /api/v1/tenant/billing-profile`;
- endpoints estritamente necessários de assinatura/pagamento para não impedir regularização financeira.

Demais rotas protegidas retornam HTTP `428 Precondition Required` no envelope padrão:

```json
{
  "success": false,
  "error": {
    "code": "TENANT_BILLING_PROFILE_INCOMPLETE",
    "message": "Complete os dados fiscais da empresa para continuar.",
    "details": {
      "required_action": "complete_tenant_billing_profile",
      "can_complete": true,
      "missing_fields": ["tax_id", "phone", "address"]
    }
  }
}
```

O middleware não deve consultar diretamente Eloquent; a leitura/computação da completude fica no `TenantBillingProfileService`. O bloqueio ocorre quando `billing_profile_required = true` e os valores persistidos não formam um perfil válido. O timestamp sozinho não pode liberar acesso. Para tenants legados com `billing_profile_required = false`, o resumo usa `status: exempt` e não os bloqueia; essa dispensa não significa que estejam prontos para emissão de NFS-e.

## Camadas e arquivos previstos

- `database/migrations/*_add_billing_profile_to_tenants_table.php`
- `app/Enums/TenantBillingProfileType.php`
- `app/Models/Central/Tenant.php`
- `app/Repositories/Contracts/TenantRepositoryInterface.php`
- `app/Repositories/TenantRepository.php`
- `app/Services/Billing/TenantBillingProfileService.php`
- `app/Http/Requests/Tenant/UpdateTenantBillingProfileRequest.php`
- `app/Http/Resources/TenantBillingProfileResource.php`
- `app/Http/Middleware/EnsureTenantBillingProfileCompleted.php`
- `app/Http/Controllers/Api/V1/Tenant/TenantController.php`
- `app/Http/Controllers/Api/V1/Tenant/Common/ModulesController.php`
- `app/Http/Controllers/Api/V1/TenantAuthController.php`
- `routes/tenant/account-billing.php`, `routes/tenant.php`, `bootstrap/app.php`
- `resources/lang/pt-br.json`, `resources/lang/en-us.json`
- testes Feature, Unit e Architecture relacionados
- atualização cirúrgica do `AGENTS.md`, pois haverá nova rota, middleware e regra de login

O Controller deve permanecer thin. Queries e updates ficam no repository; regra de completude, normalização e eventual sincronização com Stripe ficam no Service.

## Segurança, privacidade e auditoria

- Tratar CPF/CNPJ, endereço, e-mail e telefone como PII.
- Não incluir o documento fiscal completo em logs, exceptions, `details`, eventos de auditoria ou telemetria.
- Quando necessário em auditoria, registrar somente tipo, status da operação e final mascarado do documento.
- Restringir leitura detalhada e mutação a ADMIN.
- Resumo do `/start` não contém os valores fiscais.
- Se houver sincronização com Stripe, usar apenas o SDK já adotado via Cashier/`StripeClient`; não adicionar pacote.
- Não fazer chamada a serviço de CEP no backend nesta entrega.

## Estratégia de implementação

### Fase 1 — persistência e domínio

1. Criar enum(s), migration central e casts/fillable do Tenant.
2. Implementar normalização, checksum CPF/CNPJ e cálculo determinístico de campos faltantes.
3. Adicionar operações do repository e o `TenantBillingProfileService` transacional.

### Fase 2 — API e bootstrap

1. Criar FormRequest e Resource próprios.
2. Adicionar `GET` e `PUT /tenant/billing-profile` para ADMIN.
3. Expor resumo seguro no `TenantResource`, `/start`, login direto e exchange ticket.
4. Documentar o contrato no Scramble por tipos/PHPDocs.

### Fase 3 — bloqueio obrigatório

1. Criar e registrar o middleware.
2. Proteger módulos de negócio sem bloquear as rotas de regularização.
3. Padronizar resposta `428` e traduções.

### Fase 4 — Stripe e robustez

1. Avaliar prefill a partir dos dados já coletados no Checkout, sem marcar conclusão automaticamente.
2. Após save local, sincronizar nome/endereço/e-mail/telefone no Customer quando `stripe_id` existir.
3. Registrar falha segura de sincronização para retry futuro sem reverter o cadastro local.

### Fase 5 — testes e documentação

1. Cobrir persistência e regras PF/PJ.
2. Cobrir os dois fluxos de login e `/start`.
3. Cobrir bloqueio/liberação do middleware e autorização ADMIN.
4. Rodar Architecture, testes focados, suíte completa, PHPStan nível 8 e Pint.
5. Atualizar `AGENTS.md` e, quando o frontend for iniciado, publicar o contrato de integração.

## Testes obrigatórios

### Unit

- CPF válido/inválido, CNPJ válido/inválido, sequências repetidas e entradas mascaradas.
- Campos faltantes para PF e PJ.
- Campos específicos de PJ não aceitos para PF.
- `completed_at` só é definido com todos os obrigatórios válidos.
- Alteração posterior inválida remove/bloqueia a completude.

### Feature

- ADMIN consulta e conclui perfil PF.
- ADMIN consulta e conclui perfil PJ.
- `422` para CPF/CNPJ incompatível com o tipo, endereço incompleto e PJ sem nome fantasia.
- `403` para usuário não ADMIN consultar ou alterar os dados completos.
- Login direto e exchange ticket retornam a mesma `required_action`.
- `/start` retorna o resumo sem PII.
- Rota de negócio retorna `428` enquanto incompleto.
- `/start`, logout e perfil fiscal continuam acessíveis enquanto incompleto.
- Depois da conclusão, a mesma rota de negócio deixa de retornar `428` e segue para suas autorizações normais.
- Tenant legado migrado não é bloqueado.
- Tenant criado depois da migration nasce incompleto.
- Falha simulada do Stripe não perde os dados locais.

### Qualidade e arquitetura

- `php artisan test --testsuite=Architecture`
- testes Feature/Unit focados
- `composer test`
- `composer analyse`
- `./vendor/bin/pint --test`
- `php artisan route:cache` ou o teste de arquitetura de cache de rotas

## Critérios de aceite

- [ ] A tela pública de signup mantém exatamente os campos atuais e continua compatível com o payload existente.
- [ ] Todo novo tenant nasce com cadastro fiscal pendente.
- [ ] O backend informa a pendência no login direto, no exchange ticket e no `/start`.
- [ ] Apenas ADMIN pode consultar e salvar os dados fiscais completos.
- [ ] PF exige CPF e não exige dados exclusivos de PJ.
- [ ] PJ exige CNPJ, razão social e nome fantasia; inscrição municipal e regime tributário são opcionais.
- [ ] E-mail, telefone e endereço completo são obrigatórios para ambos.
- [ ] CPF/CNPJ, CEP e telefone são normalizados e validados no servidor.
- [ ] Módulos de negócio ficam bloqueados com `428 TENANT_BILLING_PROFILE_INCOMPLETE` até a conclusão.
- [ ] Rotas necessárias para autenticar, concluir o cadastro e regularizar cobrança continuam acessíveis.
- [ ] Após salvar um perfil válido, o acesso normal é liberado imediatamente.
- [ ] Dados fiscais completos não aparecem no `/start`, em logs ou em erros.
- [ ] Tenants existentes não são bloqueados pelo deploy.
- [ ] Testes, PHPStan, Pint e route cache passam.
- [ ] `AGENTS.md` é atualizado com a nova superfície e regra de enforcement.

## Dependência do frontend (ticket futuro)

O `frontend_tenant` deverá:

1. Ler `required_action` no resultado do login e no `/start`.
2. Redirecionar ADMIN para a nova página de completar cadastro.
3. Exibir mensagem de contato ao administrador para usuários sem `can_complete`.
4. Implementar formulário condicional PF/PJ e autopreenchimento por CEP.
5. Tratar `422` por campo e `428` globalmente.
6. Recarregar `/start` após o `PUT` e liberar a navegação somente quando `completed: true`.

## Fora de escopo

- Emissão efetiva de NFS-e e integração com prefeitura/provedor fiscal.
- Mudança visual ou de campos no signup público.
- Saneamento obrigatório imediato de tenants legados.
- Alteração do onboarding opcional de produto por usuário.
- Novo pacote externo para CPF/CNPJ ou CEP.
