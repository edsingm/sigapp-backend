# Análise Completa do Backend

Data da análise: 2026-04-22

## Resumo executivo

O backend tem uma base tecnicamente ambiciosa e já opera com conceitos maduros para um SaaS multi-tenant: separação entre aplicação central e tenant, tenancy por schema, autenticação com Sanctum, billing com Cashier, RBAC com Spatie Permission, feature gating por plano, middleware dedicado para contexto tenant e uma superfície relevante de testes automatizados.

Ao mesmo tempo, a implementação real hoje está em um estado de arquitetura híbrida. A convenção oficial do projeto define `Controller -> Service -> Repository`, validação via `FormRequest`, autorização consistente em `authorize()`/Policies e respostas sempre via `Resource`. Na prática, boa parte do código ainda faz query Eloquent, validação inline, retorno JSON manual e até integração externa diretamente em controllers e services.

O resultado é um backend funcional e rico em domínio, mas com custo crescente de manutenção, dificuldade de padronização e maior risco de regressão em áreas sensíveis como autenticação, autorização, billing e testes.

## Como a análise foi feita

Esta análise foi baseada em:

- leitura da estrutura do repositório
- inspeção de arquivos-chave de rotas, bootstrap, auth, tenancy, middleware, controllers, services, models, requests, resources e migrations
- execução de `php artisan test`
- verificação dos binários disponíveis em `vendor/bin`
- comparação entre o código real e as regras de `AGENTS.md`

## Snapshot do repositório

Panorama encontrado no momento da análise:

| Item | Quantidade |
|---|---:|
| Controllers | 54 |
| Services | 44 |
| Repositories | 6 |
| Models | 52 |
| FormRequests | 47 |
| Resources | 44 |
| Arquivos de teste | 36 |

Métricas relevantes do código:

- `43` controllers contêm acesso Eloquent direto
- `29` services contêm acesso Eloquent direto
- `24` controllers fazem validação inline com `validate(...)`
- `14` controllers retornam `response()->json(...)` manualmente
- `39` `FormRequest` usam `authorize(): bool { return true; }`

Esses números confirmam que o desenho dominante do backend não é mais um fluxo estrito `Controller -> Service -> Repository`.

## O que está bem implementado

### 1. Separação entre app central e app tenant

O projeto tem um desenho forte de separação entre aplicação central e tenant:

- rotas centrais em `routes/api.php`
- rotas tenant em `routes/tenant.php`
- bootstrap tenant via `App\Providers\TenancyServiceProvider`
- múltiplas conexões em `config/database.php`
- geração consistente de identificador/schema por tenant em `App\Models\Central\Tenant`

Isso é um bom fundamento para isolamento, billing e governança por plano.

### 2. Middleware e infraestrutura transversal

O `bootstrap/app.php` está bem organizado em termos de aliases e middleware transversais:

- `force.json`
- `tenant.logs`
- `api.logger`
- `enforce.limits`
- `subscription.active`
- `tenant.admin`
- `user.admin`
- `permission.gate`
- `check.feature`
- middlewares específicos para IA

Também há tratamento global de exceções com respostas JSON coerentes para APIs.

### 3. Estratégia de RBAC e feature gating

A base combina:

- `spatie/laravel-permission`
- `TenantPolicy`
- `PermissionGate`
- `check.feature:*`
- `enforce.limits:*`

O desenho é bom: a autorização depende de plano, módulo, recurso e nível de acesso. Isso é mais maduro do que simples checagem de role fixa em controller.

### 4. Base de testes existe e cobre partes importantes

Mesmo com problemas, a suíte atual cobre áreas importantes:

- planos e entitlements
- middleware de admin central
- serviços de billing
- viabilidade
- IA
- módulos
- APIs administrativas

Isso é um ativo importante para estabilização futura.

## Principais achados por domínio

## Rotas

### Pontos positivos

- a API está versionada em `/v1`
- há separação clara entre rotas públicas, autenticadas, admin e tenant
- rate limiters específicos foram definidos para login, reset de senha, approval e transfer ticket
- o desenho das rotas tenant mostra boa preocupação com feature gating por plano

### Achados

#### 1. Há padronização parcial, não total

As rotas centrais em `routes/api.php` e as tenant em `routes/tenant.php` usam uma combinação de:

- `apiResource`
- rotas customizadas por ação
- grupos com middleware
- rotas com verbos de negócio como `/aprovar`, `/reprovar`, `/recalcular`, `/marcar-pronto-registro`

