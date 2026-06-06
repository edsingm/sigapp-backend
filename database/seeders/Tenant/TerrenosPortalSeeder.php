<?php

namespace Database\Seeders\Tenant;

use App\Enums\WorkflowStatus;
use App\Models\Central\Cidade;
use App\Models\Tenant\CorretorExterno;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\TerrenoProduto;
use App\Models\Tenant\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Importa os terrenos enriquecidos do portal para as tabelas:
 *   - terrenos          (núcleo + datas + polígono + status)
 *   - terreno_produtos  (tipologias do formulário hiperdados)
 *   - corretores_externos (cria se não existir, por nome)
 *
 * Idempotente: usa updateOrCreate/firstOrCreate em cada entidade.
 * Produtos importados do portal são marcados com "[portal]" em observacoes
 * e ressincronizados a cada execução, preservando produtos cadastrados
 * manualmente (sem essa tag).
 *
 * Gere o JSON antes com:
 *   php database/dados_teste/enriquecer_terrenos_portal.php
 *
 * Para rodar em um tenant específico:
 *   php artisan tenants:seed --class=TerrenosPortalSeeder --tenants=TENANT_ID
 */
class TerrenosPortalSeeder extends Seeder
{
    private const PRODUTO_TAG = '[portal]';

    private const STATUS_MAP = [
        'Análise' => WorkflowStatus::EM_ANALISE,
        'Negociação' => WorkflowStatus::NEGOCIACAO_MINUTA,
        'Minuta' => WorkflowStatus::NEGOCIACAO_MINUTA,
        'Assinado' => WorkflowStatus::CONTRATO_ASSINADO,
        'Descartado' => WorkflowStatus::DESCARTADO,
        'StandBy' => WorkflowStatus::ARQUIVADO,
        'Distratado' => WorkflowStatus::ARQUIVADO,
    ];

    /** @var array<string, string>  normalizado(city)|UF => code IBGE */
    private array $cidadeIndex = [];

    /** @var array<string, int>  nome do gestor => user id (cache local) */
    private array $gestorIndex = [];

    public function run(): void
    {
        $path = database_path('dados_teste/terrenos_portal_enriquecido.json');

        if (! is_file($path)) {
            $this->command?->error("Arquivo não encontrado: {$path}. Rode enriquecer_terrenos_portal.php primeiro.");

            return;
        }

        $terrenos = json_decode((string) file_get_contents($path), true);

        if (! is_array($terrenos)) {
            $this->command?->error('JSON inválido: '.$path);

            return;
        }

        $this->carregarCidades();

        $importados = 0;
        $cidadesNaoResolvidas = [];

        foreach ($terrenos as $item) {
            $ficha = $item['ficha'] ?? null;
            $formulario = $item['formulario'] ?? [];
            $corretoresList = $item['corretores'] ?? [];

            $statusPortal = $ficha['status_portal'] ?? ($item['status'] ?? '');
            $cidadeNome = $ficha['cidade'] ?? '';
            $uf = $ficha['uf'] ?? '';
            $cidadeCode = $this->resolverCidade($cidadeNome, $uf);

            if ($cidadeNome !== '' && $cidadeCode === null) {
                $cidadesNaoResolvidas[$cidadeNome.'/'.$uf] = true;
            }

            $corretorId = $this->resolverCorretor($corretoresList);
            $gestorNome = $ficha['gestor'] ?? ($item['gestor'] ?? '');
            $responsavelId = $this->resolverGestor($gestorNome);
            $dataApresentacao = $this->parseData($formulario['Data Apresentação'] ?? '');

            $terreno = Terreno::updateOrCreate(
                ['nome' => $item['nome'] ?? ('Terreno '.($item['id'] ?? ''))],
                [
                    'endereco' => $this->montarEndereco($ficha),
                    'bairro' => $this->valorOuNull($ficha['bairro'] ?? ''),
                    'estado' => $uf !== '' ? $uf : null,
                    'cidade_code' => $cidadeCode,
                    'zona' => $this->valorOuNull($ficha['zona_regional'] ?? ''),
                    'distrito' => $this->valorOuNull($ficha['distrito'] ?? ''),
                    'operacao_urbana' => $this->valorOuNull($ficha['operacao_urbana'] ?? ''),
                    'area_total' => $this->parseNumero($ficha['area_total'] ?? ''),
                    'polygon_coords' => $item['poligono'] ?? null,
                    'corretor_id' => $corretorId,
                    'responsavel_id' => $responsavelId,
                    'workflow_status_code' => $this->mapearStatus($statusPortal)->value,
                    'data_apresentacao' => $dataApresentacao,
                    'data_negociacao' => $this->parseData($formulario['Data Negociação'] ?? ''),
                    'data_opcao' => $this->parseData($formulario['Data Assinatura'] ?? ''),
                    'data_contrato' => $this->parseData($formulario['Data Contrato'] ?? ''),
                    'data_descarte' => $this->parseData($formulario['Data Descarte'] ?? ''),
                    'observacoes' => $this->montarObservacoes($statusPortal, $gestorNome),
                ]
            );

            // created_at deve refletir a data de apresentação, não a data do import.
            if ($dataApresentacao !== null) {
                Terreno::withoutTimestamps(fn () => $terreno->update(['created_at' => $dataApresentacao]));
            }

            $this->sincronizarProdutos($terreno, $ficha['produtos'] ?? [], $formulario);
            $importados++;
        }

        $this->command?->info("Terrenos importados/atualizados: {$importados}.");

        if ($cidadesNaoResolvidas !== []) {
            $this->command?->warn('Cidades não resolvidas (cidade_code nulo): '.implode(', ', array_keys($cidadesNaoResolvidas)));
        }
    }

