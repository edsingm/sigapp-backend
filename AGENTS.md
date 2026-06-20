# AGENTS.md — Backend Laravel 13+

Este arquivo contém as regras obrigatórias que todas as IAs (Cursor, Claude, Copilot, Gemini, etc.) devem seguir ao trabalhar neste projeto Laravel.

> **Nota sobre convenção vs. regra oficial:** o Laravel, por padrão, não impõe uma arquitetura em camadas (Controller → Service → Repository). A própria documentação oficial e os exemplos do framework usam o Eloquent diretamente em Controllers. As regras deste documento que vão além do que o Laravel exige por padrão (ex: Repository obrigatório, Service obrigatório) são **convenções deste projeto**, adotadas deliberadamente para testabilidade e desacoplamento — não confunda com "regra do Laravel". Isso é sinalizado explicitamente nas seções abaixo onde relevante.

---

## 🎯 Visão Geral do Projeto

| Item | Valor |
|---|---|
| **Framework** | Laravel 13+ |
| **Linguagem** | PHP 8.3+ |
| **Banco de dados** | MySQL 8+ / PostgreSQL 15+ (com `pgvector` para busca semântica) |
| **Autenticação** | Laravel Sanctum |
| **Testes** | PHPUnit 13 |
| **Formatação de código** | Laravel Pint (aplica PSR-12 automaticamente) |
| **Análise estática** | PHPStan nível 8 + Larastan (extensão obrigatória para entender Eloquent) |
| **Padrão de código** | PSR-12 + PSR-4 |
| **Arquitetura** | Controller → Service → Repository (convenção deste projeto — ver nota acima) |
| **AI SDK** | Laravel AI (`laravel/ai`) — provider-agnóstico, first-party, requer Laravel 13+ |

---

## 🚨 REGRAS OBRIGATÓRIAS

### 1. PHP e Padrões de Código

- PHP mínimo: **8.3** — use sempre os recursos modernos da linguagem
- Seguir **PSR-12** (estilo) e **PSR-4** (autoload) rigorosamente. *(PSR-2 foi formalmente descontinuado e substituído por PSR-12 desde 2019 — não usar PSR-2 como referência.)*
- A formatação é aplicada automaticamente via **Laravel Pint** (`./vendor/bin/pint`) — nunca formate manualmente nem discuta estilo em code review, o Pint é a fonte da verdade
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

> ⚠️ **Importante:** esta separação em 3 camadas é uma **convenção deste projeto**, não uma exigência do Laravel. O Laravel é deliberadamente desacoplado de uma arquitetura específica — o Eloquent já funciona como uma implementação de Active Record, e a documentação oficial usa Models diretamente em Controllers em boa parte dos exemplos. Adotamos a camada de Repository aqui para: (1) centralizar regras de consulta reutilizáveis, (2) facilitar mocks em testes unitários de Service sem tocar no banco, e (3) isolar o projeto de mudanças no Eloquent. Se um Controller/Service simples só precisa de uma query trivial, **não crie um Repository só para cumprir a regra** — isso seria abstração especulativa (ver Simplicidade). Use bom senso: Repository compensa quando a consulta é reutilizada em mais de um lugar ou quando precisa ser mockada em teste.

A separação de responsabilidades é **a regra deste projeto** para módulos não-triviais. Cada camada tem uma única função:

| Camada | Responsabilidade |
|---|---|
| **Controller** | Recebe a requisição HTTP, delega ao Service, retorna resposta |
| **Service** | Contém toda a lógica de negócio da aplicação |
| **Repository** | Abstrai o acesso ao banco de dados (Eloquent) |
| **Model** | Representa a entidade; define relações, casts e escopos |
| **FormRequest** | Valida e autoriza a entrada de dados |
| **Resource** | Formata a saída da API |

#### Regras de camada

- **Controllers devem ser thin**: nunca conter lógica de negócio, queries Eloquent diretas ou condicionais complexas — isto sim é convenção amplamente adotada e alinhada com o próprio Laravel (FormRequests, Resources e Route Model Binding existem exatamente para manter Controllers magros)
- **Services devem ser thin também**: orquestram chamadas a repositories e outros services — não contêm queries
- **Repositories são o único lugar** onde Eloquent é usado diretamente
- **Models concentram o acesso a dados e suas regras intrínsecas** (relações, casts, accessors/mutators, escopos locais). Lógica de *negócio* (regras que envolvem múltiplas entidades, eventos, notificações) vai para Services — mas isso não significa que o Model deve ser "anêmico": scopes, accessors e métodos que descrevem o próprio dado (ex: `$post->isPublished()`) pertencem ao Model, é assim que o Eloquent foi desenhado para ser usado

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
  Architecture/           → testes de arquitetura (ver seção 12)
