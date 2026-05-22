# AGENTS.md — Backend Laravel 13+

Este arquivo contém as regras obrigatórias que todas as IAs (Cursor, Claude, Copilot, Gemini, etc.) devem seguir ao trabalhar neste projeto Laravel.

---

## 🎯 Visão Geral do Projeto

| Item | Valor |
|---|---|
| **Framework** | Laravel 13+ |
| **Linguagem** | PHP 8.3+ |
| **Banco de dados** | MySQL 8+ / PostgreSQL 15+ (com `pgvector` para busca semântica) |
| **Autenticação** | Laravel Sanctum |
| **Testes** | PHPUnit 13 |
| **Análise estática** | PHPStan (nível 8) |
| **Padrão de código** | PSR-2 + PSR-4 |
| **Arquitetura** | Controller → Service → Repository |
| **AI SDK** | Laravel AI (`laravel/ai`) — provider-agnóstico (OpenRouter configurado) |

---

## 🚨 REGRAS OBRIGATÓRIAS

### 1. PHP e Padrões de Código

- PHP mínimo: **8.3** — use sempre os recursos modernos da linguagem
- Seguir **PSR-2** (estilo) e **PSR-4** (autoload) rigorosamente
- **Sempre declare tipos** em propriedades, parâmetros e retornos de método — nunca omita
- Use **enums** (PHP 8.1+) ao invés de constantes mágicas ou strings avulsas
- Use **readonly properties** e **constructor promotion** onde aplicável
- Nunca use `mixed` como tipo — seja preciso
- Nunca use `@suppress` ou `@phpstan-ignore` sem comentário explicativo

```php
// ❌ RUIM
function processOrder($order, $discount) {
    // ...
}

// ✅ BOM
function processOrder(Order $order, float $discount): OrderResult
{
    // ...
}
```

---

### 2. Arquitetura: Controller → Service → Repository

A separação de responsabilidades é **inegociável**. Cada camada tem uma única função:

| Camada | Responsabilidade |
|---|---|
| **Controller** | Recebe a requisição HTTP, delega ao Service, retorna resposta |
| **Service** | Contém toda a lógica de negócio da aplicação |
| **Repository** | Abstrai o acesso ao banco de dados (Eloquent) |
| **Model** | Representa a entidade; define relações, casts e escopos |
| **FormRequest** | Valida e autoriza a entrada de dados |
| **Resource** | Formata a saída da API |

#### Regras de camada

- **Controllers devem ser thin**: nunca conter lógica de negócio, queries Eloquent diretas ou condicionais complexas
- **Services devem ser thin também**: orquestram chamadas a repositories e outros services — não contêm queries
- **Repositories são o único lugar** onde Eloquent é usado diretamente
- **Models não devem conter lógica de negócio** — apenas relações, casts, accessors/mutators e escopos locais

```php
// ✅ Estrutura correta de um Controller
class PostController extends Controller
{
    public function __construct(
        private readonly PostService $postService
    ) {}

    public function store(StorePostRequest $request): PostResource
    {
        $post = $this->postService->create($request->validated());

        return new PostResource($post);
    }
}

// ✅ Estrutura correta de um Service
class PostService
{
    public function __construct(
        private readonly PostRepository $postRepository
    ) {}

    public function create(array $data): Post
    {
        // lógica de negócio aqui (notificações, eventos, etc.)
        $post = $this->postRepository->create($data);
        event(new PostCreated($post));

        return $post;
    }
}

// ✅ Estrutura correta de um Repository
class PostRepository
{
    public function create(array $data): Post
    {
        return Post::create($data);
    }

    public function findBySlug(string $slug): ?Post
    {
        return Post::where('slug', $slug)->first();
    }
}
```

---

### 3. Estrutura de Pastas