    // -------------------------------------------------------------------------
    // Produtos
    // -------------------------------------------------------------------------

    /**
     * @param  array<int, array<string, string>>  $produtosTabela  Da tabela da ficha HTML
     * @param  array<string, string>  $formulario  Do formulário hiperdados
     */
    private function sincronizarProdutos(Terreno $terreno, array $produtosTabela, array $formulario): void
    {
        // Remove apenas os produtos importados anteriormente do portal
        TerrenoProduto::where('terreno_id', $terreno->id)
            ->where('observacoes', 'like', self::PRODUTO_TAG.'%')
            ->forceDelete();

        // Prefere a tabela da ficha (mais detalhada) quando disponível
        if ($produtosTabela !== []) {
            foreach ($produtosTabela as $produto) {
                TerrenoProduto::create([
                    'terreno_id' => $terreno->id,
                    'produto_id' => null,
                    'unidades' => $this->parseInteiro($produto['unidades'] ?? ''),
                    'valor' => $this->parseNumero($produto['preco'] ?? ''),
                    'permuta' => $this->parseInteiro($produto['permutas'] ?? ''),
                    'observacoes' => trim(self::PRODUTO_TAG.' '.($produto['tipo_unidade'] ?? '').
                        (! empty($produto['lancamento']) ? ' lançamento '.$produto['lancamento'] : '')),
                ]);
            }

            return;
        }

        // Fallback: constrói produtos a partir do formulário hiperdados
        $linhas = $this->extrairProdutosDoFormulario($formulario);

        foreach ($linhas as $linha) {
            TerrenoProduto::create([
                'terreno_id' => $terreno->id,
                'produto_id' => null,
                'unidades' => $linha['unidades'],
                'valor' => $linha['valor'],
                'permuta' => null,
                'observacoes' => self::PRODUTO_TAG.' '.$linha['tipo'],
            ]);
        }
    }

    /**
     * Constrói linhas de produto a partir dos campos do formulário hiperdados.
     * Padrão observado: "Quant. de Unidades 2 Dorm" / "Valor Unid. 2 Dorm."
     *                   "Quant. Unidades 3 Dorm."    / "Valor Unid. 3 Dorm."
     *
     * @param  array<string, string>  $formulario
     * @return array<int, array{tipo: string, unidades: ?int, valor: ?float}>
     */
    private function extrairProdutosDoFormulario(array $formulario): array
    {
        $candidatos = [
            '2 Dorm' => [
                'unidades' => $formulario['Quant. de Unidades 2 Dorm'] ?? ($formulario['Quant. Unidades 2 Dorm'] ?? ''),
                'valor' => $formulario['Valor Unid. 2 Dorm.'] ?? '',
            ],
            '3 Dorm' => [
                'unidades' => $formulario['Quant. Unidades 3 Dorm.'] ?? ($formulario['Quant. Unidades 3 Dorm'] ?? ''),
                'valor' => $formulario['Valor Unid. 3 Dorm.'] ?? '',
            ],
        ];

        $produtos = [];

        foreach ($candidatos as $tipo => $dados) {
            $unidades = $this->parseInteiro($dados['unidades']);

            if ($unidades === null || $unidades === 0) {
                continue;
            }

            $produtos[] = [
                'tipo' => $tipo,
                'unidades' => $unidades,
                'valor' => $this->parseNumero($dados['valor']),
            ];
        }

        return $produtos;
    }