Isso não é necessariamente errado, mas foge do padrão REST mais consistente que o `AGENTS.md` pede. O problema real não é a existência dessas rotas, e sim a falta de um padrão claro de quando usar ação customizada, quando usar policy dedicada e quando usar service.

#### 2. Route Model Binding é pouco aproveitado

A diretriz do projeto pede uso de Route Model Binding, mas a implementação real usa majoritariamente `{id}` e `find/findOrFail` nos controllers.

Exemplos:

- `App\Http\Controllers\Api\V1\Tenant\TerrenoController`
- `App\Http\Controllers\Api\V1\Admin\UserController`
- `App\Http\Controllers\Api\V1\Tenant\Admin\UserManagementController`
- `App\Http\Controllers\Api\V1\Tenant\CommitteeController`

Isso aumenta boilerplate e espalha regras de carregamento e erro 404 por vários controllers.

#### 3. Naming de rotas é inconsistente

O `AGENTS.md` pede que toda rota seja nomeada. Hoje:

- parte das rotas administrativas centrais tem nome
- boa parte das rotas tenant não tem `->name()`
- há grupos `apiResource` que geram nomes automaticamente, mas rotas customizadas tenant em geral não estão nomeadas

Impacto:

- dificulta referências internas consistentes
- reduz previsibilidade em testes e integrações
- torna a superfície da API menos governável

## Autenticação e sessão

## Arquitetura atual

O backend possui dois fluxos principais:

- autenticação central com broker e seleção de tenant
- autenticação tenant com login direto ou troca de transfer ticket

Arquivos-chave:

- `App\Http\Controllers\Api\V1\AuthController`
- `App\Services\Auth\CentralLoginBrokerService`
- `App\Services\Auth\TenantLoginService`
- `config/auth.php`
- `config/sanctum.php`

### Pontos positivos

- o fluxo central + tenant é sofisticado e compatível com SaaS multi-tenant
- há throttling específico para login e reset de senha
- o fluxo de ticket reduz fricção entre app central e tenant
- `EnsureUserIsAdmin` valida tanto identidade central quanto ability do token

### Achados

#### 1. `AuthController` concentra responsabilidades demais

`App\Http\Controllers\Api\V1\AuthController` acumula:

- resolução de tenant local
- inicialização manual de tenancy
- login central
- login tenant
- seleção de tenant
- exchange de ticket
- reset de senha
- refresh de token
- `me`
- atualização de perfil

Isso torna o controller grosso para um projeto que se propõe a ter controllers thin. Ele funciona como “orquestrador operacional”, mas também faz validação inline, manipulação de tenancy e decisões de fluxo que poderiam estar encapsuladas em actions/services menores.

#### 2. O projeto usa semânticas diferentes de “admin”

Há dois modelos de admin coexistindo:

- central: `is_admin` + token ability `admin` em `EnsureUserIsAdmin`
- tenant: role `ADMIN` ou `DIRECTOR` em `EnsureTenantAdmin`

Isso é compreensível por contexto, mas a nomenclatura semelhante esconde semânticas distintas. Hoje o projeto depende de o desenvolvedor saber quando “admin” significa:

- usuário central
- role tenant
- bypass completo de policy

Esse ponto merece explicitação formal no código e na documentação de auth.

#### 3. Parte da validação de auth continua inline

Mesmo com `LoginRequest`, várias ações de auth ainda validam com `$request->validate(...)`:

- `selectTenant`
- `exchangeTicket`
- `forgotPassword`
- `resetPassword`
- `updateMe`

Isso contraria a convenção do projeto para mutações de dados.

## Autorização, permissions e policies

### Pontos positivos

- `App\Policies\Tenant\TenantPolicy` centraliza a autorização tenant por modelo
- `App\Services\Acl\PermissionNameResolver` é uma boa abstração
- `PermissionGate` mapeia método HTTP para nível de permissão
- `App\Providers\AppServiceProvider` registra a mesma policy para vários modelos tenant

### Achados

#### 1. O projeto usa quatro mecanismos de autorização em paralelo

Hoje a autorização está espalhada entre:

- middleware (`tenant.admin`, `user.admin`, `permission.gate`, `check.feature`)
- policies (`Gate::authorize`, `$this->authorize`)
- `abort_unless(auth()->user()->isAdmin(), 403)`
- `authorize()` em `FormRequest`