```
app/
  Http/
    Controllers/          → thin controllers por recurso
    Requests/             → FormRequests (validação + autorização)
    Resources/            → API Resources e Collections
    Middleware/           → middlewares customizados

  Services/               → lógica de negócio por domínio
  Repositories/           → acesso ao banco de dados
    Contracts/            → interfaces dos repositories

  Models/                 → Eloquent Models
  Enums/                  → PHP Enums tipados
  Events/                 → eventos do sistema
  Listeners/              → handlers de eventos
  Jobs/                   → jobs assíncronos
  Notifications/          → notificações (email, SMS, push)
  Exceptions/             → exceções customizadas tipadas
  Policies/               → autorização por recurso (Gates/Policies)
  DTOs/                   → Data Transfer Objects (opcional, mas recomendado)
  Providers/              → Service Providers (bind interfaces)

database/
  migrations/             → sempre com rollback implementado
  factories/              → factories para todos os models
  seeders/                → seeders separados por ambiente

routes/
  api.php                 → rotas da API (versionadas)
  web.php                 → rotas web (se aplicável)
  console.php             → comandos Artisan agendados

tests/
  Feature/                → testes de integração (HTTP, banco)
  Unit/                   → testes unitários (services, helpers)
  Architecture/           → testes de arquitetura com Pest
```

> ⚠️ **Nunca crie pastas fora desta estrutura sem aprovação explícita.**

---

### 4. Eloquent e Banco de Dados

#### Models

- Sempre defina `$fillable` **explicitamente** — nunca use `$guarded = []` em produção
- Sempre defina `$casts` para tipos não-string (datas, booleans, enums, JSON)
- Use **Enums** nativos do PHP nos casts do Eloquent
- Nunca coloque lógica de negócio em accessors/mutators — use Services
- Use `#[UseResource]` attribute (Laravel 12+) para vincular resources ao model quando conveniente
- Use **PHP Attributes** modernos do Laravel 13 para configuração declarativa de models (`#[Table]`, `#[Connection]`, `#[Scope]`, etc.) quando aplicável

```php
// ✅ Model bem definido
class Post extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'status',
        'published_at',
    ];

    protected $casts = [
        'status'       => PostStatus::class, // PHP Enum
        'published_at' => 'immutable_datetime',
        'metadata'     => 'array',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PostStatus::Published);
    }
}
```

#### Migrations

- Toda migration **deve ter um método `down()` funcional**
- Nunca altere uma migration já executada em produção — crie uma nova
- Sempre adicione índices em colunas usadas em `WHERE`, `ORDER BY` e foreign keys
- Use `foreignIdFor()` ao invés de `unsignedBigInteger()` + `foreign()` manual

```php
// ✅ BOM
$table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();

// ❌ RUIM
$table->unsignedBigInteger('user_id');
$table->foreign('user_id')->references('id')->on('users');
```

#### Queries

- **Nunca use `all()` ou `get()` sem condições** em tabelas grandes — sempre pagine ou limite
- Use `paginate()` ao invés de `get()` para listagens na API
- **Sempre carregue relações com `with()`** — nunca deixe N+1 queries (o Laravel 12.8+ tem eager loading automático, mas não dependa disso)
- Prefira `select()` explícito ao invés de `SELECT *` em queries pesadas
- Use `chunk()` ou `lazy()` para processar grandes volumes de dados em jobs

```php
// ❌ RUIM — N+1 e sem paginação
$posts = Post::all();
foreach ($posts as $post) {
    echo $post->author->name;
}

// ✅ BOM
$posts = Post::with('author')
    ->published()
    ->select(['id', 'title', 'slug', 'user_id', 'published_at'])
    ->paginate(20);
```

---

### 5. FormRequests — Validação e Autorização

- **Toda requisição que muta dados deve usar um FormRequest** — nunca valide no Controller
- O método `authorize()` deve verificar permissões **de verdade** — nunca apenas retorne `true`
- Use `$request->validated()` para pegar os dados — nunca `$request->all()` ou `$request->input()`
- A autorização no `authorize()` garante HTTP 403 antes da validação — importante para respostas corretas

```php
// ✅ FormRequest correto
class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Post::class);
    }

    public function rules(): array
    {
        return [
            'title'   => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'status'  => ['required', Rule::enum(PostStatus::class)],
        ];
    }
}
```

---

### 6. API Resources

- **Toda resposta de API deve passar por um Resource** — nunca retorne Models ou arrays brutos
- Use `ResourceCollection` para listagens paginadas
- Nunca exponha campos sensíveis (senhas, tokens, dados internos) no Resource
- Padronize sempre o formato de resposta