    // -------------------------------------------------------------------------
    // Gestores (users)
    // -------------------------------------------------------------------------

    /**
     * Resolve o user correspondente ao gestor, criando-o se ainda não existir.
     * Usa cache local para evitar N queries por gestor repetido.
     * Usuários criados aqui têm senha inutilizável — precisam de convite/reset.
     */
    private function resolverGestor(string $nome): ?int
    {
        $nome = trim($nome);

        if ($nome === '') {
            return null;
        }

        if (isset($this->gestorIndex[$nome])) {
            return $this->gestorIndex[$nome];
        }

        $email = Str::slug($nome, '.').'@portal.comproterreno';

        $user = User::firstOrCreate(
            ['name' => $nome],
            [
                'email' => $email,
                'password' => Hash::make(Str::random(32)),
                'email_verified_at' => null,
            ]
        );

        $this->gestorIndex[$nome] = $user->id;

        return $user->id;
    }

    // -------------------------------------------------------------------------
    // Corretores
    // -------------------------------------------------------------------------

    /**
     * Resolve o primeiro corretor da lista, criando o registro se necessário.
     *
     * @param  array<int, array<string, string>>  $corretoresList
     */
    private function resolverCorretor(array $corretoresList): ?int
    {
        if ($corretoresList === []) {
            return null;
        }

        $dados = $corretoresList[0];
        $nome = trim($dados['nome'] ?? '');

        if ($nome === '') {
            return null;
        }

        $telefone = trim($dados['celular'] ?? $dados['telefone'] ?? '');
        $emailPlaceholder = Str::slug($nome, '.').'@portal.comproterreno';

        $corretor = CorretorExterno::firstOrCreate(
            ['nome' => $nome],
            [
                'email' => $emailPlaceholder,
                'telefone' => $telefone,
                'creci' => '',
            ]
        );

        return $corretor->id;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function montarEndereco(?array $ficha): ?string
    {
        if ($ficha === null) {
            return null;
        }

        $endereco = trim($ficha['endereco'] ?? '');
        $complemento = trim($ficha['complemento'] ?? '');
        $completo = $endereco.($complemento !== '' ? ', '.$complemento : '');

        return $completo !== '' ? $completo : null;
    }

    private function montarObservacoes(string $statusPortal, string $gestor): string
    {
        $partes = ['Importado do portal comproterreno.'];

        if ($statusPortal !== '') {
            $partes[] = "Status original: {$statusPortal}.";
        }

        if ($gestor !== '') {
            $partes[] = "Gestor: {$gestor}.";
        }

        return implode(' ', $partes);
    }

    private function mapearStatus(string $statusPortal): WorkflowStatus
    {
        return self::STATUS_MAP[$statusPortal] ?? WorkflowStatus::EM_ANALISE;
    }

    private function carregarCidades(): void
    {
        Cidade::query()
            ->get(['code', 'city', 'state_code'])
            ->each(function (Cidade $cidade): void {
                $chave = $this->normalizar($cidade->city).'|'.strtoupper($cidade->state_code);
                $this->cidadeIndex[$chave] = $cidade->code;
            });
    }

    private function resolverCidade(string $nome, string $uf): ?string
    {
        if ($nome === '' || $uf === '') {
            return null;
        }

        return $this->cidadeIndex[$this->normalizar($nome).'|'.strtoupper($uf)] ?? null;
    }

    private function normalizar(string $valor): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', Str::ascii($valor)) ?? $valor));
    }

    private function parseNumero(string $valor): ?float
    {
        // Remove tudo exceto dígitos e vírgula (ex: "R$ 160.000,00" → "160000.00")
        $limpo = preg_replace('/[^\d,]/', '', $valor) ?? '';

        if ($limpo === '') {
            return null;
        }

        return (float) str_replace(',', '.', $limpo);
    }

    private function parseInteiro(string $valor): ?int
    {
        $limpo = preg_replace('/\D/', '', $valor) ?? '';

        return $limpo === '' ? null : (int) $limpo;
    }

    private function parseData(string $valor): ?string
    {
        $valor = trim($valor);

        if (! preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $valor)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $valor)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function valorOuNull(string $valor): ?string
    {
        $valor = trim($valor);

        return $valor !== '' ? $valor : null;
    }
}