Essa coexistência não é ruim por si só, mas o critério de uso não está padronizado. Isso aparece de forma clara em:

- `App\Http\Controllers\Api\V1\Tenant\TenantController`
- `App\Http\Controllers\Api\V1\Tenant\PlanSwapController`
- `App\Http\Controllers\Api\V1\Tenant\TerrenoController`
- `App\Http\Controllers\Api\V1\AuthController`

Consequência:

- autorização fica difícil de auditar
- é fácil duplicar regra ou esquecer um layer
- novos endpoints tendem a seguir o padrão “mais próximo”, não o padrão oficial

#### 2. `TenantPolicy` funciona, mas está muito implícita

`App\Policies\Tenant\TenantPolicy` devolve `false` em todos os métodos concretos e delega a lógica real ao método `before(...)`.

Isso é funcional, mas aumenta a opacidade do comportamento:

- quem lê `view`, `create`, `update`, `delete` vê `false`
- a autorização real depende de convenção em `PermissionNameResolver`
- debugging de authorization fica menos óbvio

Não é um bug, mas é um padrão de alta indireção. Para um time maior, isso aumenta curva de entendimento.

#### 3. `FormRequest::authorize()` está quase sempre vazio na prática

Esse é um dos principais desvios do projeto.

Foram encontrados `39` `FormRequest` com `return true;`, incluindo requests que mutam dados sensíveis:

- `App\Http\Requests\Tenant\StoreUserRequest`
- `App\Http\Requests\Tenant\StoreTerrenoRequest`
- `App\Http\Requests\Tenant\UpdateTerrenoRequest`
- `App\Http\Requests\Tenant\StoreLegalizacaoRequest`
- `App\Http\Requests\Tenant\ViabilidadeRequest`
- `App\Http\Requests\Tenant\PlanSwapRequest`
- vários requests administrativos tenant

Isso fere diretamente a regra do `AGENTS.md`:

> O método `authorize()` deve verificar permissões de verdade.

Observação importante:

- em requests públicos como login/signup, `return true` pode ser aceitável
- em requests autenticados que criam, alteram ou removem dados do tenant, esse padrão é fraco

#### 4. Há potencial de vazamento de detalhe de permissão

`App\Http\Middleware\PermissionGate` retorna em `details` a permissão exigida:

- `required_permission`

Isso ajuda debugging, mas também expõe convenções internas de ACL ao cliente. Eu classificaria isso como risco baixo, mas vale decidir conscientemente se esse detalhe deve existir em produção.

## Controllers, services e repositories

## Estado geral

Aqui está o principal desalinhamento estrutural do projeto.

O `AGENTS.md` define:

- controllers thin
- services sem query
- repositories como único lugar com Eloquent

A implementação atual não segue esse desenho.

### Evidências

- `54` controllers
- `44` services
- `6` repositories
- `43` controllers com Eloquent direto
- `29` services com Eloquent direto

Ou seja: os repositories não são a camada de persistência dominante do sistema. Eles estão restritos a poucos contextos, principalmente planos e entitlements.

### Exemplos concretos

#### 1. `TerrenoController` concentra responsabilidades demais

`App\Http\Controllers\Api\V1\Tenant\TerrenoController` faz:

- autorização
- query Eloquent
- cache
- paginação
- validação inline
- criação direta de model
- atualização direta de model
- resposta JSON manual em alguns métodos
- invocação de workflow

É um controller operacional, não thin.

#### 2. `Admin\UserController` ignora quase todo o padrão arquitetural

`App\Http\Controllers\Api\V1\Admin\UserController`:

- valida inline
- usa Eloquent direto
- usa `Hash::make` no controller
- retorna model cru em vez de resource
- não usa service nem repository

Esse controller é um dos exemplos mais claros de divergência entre guideline e prática.

#### 3. `BlogController` pula Service, Resource e padrão de resposta oficial

`App\Http\Controllers\Api\V1\BlogController`:

- usa Eloquent direto
- usa `response()->json(...)`
- não usa Resource
- não usa Service

Ele é simples, mas demonstra que a camada pública da API não está totalmente padronizada.

#### 4. `TenantController` carrega integração Stripe no controller

`App\Http\Controllers\Api\V1\Tenant\TenantController` consulta Stripe diretamente, monta payloads extensos e usa `abort_unless(...)` para autorização. Isso mistura:

- orchestration HTTP
- integração externa
- tratamento de erro
- serialização de dados de billing

Essa lógica deveria estar mais encapsulada em service próprio.

### Services também estão fazendo papel de repository

Services como:

- `App\Services\Tenant\LegalizacaoService`
- `App\Services\Tenant\ProjetoService`
- `App\Services\Tenant\NegotiationService`
- `App\Services\Tenant\Viabilidade\ViabilidadeService`
- `App\Services\Tenant\TenantUserService`
- `App\Services\Billing\TenantBillingService`

acessam Eloquent diretamente. Na prática, os services viraram a camada dominante de persistência e regra de negócio ao mesmo tempo.

Isso é aceitável em times pequenos, mas contradiz explicitamente a arquitetura escolhida para o projeto.

## Models, requests e resources

### Models

Os principais models auditados estão relativamente bem estruturados:

- `App\Models\Central\Tenant`
- `App\Models\Tenant\User`
- `App\Models\Tenant\Terreno`

Eles usam:

- `fillable`
- `casts`
- relações explícitas
- enums ou atributos derivados em alguns pontos

### Achados em models

#### 1. Há side effects operacionais em hooks de model

Exemplos:

- `App\Models\Tenant\User`
- `App\Models\Tenant\Terreno`

Esses models disparam limpeza de cache, sincronização de diretório e flush de dashboard em `saved/deleted/restored`.

Isso não é necessariamente errado, mas desloca efeitos colaterais importantes para uma camada implícita. Em sistemas com alto volume de escrita, isso dificulta previsibilidade e debugging.

#### 2. Há lógica de ciclo de vida no model central de tenant

`App\Models\Central\Tenant` possui métodos como:

- `activate()`
- `suspend()`
- `cancel()`
- `generateEncryptionKey()`

Isso é aceitável como comportamento de entidade, mas já mostra que parte da lógica de negócio não está confinada a services.

### Requests

#### 1. A cobertura de `FormRequest` existe, mas é incompleta

Há boa quantidade de `FormRequest`, porém:

- muitos endpoints ainda validam inline
- grande parte dos requests autenticados usa `authorize() => true`

#### 2. Há drift entre request e service

Exemplo claro:

- `App\Http\Requests\Tenant\StoreUserRequest`
- `App\Services\Tenant\TenantUserService::create`

O request aceita campos como:

- `roles`
- `status`
- `phone`
- `cpf`

Mas o service usa principalmente:

- `name`
- `email`
- `password`
- `locale`
- `department_id`
- `position_id`
- `role`

Isso indica desalinhamento entre contrato HTTP e implementação real. O risco aqui é API aceitar campos que não têm efeito, gerando comportamento enganoso para frontend e testes.

### Resources e contrato de resposta

Há muitos `Resources` no projeto, o que é positivo. Porém a regra “toda resposta deve passar por Resource” não é cumprida de forma universal.

Exemplos de desvios:

- `App\Http\Controllers\Api\V1\BlogController`
- `App\Http\Controllers\Api\V1\Admin\UserController`
- vários métodos em controllers tenant com `response()->json(...)`

Além disso, `ApiResponseService` padroniza `success/data/message`, enquanto o `AGENTS.md` pede o padrão Laravel clássico de:

- `data`
- `meta`
- `links`
- `message/errors`

Hoje existe um padrão próprio do projeto, não o padrão descrito na guideline.

#### Observação técnica sobre `ApiResponseService`

Há inconsistência na tradução de mensagens:

- `created()`, `validationError()`, `unauthorized()`, `forbidden()`, `notFound()`, `conflict()` e `tooManyRequests()` em vários casos passam mensagem já traduzida para métodos que traduzem novamente

Isso pode não quebrar em runtime se o tradutor devolver a string original, mas é um smell de design e pode produzir chaves inexistentes ou mensagens inconsistentes.

## Banco de dados, tenancy e migrations

### Pontos positivos

- `config/database.php` está preparado para central, tenant host e tenant template
- o uso de `search_path` para PostgreSQL está bem alinhado ao cenário multi-schema
- `TenancyServiceProvider` personaliza naming do schema/database do tenant
- o projeto separa migrations centrais e tenant

### Achados

#### 1. Existe migration irreversível

`database/migrations/tenant/2026_03_11_000002_backfill_workflow_and_versions.php`

Essa migration:

- faz backfill de status
- recalcula versões de viabilidade
- cria registros de projeto
- tem `down(): void {}` vazio