```php
// ✅ Resource correto
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'title'        => $this->title,
            'slug'         => $this->slug,
            'status'       => $this->status,
            'author'       => new UserResource($this->whenLoaded('author')),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at'   => $this->created_at->toIso8601String(),
        ];
    }
}
```

#### Padrão de resposta para erros

Crie um trait ou use o `Handler.php` para padronizar respostas de erro:

```php
// responses/error → sempre este formato
{
    "message": "The given data was invalid.",
    "errors": {
        "email": ["The email field is required."]
    }
}

// responses/sucesso → sempre este formato
{
    "data": { ... }        // resource único
}
{
    "data": [ ... ],       // collection
    "meta": { ... },       // paginação
    "links": { ... }
}
```

---

### 7. Autenticação e Autorização

- Use **Laravel Sanctum** para APIs (SPAs e mobile)
- Use **Policies** como única fonte de verdade para toda lógica de autorização por recurso — nunca coloque `if ($user->role === 'admin')` em Controllers ou Services
- A verificação de autorização deve acontecer **antes** do Service ser chamado, em uma das camadas HTTP:
  - **Rota**: `->middleware('can:update,post')` ou `#[Middleware('can:update,post')]`
- **Services nunca tratam autorização** — eles operam sobre dados já autorizados
- Registre todas as Policies no `AuthServiceProvider` (ou via `#[Policy]` attribute)
- Use **Gates** para ações não ligadas a um model específico
- **Nunca confie nos dados do cliente** para determinar permissões — sempre verifique no servidor

```php
// ✅ Autorização via rota (middleware can)
Route::put('/posts/{post}', [PostController::class, 'update'])
    ->middleware('can:update,post');

```

---

### 8. Tratamento de Erros e Exceções

- Crie **exceções customizadas** para erros de domínio — nunca lance `\Exception` genérica
- Registre os handlers no `bootstrap/app.php` (Laravel 12+) ou `app/Exceptions/Handler.php`
- Toda exceção de domínio deve retornar um HTTP status code semântico correto
- Use `report()` para logar erros sem interromper o fluxo
- Nunca exponha stack traces ou detalhes técnicos em respostas de produção (`APP_DEBUG=false`)

```php
// ✅ Exceção customizada
class PostNotFoundException extends RuntimeException
{
    public function __construct(int $id)
    {
        parent::__construct("Post #{$id} não encontrado.");
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 404);
    }
}

// ✅ Registro no Handler (Laravel 13)
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (PostNotFoundException $e) {
        return response()->json(['message' => $e->getMessage()], 404);
    });
})
```

---

### 9. Segurança

- **`APP_DEBUG=false`** obrigatório em produção
- Nunca commit de `.env` — use `.env.example` com todas as variáveis listadas (sem valores)
- Use **`$fillable`** em todos os models — nunca `$guarded = []`
- Sempre use **rate limiting** em rotas de autenticação e endpoints públicos
- Use **`$request->validated()`** — nunca `$request->all()`
- Sempre sanitize uploads de arquivo (tipo MIME, tamanho, extensão)
- Use HTTPS forçado em produção via `AppServiceProvider`
- Rode `composer audit` regularmente para verificar vulnerabilidades nas dependências

```php
// ✅ Rate limiting nas rotas
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // rotas autenticadas
});

// Rotas sensíveis com limite menor
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
});
```

---

### 10. Rotas da API

- **Versione sempre** a API: `/api/v1/`, `/api/v2/`
- Use **Route Model Binding** ao invés de buscar manualmente no controller
- Agrupe rotas por middleware, prefixo e namespace
- Prefira **Resource Controllers** para operações CRUD padrão
- Nomeie todas as rotas com `->name()`
- Nunca use verbos nos nomes das rotas — use substantivos (RESTful)

```php
// ✅ routes/api.php bem organizado
Route::prefix('v1')->name('api.v1.')->group(function () {

    // Rotas públicas
    Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/auth/register', [AuthController::class, 'register'])->name('auth.register');

    // Rotas autenticadas
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('posts', PostController::class);
        Route::apiResource('comments', CommentController::class)->only(['store', 'destroy']);
    });
});
```

---

### 11. Jobs, Queues e Events