```

> ⚠️ **Nunca crie pastas fora desta estrutura sem aprovação explícita.**

---

### 4. Eloquent e Banco de Dados

#### Models

- Sempre defina `$fillable` **explicitamente** — nunca use `$guarded = []` em produção
- Sempre defina `$casts` para tipos não-string (datas, booleans, enums, JSON)
- Use **Enums** nativos do PHP nos casts do Eloquent
- Lógica que envolve apenas o próprio dado (formatação, estado derivado, escopos de consulta) pertence ao Model; lógica que orquestra múltiplas entidades, side-effects (eventos, emails, filas) vai para Services
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

- **Framework**: PHPUnit 13 — o projeto usa PHPUnit diretamente, classes estendendo `TestCase`, não Pest PHP
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
    LayerBoundariesTest.php
```

#### Padrão Arrange-Act-Assert (PHPUnit nativo)

```php
// tests/Feature/Posts/CreatePostTest.php
final class CreatePostTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_um_post_autenticado_com_dados_validos(): void
    {
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

        $this->assertDatabaseHas('posts', [
            'title'   => 'Meu Post',
            'user_id' => $user->id,
        ]);
    }

    public function test_rejeita_criacao_de_post_sem_autenticacao(): void
    {
        $response = $this->postJson('/api/v1/posts', ['title' => 'Test']);

        $response->assertUnauthorized();
    }
}
```

#### Testes de arquitetura

O PHPUnit puro não tem um equivalente nativo ao plugin de arquitetura do Pest (`->expect()->toUse()`). Como este projeto usa PHPUnit, conformidade arquitetural é verificada de uma das duas formas — escolha uma e documente no `composer.json`:

**Opção A — teste PHPUnit com reflexão (sem dependência extra, recomendado para regras simples):**

```php
// tests/Architecture/LayerBoundariesTest.php
final class LayerBoundariesTest extends TestCase
{
    public function test_controllers_nao_usam_eloquent_diretamente(): void
    {
        foreach (glob(app_path('Http/Controllers/**/*.php')) as $file) {
            $contents = file_get_contents($file);
            $this->assertStringNotContainsString(
                'Illuminate\Database\Eloquent\Model',
                $contents,
                "Controller {$file} não deve referenciar Eloquent\\Model diretamente."
            );
        }
    }

    public function test_services_nao_dependem_de_request(): void
    {
        foreach (glob(app_path('Services/**/*.php')) as $file) {
            $contents = file_get_contents($file);
            $this->assertStringNotContainsString(
                'Illuminate\Http\Request',
                $contents,
                "Service {$file} não deve depender de Illuminate\\Http\\Request."
            );
        }
    }
}
```

**Opção B — [Deptrac](https://github.com/qossmic/deptrac) (recomendado se as regras de camada crescerem):** ferramenta dedicada e independente de framework de testes para checar dependências entre camadas, com `deptrac.yaml` declarando as camadas (`Controller`, `Service`, `Repository`, `Model`) e quais podem depender de quais. Rodada separadamente do `php artisan test`, no CI.

---

### 13. Performance e Cache

- Use `Cache::remember()` para dados que mudam raramente
- Use **tags de cache** para invalidar grupos de cache de forma precisa — **atenção:** tags de cache só são suportadas pelos drivers **Redis** e **Memcached**; os drivers `database`, `file` e `array` não suportam `Cache::tags()` e lançam exceção se usados. Confirme o driver configurado (`CACHE_STORE` no `.env`) antes de usar tags
- Configure `config:cache`, `route:cache` e `view:cache` no pipeline de deploy
- Use `select()` explícito — nunca `SELECT *` em queries que alimentam listagens
- Use `paginate()` em vez de `get()` para listagens públicas
- Use `with()` para eager loading — monitore N+1 com **Laravel Telescope** ou **Debugbar** em desenvolvimento

```php
// ✅ Cache com tags para invalidação precisa (apenas Redis/Memcached)
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
- **Larastan** (`composer require --dev larastan/larastan`) é obrigatório junto com o PHPStan — o PHPStan puro não entende a magia do Eloquent (relações, `$casts`, scopes, magic methods) e gera falsos positivos massivos sem essa extensão
- Rode `./vendor/bin/phpstan analyse` antes de todo commit
- Nunca use `// @phpstan-ignore-next-line` sem um comentário explicando o motivo

```neon
# phpstan.neon
includes:
    - vendor/larastan/larastan/extension.neon

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
8. **PHPStan nível 8 + Larastan** devem passar sem erros antes de qualquer merge
9. **Laravel Pint** deve passar sem alterações pendentes (`./vendor/bin/pint --test`) antes de qualquer merge
10. Cada **Job deve ter `failed()`** implementado
11. Toda funcionalidade nova deve ter **testes Feature** cobrindo o happy path e pelo menos um cenário de erro

---

## 📋 Checklist antes de cada PR

- [ ] PHPStan nível 8 + Larastan passam sem erros (`./vendor/bin/phpstan analyse`)
- [ ] Laravel Pint não acusa nenhuma alteração pendente (`./vendor/bin/pint --test`)
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
- [ ] Se usou `Cache::tags()`, confirmou que o driver configurado é Redis ou Memcached

---

**Última atualização:** Junho 2026 (revisado — PSR-12, Pint, Larastan, PHPUnit/Pest corrigido, limites de Cache::tags(), e nota de convenção vs. regra oficial do Laravel adicionada)