Isso viola diretamente a regra do projeto de migrations com rollback funcional.

#### 2. Há conflito real de migrations em ambiente de teste SQLite

A execução de `php artisan test` mostrou falhas repetidas por:

- tabela `personal_access_tokens` já existente

Arquivo envolvido:

- `database/migrations/tenant/0001_01_01_000003_create_personal_access_tokens_table.php`

Esse erro indica que o desenho de migrations central + tenant não está isolado corretamente no ambiente de testes SQLite em memória. Isso já deixou de ser dívida teórica; está quebrando testes hoje.

#### 3. Algumas migrations de backfill têm lógica de negócio significativa

Quando migrations fazem muito mais do que schema evolution, cresce o risco de:

- dificuldade de rollback
- reexecução não idempotente
- fragilidade em ambientes de teste
- acoplamento entre estado de dados e release

## Billing, webhooks e integrações externas

### Pontos positivos

- o projeto usa Cashier de forma consistente com o modelo `Tenant`
- há camada dedicada de billing em `App\Services\Billing\TenantBillingService`
- `App\Http\Controllers\Api\V1\WebhookController` tem proteção contra reprocessamento com lock e persistência em `WebhookEvent`

### Achados

#### 1. Parte importante do fluxo Stripe ainda depende de comportamento implícito do Cashier

A suíte falhou em:

- `Tests\Feature\Billing\WebhookHandlerTest`

com erro 500 em `past_due subscription`, vindo de:

- `vendor/laravel/cashier/src/Http/Controllers/WebhookController.php`

O payload esperado pelo Cashier não estava completo para o caso exercitado. Isso sugere que o controller customizado ainda está frágil diante de variações de payload de webhook.

#### 2. `TenantBillingService` também aparece instável em teste

Houve falha em:

- `Tests\Unit\Services\Billing\TenantBillingServiceTest`

ligada a acesso inesperado a atributo do model mockado. Isso sugere acoplamento maior do que o desejado entre o service e detalhes internos do model.

## Testes, qualidade e aderência ao guideline

## Resultado real de `php artisan test`

Resultado encontrado nesta análise:

- `182` testes passaram
- `47` falharam
- `16` ficaram `risky`

Principais categorias de falha:

- billing/webhooks
- módulos
- user management tenant
- IA com acesso externo
- mismatch de configuração em testes de IA
- conflito de migrations no SQLite

### Achados relevantes

#### 1. O projeto não está verde hoje

Isso precisa constar explicitamente. Não é uma base pronta para merge contínuo sem estabilização.

#### 2. Os testes de IA dependem de rede externa

Falhas observadas:

- `Tests\Feature\Tenant\AiChatStreamingTest`

com `ConnectionException` para `https://openrouter.ai/api/v1/chat/completions`.

Isso indica que parte da suíte não está isolada com `Http::fake()` ou adapter de provider. Como consequência:

- testes falham em ambiente offline
- CI fica não determinístico
- custo operacional e acoplamento ao provider aumentam

#### 3. A suíte usa majoritariamente estilo PHPUnit, não Pest

O `AGENTS.md` define Pest como padrão obrigatório. Porém:

- `vendor/bin` contém `phpunit`, mas não `pest`
- `phpunit.xml` define apenas suites `Unit` e `Feature`
- não existe pasta `tests/Architecture`
- os testes auditados usam estilo `class extends TestCase` com `$this->assert...`

Conclusão:

- a base atual está alinhada com PHPUnit
- a guideline está desalinhada com a realidade do projeto

#### 4. Não há testes de arquitetura dedicados

A pasta `tests/Architecture` não existe. Isso é especialmente relevante porque o projeto declara regras arquiteturais rígidas e hoje está fora delas em vários pontos.

#### 5. Existem warnings e testes ruidosos

Durante a execução apareceram sinais de baixa higiene na suíte:

- warning de metadata em doc-comment, já depreciada para PHPUnit 12
- testes `risky`
- saída excessiva de cenários de viabilidade

Isso aumenta ruído e reduz confiança do pipeline.

#### 6. PHPStan não está operacional no projeto atual

O `AGENTS.md` diz que PHPStan nível 8 é obrigatório. Porém:

- `vendor/bin` não contém `phpstan`
- não foi encontrado um fluxo ativo de execução de PHPStan na base auditada

Conclusão:

- o padrão desejado existe na guideline
- a ferramenta não está implantada de forma operacional neste repositório

## Aderência ao `AGENTS.md`

### Onde o projeto está alinhado

- uso de PHP 8.2+
- API versionada
- multi-tenancy bem estruturado
- uso de Sanctum
- uso de Policies e middleware de autorização
- presença de Resources e FormRequests em parte relevante da base
- existência de testes automatizados

### Onde o projeto diverge

- controllers não são thin em boa parte da base
- services fazem query Eloquent diretamente
- repositories não são a camada padrão de persistência
- muitas mutações ainda validam inline
- `authorize()` em `FormRequest` frequentemente não valida permissão real
- nem toda resposta de API usa `Resource`
- nem toda rota está nomeada
- Route Model Binding é pouco usado
- não há suíte de arquitetura
- Pest não é o runner dominante
- PHPStan nível 8 não está operacional
- há migration sem rollback funcional

## Achados priorizados

## Críticos

1. A arquitetura declarada e a arquitetura real divergem fortemente.
2. `39` `FormRequest` com `authorize() = true` reduzem a confiabilidade da camada de autorização.
3. A suíte falha hoje com `47` testes quebrados.
4. Há conflito real de migrations tenant/central em SQLite.
5. Existem testes de IA dependentes de rede externa.

## Altos

1. Controllers acumulam query, cache, validação, integração externa e regra de negócio.
2. Services absorveram o papel de repository, mas sem a disciplina arquitetural dessa camada.
3. O sistema usa múltiplos mecanismos de autorização sem critério único explícito.
4. O contrato de resposta da API não está uniformemente padronizado.
5. Billing/webhooks têm comportamento frágil em cenários parcialmente cobertos.

## Médios

1. `TenantPolicy` depende demais de indireção via `before()`.
2. Há drift entre requests e services em alguns endpoints.
3. Várias rotas customizadas não são nomeadas.
4. O projeto usa pouco Route Model Binding.
5. Alguns models concentram side effects operacionais em hooks.

## Baixos

1. Há inconsistência na tradução de mensagens em `ApiResponseService`.
2. Parte da nomenclatura e da guideline documental está desatualizada em relação ao código.
3. A suíte ainda gera warnings e output excessivo.

## Recomendações priorizadas

## Curto prazo

1. Corrigir a base de testes até ficar verde novamente.
2. Isolar testes de IA com `Http::fake()` ou adapter mockado.
3. Resolver o conflito de migrations tenant/central em SQLite.
4. Substituir `authorize() = true` por checagem real nos `FormRequest` autenticados mais críticos.
5. Padronizar endpoints que ainda validam inline em controllers.

## Médio prazo

1. Escolher uma regra oficial de autorização por tipo de endpoint:
   controller + policy, ou middleware + policy, mas com critério explícito.
2. Refatorar controllers mais espessos primeiro:
   - `TerrenoController`
   - `TenantController`
   - `AuthController`
   - `Admin\UserController`
   - `BlogController`
3. Criar repositories reais para domínios centrais do tenant:
   - terrenos
   - usuários tenant
   - viabilidades
   - legalizações
   - negociações
4. Uniformizar respostas com `Resource` + padrão único de envelope.
5. Introduzir Route Model Binding onde o ganho é mais direto.

## Longo prazo

1. Decidir se o projeto vai seguir de fato o padrão `Controller -> Service -> Repository`.
2. Se a resposta for sim, aplicar a arquitetura de forma sistemática.
3. Se a resposta for não, atualizar o `AGENTS.md` para refletir a arquitetura real e evitar guideline “fantasma”.
4. Implantar uma suíte de testes de arquitetura.
5. Tornar Pest e PHPStan operacionais de verdade, caso sigam sendo requisitos oficiais.

## Conclusão

O backend está acima da média em riqueza de domínio e infraestrutura para um produto SaaS Laravel: tenancy, billing, ACL, IA, módulos por plano e áreas de negócio bem definidas. O problema central não é falta de capacidade, e sim falta de convergência arquitetural.

Hoje existem bons blocos técnicos, mas eles convivem com padrões concorrentes. O próximo salto de maturidade do projeto não depende tanto de adicionar features, e sim de reduzir a distância entre a arquitetura desejada e a arquitetura realmente praticada no código.

Se eu tivesse que resumir em uma frase:

> a base é forte, mas precisa de consolidação estrutural urgente para continuar escalando com segurança.