- Toda operação demorada (emails, notificações, integrações externas) deve ser assíncrona via **Jobs**
- Use `Bus::batch()` para processar grupos de jobs com rollback automático em falhas
- Sempre implemente `failed()` nos Jobs para tratar falhas de forma controlada
- Use **Events + Listeners** para desacoplar efeitos colaterais da lógica de negócio principal
- Defina `$tries`, `$timeout` e `$backoff` em todos os Jobs

```php
// ✅ Job bem definido
class SendWelcomeEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;
    public array $backoff = [10, 30, 60];

    public function __construct(
        private readonly User $user
    ) {}

    public function handle(MailService $mailService): void
    {
        $mailService->sendWelcome($this->user);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Falha ao enviar email de boas-vindas', [
            'user_id' => $this->user->id,
            'error'   => $exception->getMessage(),
        ]);
    }
}
```

---

### 12. Testes

- **Framework**: PHPUnit 13 — o projeto usa PHPUnit diretamente, não Pest PHP
- Toda funcionalidade nova **deve ter testes** antes de ser considerada concluída
- Use `RefreshDatabase` em testes que interagem com o banco
- Use `actingAs()` para testar rotas autenticadas
- Nunca teste implementações internas — teste comportamentos e respostas HTTP
- Mock serviços externos (`Http::fake()`, `Mail::fake()`, `Queue::fake()`, `Event::fake()`)

#### Estrutura de testes

```
tests/
  Feature/
    Auth/
      LoginTest.php
      RegisterTest.php
    Posts/
      CreatePostTest.php
      UpdatePostTest.php
      DeletePostTest.php
  Unit/
    Services/
      PostServiceTest.php
    Repositories/
      PostRepositoryTest.php
  Architecture/
    ArchTest.php
```

#### Testes de arquitetura com PHPUnit

```php
// tests/Architecture/ArchTest.php
test('controllers não devem usar Eloquent diretamente')
    ->expect('App\Http\Controllers')
    ->not->toUse(['Illuminate\Database\Eloquent\Model']);

test('models devem existir apenas na pasta Models')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model');

test('services não devem depender de Request')
    ->expect('App\Services')
    ->not->toUse('Illuminate\Http\Request');

test('repositories não devem conter lógica de negócio')
    ->expect('App\Repositories')
    ->not->toUse('App\Services');
```

#### Padrão Arrange-Act-Assert

```php
// ✅ Feature test bem escrito
it('cria um post autenticado com dados válidos', function () {
    // Arrange
    $user = User::factory()->create();
    $payload = [
        'title'   => 'Meu Post',
        'content' => 'Conteúdo do post',
        'status'  => PostStatus::Draft->value,
    ];

    // Act
    $response = $this
        ->actingAs($user)
        ->postJson('/api/v1/posts', $payload);

    // Assert
    $response->assertCreated()
             ->assertJsonStructure(['data' => ['id', 'title', 'slug', 'status']]);

    $this->assertDatabaseHas('posts', ['title' => 'Meu Post', 'user_id' => $user->id]);
});

it('rejeita criação de post sem autenticação', function () {
    $response = $this->postJson('/api/v1/posts', ['title' => 'Test']);

    $response->assertUnauthorized();
});
```

---

### 13. Performance e Cache

- Use `Cache::remember()` para dados que mudam raramente
- Use **tags de cache** para invalidar grupos de cache de forma precisa
- Configure `config:cache`, `route:cache` e `view:cache` no pipeline de deploy
- Use `select()` explícito — nunca `SELECT *` em queries que alimentam listagens
- Use `paginate()` em vez de `get()` para listagens públicas
- Use `with()` para eager loading — monitore N+1 com **Laravel Telescope** ou **Debugbar** em desenvolvimento

```php
// ✅ Cache com tags para invalidação precisa
public function getPublishedPosts(): Collection
{
    return Cache::tags(['posts', 'published'])
        ->remember('posts.published', now()->addHour(), fn () =>
            Post::with('author')->published()->paginate(20)
        );
}

// Invalida apenas o cache de posts publicados
Cache::tags(['posts', 'published'])->flush();
```

---

### 14. Análise Estática

- **PHPStan no nível 8** é obrigatório — o CI deve falhar se houver erros
- Rode `./vendor/bin/phpstan analyse` antes de todo commit
- Nunca use `// @phpstan-ignore-next-line` sem um comentário explicando o motivo

