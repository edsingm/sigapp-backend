<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Tenant\Viabilidade\ViabilidadeUnificadoService;
use App\Services\Acl\PermissionNameResolver;
use App\Services\Tenant\MobilePushService;
use App\Services\Tenant\Viabilidade\ViabilidadeService;
use App\Models\Tenant\Viabilidade;
use App\Http\Requests\Tenant\ViabilidadeRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPdf\Facades\Pdf;
use Exception;
use App\Services\Tenant\LandWorkflowService;

class ViabilidadeController extends Controller
{
    protected ViabilidadeService $viabilidadeService;

    public function __construct(
        ViabilidadeService $viabilidadeService,
        protected MobilePushService $mobilePushService,
        protected LandWorkflowService $workflowService,
        protected PermissionNameResolver $permissions,
    )
    {
        $this->viabilidadeService = $viabilidadeService;
    }

    /**
     * Ativar uma viabilidade (mudar status de rascunho para ativo)
     *
     * @param int $id
     * @return JsonResponse
     */
    public function ativar(int $id): JsonResponse
    {
        try {
            $user = request()->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não autenticado.'
                ], 401);
            }

            // $canActivate = $user->hasRole('admin')
            //     || ($user->hasAnyRole(['diretor', 'gestor', 'coordenador']) && $user->isDepartamentoNovosNegocios());

            // if (!$canActivate) {
            //     Log::warning('Acesso negado no ViabilidadeController::ativar', [
            //         'viabilidade_id' => $id,
            //         'user_id' => $user->id,
            //     ]);

            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Acesso não autorizado. Apenas diretor, gestor ou coordenador do departamento de Novos Negócios podem ativar viabilidades.'
            //     ], 403);
            // }

            $viabilidade = Viabilidade::findOrFail($id);
            Gate::authorize('ativar', $viabilidade);
            $viabilidade->update(['status' => 'ativo']);

            return response()->json([
                'success' => true,
                'message' => 'Viabilidade salva com sucesso',
                'data' => $viabilidade->load(['terreno', 'createdBy', 'updatedBy'])
            ]);

        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro no ViabilidadeController::ativar', [
                'viabilidade_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao salvar viabilidade'
            ], 500);
        }
    }

    public function solicitarAprovacao(Request $request, int $id): JsonResponse
    {
        try {
            $viabilidade = Viabilidade::findOrFail($id);
            Gate::authorize('requestApproval', $viabilidade);

            $validated = $request->validate([
                'approval_notes' => ['nullable', 'string', 'max:5000'],
            ]);

            $viabilidade->update([
                'approval_status' => 'em_aprovacao',
                'approval_requested_at' => now(),
                'submitted_at' => now(),
                'approval_decided_at' => null,
                'approval_decided_by' => null,
                'approval_notes' => $validated['approval_notes'] ?? null,
                'updated_by' => $request->user()?->id,
            ]);
            $this->workflowService->transition(
                $viabilidade->terreno()->firstOrFail(),
                'aguardando_viabilidade',
                $request->user(),
                'viability_submitted',
                $validated['approval_notes'] ?? null,
            );

            $viabilidade->loadMissing('terreno');

            $this->mobilePushService->notifyUsersWithPermission(
                (string) $this->permissions->forModel(Viabilidade::class, 'approve'),
                [
                    'title' => 'Viabilidade aguardando aprovação',
                    'body' => "A viabilidade do terreno {$viabilidade->terreno?->nome} aguarda decisão.",
                    'type' => 'viabilidade.solicitar_aprovacao',
                    'entity_type' => 'viabilidade',
                    'entity_id' => (string) $viabilidade->id,
                    'target_route' => "/terrenos/{$viabilidade->terreno_id}",
                    'payload' => [
                        'tenant_slug' => tenant('slug'),
                        'viabilidade_id' => $viabilidade->id,
                        'terreno_id' => $viabilidade->terreno_id,
                    ],
                ],
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Aprovação da viabilidade solicitada com sucesso',
                'data' => new \App\Http\Resources\Tenant\ViabilidadeResource(
                    $viabilidade->fresh(['terreno', 'createdBy', 'approvalDecidedBy'])
                ),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Viabilidade não encontrada',
            ], 404);
        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro no ViabilidadeController::solicitarAprovacao', [
                'viabilidade_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao solicitar aprovação da viabilidade',
            ], 500);
        }
    }

    public function aprovar(Request $request, int $id): JsonResponse
    {
        return $this->decidirAprovacao($request, $id, 'aprovada');
    }

    public function reprovar(Request $request, int $id): JsonResponse
    {
        return $this->decidirAprovacao($request, $id, 'reprovada');
    }

    /**
     * Listar todas as viabilidades
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            Gate::authorize('viewAny', Viabilidade::class);

            $tenantId = tenant('id') ?? 'central';
            $filtros = $request->only(['search', 'terreno_id', 'per_page', 'page']);
            $cacheKey = "tenant:{$tenantId}:viabilidades:index:" . md5(json_encode($filtros));

            $viabilidades = Cache::tags(["tenant:{$tenantId}:viabilidades"])->remember($cacheKey, now()->addMinutes(30), function () use ($filtros) {
                return $this->viabilidadeService->listarTodasViabilidades($filtros);
            });

            return response()->json([
                'success' => true,
                'data' => $viabilidades
            ]);

        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro no ViabilidadeController::index', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao listar viabilidades'
            ], 500);
        }
    }

    /**
     * Criar nova viabilidade e gerar DRE
     *
     * @param ViabilidadeRequest $request
     * @return JsonResponse
     */
    public function store(ViabilidadeRequest $request): JsonResponse
    {
        try {
            Gate::authorize('create', Viabilidade::class);

            // Dados já validados pelo ViabilidadeRequest
            $dados = $request->validated();

            // Criar viabilidade com DRE
            $resultado = $this->viabilidadeService->criarViabilidadeComDre($dados);
            Log::info('Viabilidade criada com sucesso', $resultado);
            return response()->json([
                'success' => true,
                'message' => 'Viabilidade criada com sucesso',
                'data' => $resultado
            ], 201);

        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro no ViabilidadeController::store', [
                'request_data' => $request->validated(),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Buscar viabilidade com DRE por ID
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $viabilidade = Viabilidade::findOrFail($id);
            Gate::authorize('view', $viabilidade);

            $resultado = $this->viabilidadeService->buscarViabilidadeComDre($id);

            return response()->json([
                'success' => true,
                'data' => $resultado
            ]);

        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro no ViabilidadeController::show', [
                'viabilidade_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Viabilidade não encontrada'
            ], 404);
        }
    }

    /**
     * Atualizar viabilidade e recalcular DRE
     *
     * @param ViabilidadeRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(ViabilidadeRequest $request, int $id): JsonResponse
    {
        try {
            $viabilidade = Viabilidade::findOrFail($id);
            Gate::authorize('update', $viabilidade);

            // Dados já validados pelo ViabilidadeRequest
            $dados = $request->validated();

            // Atualizar viabilidade com DRE
            $resultado = $this->viabilidadeService->atualizarViabilidadeComDre($viabilidade, $dados);

            return response()->json([
                'success' => true,
                'message' => 'Viabilidade atualizada com sucesso',
                'data' => $resultado
            ]);

        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro no ViabilidadeController::update', [
                'viabilidade_id' => $id,
                'request_data' => $request->validated(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    protected function decidirAprovacao(Request $request, int $id, string $decision): JsonResponse
    {
        try {
            $viabilidade = Viabilidade::findOrFail($id);
            Gate::authorize('approve', $viabilidade);

            $validated = $request->validate([
                'approval_notes' => ['nullable', 'string', 'max:5000'],
            ]);

            $approvalStatus = $viabilidade->approval_status ?? ($viabilidade->status === 'ativo' ? 'aprovada' : 'pendente');

            if ($approvalStatus !== 'em_aprovacao') {
                return response()->json([
                    'success' => false,
                    'message' => 'A viabilidade precisa estar em aprovação antes desta decisão.',
                ], 422);
            }

            $payload = [
                'approval_status' => $decision,
                'approval_decided_at' => now(),
                'approval_decided_by' => $request->user()?->id,
                'approval_notes' => $validated['approval_notes'] ?? $viabilidade->approval_notes,
                'updated_by' => $request->user()?->id,
            ];

            if ($decision === 'aprovada') {
                $payload['status'] = 'ativo';
                $payload['locked_at'] = now();
            } else {
                $payload['status'] = 'rascunho';
                $payload['locked_at'] = null;
            }

            $viabilidade->update($payload);
            $this->viabilidadeService->registrarAprovacao($viabilidade, $decision, $validated['approval_notes'] ?? null);
            $this->workflowService->transition(
                $viabilidade->terreno()->firstOrFail(),
                $decision === 'aprovada' ? 'viabilidade_aprovada' : 'em_analise',
                $request->user(),
                'viability_decided',
                $validated['approval_notes'] ?? null,
            );
            $viabilidade->loadMissing('terreno');

            $this->mobilePushService->notifyAllUsers(
                [
                    'title' => $decision === 'aprovada'
                        ? 'Viabilidade aprovada'
                        : 'Viabilidade reprovada',
                    'body' => $decision === 'aprovada'
                        ? "A viabilidade do terreno {$viabilidade->terreno?->nome} foi aprovada."
                        : "A viabilidade do terreno {$viabilidade->terreno?->nome} foi reprovada.",
                    'type' => $decision === 'aprovada'
                        ? 'viabilidade.aprovada'
                        : 'viabilidade.reprovada',
                    'entity_type' => 'viabilidade',
                    'entity_id' => (string) $viabilidade->id,
                    'target_route' => "/terrenos/{$viabilidade->terreno_id}",
                    'payload' => [
                        'tenant_slug' => tenant('slug'),
                        'viabilidade_id' => $viabilidade->id,
                        'terreno_id' => $viabilidade->terreno_id,
                    ],
                ],
                $request->user()
            );

            $message = $decision === 'aprovada'
                ? 'Viabilidade aprovada com sucesso'
                : 'Viabilidade reprovada com sucesso';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => new \App\Http\Resources\Tenant\ViabilidadeResource(
                    $viabilidade->fresh(['terreno', 'createdBy', 'approvalDecidedBy'])
                ),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Viabilidade não encontrada',
            ], 404);
        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro no ViabilidadeController::decidirAprovacao', [
                'viabilidade_id' => $id,
                'decision' => $decision,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao registrar a decisão da aprovação',
            ], 500);
        }
    }

    /**
     * Listar viabilidades de uma área
     *
     * @param int $areaId
     * @return JsonResponse
     */
    public function byTerreno(int $terrenoId): JsonResponse
    {
        try {
            Gate::authorize('viewAny', Viabilidade::class);

            $viabilidades = $this->viabilidadeService->listarViabilidadesPorTerreno($terrenoId);

            return response()->json([
                'success' => true,
                'data' => $viabilidades
            ]);

        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro no ViabilidadeController::byTerreno', [
                'terreno_id' => $terrenoId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar viabilidades da área'
            ], 500);
        }
    }

    /**
     * Buscar viabilidade mais recente de um terreno
     *
     * @param int $terrenoId
     * @return JsonResponse
     */
    public function latest(int $terrenoId): JsonResponse
    {
        try {
            Gate::authorize('viewAny', Viabilidade::class);

            $viabilidade = $this->viabilidadeService->buscarViabilidadeAtual($terrenoId);

            if (!$viabilidade) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nenhuma viabilidade encontrada para este terreno'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $viabilidade
            ]);

        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro no ViabilidadeController::latest', [
                'terreno_id' => $terrenoId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar viabilidade mais recente'
            ], 500);
        }
    }

    /**
     * Duplicar viabilidade
     *
     * @param int $id
     * @return JsonResponse
     */
    public function duplicate(int $id): JsonResponse
    {
        try {
            $viabilidade = Viabilidade::findOrFail($id);
            Gate::authorize('duplicate', $viabilidade);

            $novaViabilidade = $this->viabilidadeService->duplicarViabilidade($id);

            return response()->json([
                'success' => true,
                'message' => 'Viabilidade duplicada com sucesso',
                'data' => $novaViabilidade->load(['terreno', 'createdBy', 'updatedBy'])
            ], 201);

        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro no ViabilidadeController::duplicate', [
                'viabilidade_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Excluir viabilidade
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $viabilidade = Viabilidade::findOrFail($id);
            Gate::authorize('delete', $viabilidade);

            $resultado = $this->viabilidadeService->excluirViabilidade($id);

            if ($resultado) {
                return response()->json([
                    'success' => true,
                    'message' => 'Viabilidade excluída com sucesso'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Erro ao excluir viabilidade'
            ], 500);

        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro no ViabilidadeController::destroy', [
                'viabilidade_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Comparar duas viabilidades
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function compare(Request $request): JsonResponse
    {
        try {
            Gate::authorize('compare', Viabilidade::class);

            $viabilidade1Id = $request->input('viabilidade_1_id');
            $viabilidade2Id = $request->input('viabilidade_2_id');

            if (!$viabilidade1Id || !$viabilidade2Id) {
                return response()->json([
                    'success' => false,
                    'message' => 'IDs das duas viabilidades são obrigatórios'
                ], 422);
            }

            $comparacao = $this->viabilidadeService->compararViabilidades($viabilidade1Id, $viabilidade2Id);

            return response()->json([
                'success' => true,
                'data' => $comparacao
            ]);

        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro no ViabilidadeController::compare', [
                'request_data' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Gerar DRE para uma viabilidade específica
     *
     * @param int $id
     * @return JsonResponse
     */
    public function gerarDre(int $id): JsonResponse
    {
        try {
            $viabilidade = Viabilidade::findOrFail($id);
            Gate::authorize('gerarDre', $viabilidade);

            $resultado = $this->viabilidadeService->buscarViabilidadeComDre($id);

            return response()->json([
                'success' => true,
                'data' => $resultado['dre_resultados']
            ]);

        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro no ViabilidadeController::gerarDre', [
                'viabilidade_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao gerar DRE: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recalcular DRE de uma viabilidade existente
     *
     * @param int $id
     * @return JsonResponse
     */
    public function recalcular(int $id): JsonResponse
    {
        try {
            $viabilidade = Viabilidade::findOrFail($id);
            Gate::authorize('recalcular', $viabilidade);

            // Recalcular DRE com os dados atuais da viabilidade
            $resultado = $this->viabilidadeService->recalcularDre($viabilidade);

            return response()->json([
                'success' => true,
                'message' => 'Viabilidade recalculada com sucesso',
                'data' => $resultado
            ]);

        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro no ViabilidadeController::recalcular', [
                'viabilidade_id' => $id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao recalcular viabilidade: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Restaurar viabilidade excluída
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        try {
            $viabilidade = Viabilidade::withTrashed()->findOrFail($id);
            Gate::authorize('restore', $viabilidade);
            $viabilidade->restore();

            return response()->json([
                'success' => true,
                'message' => 'Viabilidade restaurada com sucesso',
                'data' => $viabilidade->load(['terreno', 'createdBy', 'updatedBy'])
            ]);

        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro no ViabilidadeController::restore', [
                'viabilidade_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao restaurar viabilidade'
            ], 500);
        }
    }

    /**
     * Exportar viabilidade para PDF
     *
     * @param int $id
     * @return mixed
     */
    public function exportPdf(int $id)
    {
        try {
            $viabilidade = Viabilidade::findOrFail($id);
            Gate::authorize('export', $viabilidade);

            $resultado = $this->viabilidadeService->buscarViabilidadeComDre($id);
            $viabilidade = $resultado['viabilidade'];
            $dre = $resultado['dre_resultados'];

            if (!$dre || !isset($dre['totais'])) {
                // Tenta recalcular se estiver vazio
                $resultado = $this->viabilidadeService->recalcularDre($viabilidade);
                $dre = $resultado['dre_resultados'];
            }

            if (!$dre) {
                throw new Exception('Não foi possível carregar ou gerar os dados do DRE para esta viabilidade.');
            }

            $data = [
                'viabilidade' => $viabilidade,
                'dre' => $dre,
                'dataGeracao' => now()->format('d/m/Y H:i'),
            ];

            return Pdf::view('exports.viabilidade-pdf', $data)
                ->format('a4')
                ->withBrowsershot(function ($browsershot) {
                    $browsershot->noSandbox();
                })
                ->name("viabilidade-{$id}-" . now()->format('Y-m-d') . ".pdf");

        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro no ViabilidadeController::exportPdf', [
                'viabilidade_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao exportar PDF: ' . $e->getMessage(),
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Listar viabilidades para campos de seleção
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function forSelect(Request $request): JsonResponse
    {
        try {
            Gate::authorize('viewAny', Viabilidade::class);

            $terrenoId = $request->input('terreno_id');

            $query = Viabilidade::query()->with('terreno');

            if ($terrenoId) {
                $query->where('terreno_id', $terrenoId);
            }

            $viabilidades = $query->orderBy('created_at', 'desc')->get();

            $resultado = $viabilidades->map(function ($v) {
                $data = $v->created_at->format('d/m/Y H:i');
                return [
                    'id' => $v->id,
                    'label' => "Viabilidade #{$v->id} - {$v->terreno->nome} ({$data})",
                    'terreno_id' => $v->terreno_id
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $resultado
            ]);

        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro no ViabilidadeController::forSelect', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar viabilidades'
            ], 500);
        }
    }
}