```bash
# phpstan.neon
parameters:
    level: 8
    paths:
        - app
        - tests
    ignoreErrors:
        # Exemplo de ignore documentado
        - '#Call to an undefined method Illuminate\\Database\\Eloquent\\Builder#'
```

---

### 15. Convenções de Nomenclatura

| Tipo | Convenção | Exemplo |
|---|---|---|
| Controller | PascalCase + sufixo | `PostController.php` |
| Service | PascalCase + sufixo | `PostService.php` |
| Repository | PascalCase + sufixo | `PostRepository.php` |
| FormRequest | PascalCase + verbo+recurso | `StorePostRequest.php`, `UpdatePostRequest.php` |
| Resource | PascalCase + sufixo | `PostResource.php`, `PostCollection.php` |
| Model | PascalCase singular | `Post.php`, `UserProfile.php` |
| Migration | snake_case com timestamp | `2025_01_01_000000_create_posts_table.php` |
| Enum | PascalCase | `PostStatus.php` |
| Event | PascalCase + ação passada | `PostCreated.php`, `UserRegistered.php` |
| Job | PascalCase + ação | `SendWelcomeEmail.php`, `ProcessPayment.php` |
| Exception | PascalCase + sufixo | `PostNotFoundException.php` |
| Teste Feature | PascalCase + Test | `CreatePostTest.php` |
| Teste Unit | PascalCase + Test | `PostServiceTest.php` |
| Rota API | kebab-case plural | `/api/v1/blog-posts` |
| Método de rota | camelCase | `store`, `update`, `showBySlug` |

---

### 16. Artisan e Comandos

- Use **Artisan Commands** para tarefas recorrentes de manutenção — nunca scripts PHP avulsos
- Defina `$signature` e `$description` em todo Command (ou use o atributo `#[AsCommand]` do Laravel 13)
- Agende commands no `routes/console.php` (Laravel 11+/12+) — nunca no `Kernel.php`

```php
// routes/console.php
Schedule::command('posts:publish-scheduled')->everyMinute();
Schedule::command('backup:run')->dailyAt('02:00');
```

---

## 🔥 Regras de Prioridade Alta

1. **Nunca instale pacotes nem mude a estrutura de pastas sem listar o que faria e aguardar aprovação explícita**
2. Prefira sempre **recursos nativos do Laravel** antes de adicionar bibliotecas externas
3. O projeto usa **Laravel AI SDK** (`laravel/ai`) como camada de IA — nunca integre SDKs de providers diretamente; use sempre o Laravel AI como abstração
4. **Controllers são thin** — qualquer lógica além de receber, delegar e responder vai para o Service
5. **Toda mutação de dados passa por FormRequest** (validação + autorização) antes de chegar ao Controller
6. **Toda resposta de API passa por um Resource** — nunca retorne Models brutos
7. `APP_DEBUG=false` e **nunca** commite `.env` — use `.env.example`
8. **PHPStan nível 8** deve passar sem erros antes de qualquer merge
9. Cada **Job deve ter `failed()`** implementado
10. Toda funcionalidade nova deve ter **testes Feature** cobrindo o happy path e pelo menos um cenário de erro

---

## 📋 Checklist antes de cada PR

- [ ] PHPStan nível 8 passa sem erros (`./vendor/bin/phpstan analyse`)
- [ ] Todos os testes passam (`php artisan test`)
- [ ] Testes de arquitetura passam (sem Eloquent em Controllers, sem Request em Services)
- [ ] Toda nova rota está versionada (`/api/v1/...`) e nomeada
- [ ] Toda mutation usa `FormRequest` com `authorize()` real
- [ ] Toda resposta de API usa `Resource` ou `ResourceCollection`
- [ ] Nenhum `$request->all()` no código
- [ ] Nenhum `Model::all()` sem limite/paginação
- [ ] Relações Eloquent carregadas com `with()` (sem N+1)
- [ ] Novos Jobs têm `$tries`, `$timeout` e `failed()` definidos
- [ ] `.env.example` atualizado com as novas variáveis (sem valores sensíveis)
- [ ] Migrations têm `down()` funcional

---

**Última atualização:** Maio 2026