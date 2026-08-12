<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\WorkflowStatus;
use App\Models\Central\Cidade;
use App\Models\Tenant\CorretorExterno;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\TerrenoProduto;
use App\Models\Tenant\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Importa terrenos enriquecidos do portal Hiperdados no schema do tenant.
 * Lógica extraída de TerrenosPortalSeeder para reuso via API admin.
 */
class HiperdadosTerrenoCommitService
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

    /** @var array<string, string> */
    private array $cidadeIndex = [];

    /** @var array<string, int> */
    private array $gestorIndex = [];

    /**
     * @param  list<array<string, mixed>>  $terrenos
     * @return array{imported: int, cidades_nao_resolvidas: list<string>}
     */
    public function commit(array $terrenos): array
    {
        $this->cidadeIndex = [];
        $this->gestorIndex = [];
        $this->carregarCidades();

        $importados = 0;
        $cidadesNaoResolvidas = [];

        Terreno::withoutEvents(function () use ($terrenos, &$importados, &$cidadesNaoResolvidas): void {
            foreach ($terrenos as $item) {
                $ficha = is_array($item['ficha'] ?? null) ? $item['ficha'] : null;
                $formulario = is_array($item['formulario'] ?? null) ? $item['formulario'] : [];
                $corretoresList = is_array($item['corretores'] ?? null) ? $item['corretores'] : [];

                $statusPortal = (string) ($ficha['status_portal'] ?? ($item['status'] ?? ''));
                $cidadeNome = (string) ($ficha['cidade'] ?? '');
                $uf = (string) ($ficha['uf'] ?? '');
                $cidadeCode = $this->resolverCidade($cidadeNome, $uf);

                if ($cidadeNome !== '' && $cidadeCode === null) {
                    $cidadesNaoResolvidas[$cidadeNome.'/'.$uf] = true;
                }

                $corretorId = $this->resolverCorretor($corretoresList);
                $gestorNome = (string) ($ficha['gestor'] ?? ($item['gestor'] ?? ''));
                $responsavelId = $this->resolverGestor($gestorNome);
                $dataApresentacao = $this->parseData((string) ($formulario['Data Apresentação'] ?? ''));

                $nome = (string) ($item['nome'] ?? ('Terreno '.($item['id'] ?? '')));
                if ($nome === '') {
                    $nome = 'Terreno '.($item['id'] ?? Str::uuid()->toString());
                }

                $terreno = Terreno::updateOrCreate(
                    ['nome' => $nome],
                    [
                        'endereco' => $this->montarEndereco($ficha),
                        'bairro' => $this->valorOuNull((string) ($ficha['bairro'] ?? '')),
                        'estado' => $uf !== '' ? $uf : null,
                        'cidade_code' => $cidadeCode,
                        'zona' => $this->valorOuNull((string) ($ficha['zona_regional'] ?? '')),
                        'distrito' => $this->valorOuNull((string) ($ficha['distrito'] ?? '')),
                        'operacao_urbana' => $this->valorOuNull((string) ($ficha['operacao_urbana'] ?? '')),
                        'area_total' => $this->parseNumero((string) ($ficha['area_total'] ?? '')),
                        'polygon_coords' => is_array($item['poligono'] ?? null) ? $item['poligono'] : null,
                        'corretor_id' => $corretorId,
                        'responsavel_id' => $responsavelId,
                        'workflow_status_code' => $this->mapearStatus($statusPortal)->value,
                        'data_apresentacao' => $dataApresentacao,
                        'data_negociacao' => $this->parseData((string) ($formulario['Data Negociação'] ?? '')),
                        'data_opcao' => $this->parseData((string) ($formulario['Data Assinatura'] ?? '')),
                        'data_contrato' => $this->parseData((string) ($formulario['Data Contrato'] ?? '')),
                        'data_descarte' => $this->parseData((string) ($formulario['Data Descarte'] ?? '')),
                        'observacoes' => $this->montarObservacoes($statusPortal, $gestorNome),
                    ]
                );

                if ($dataApresentacao !== null) {
                    Terreno::withoutTimestamps(fn () => $terreno->update(['created_at' => $dataApresentacao]));
                }

                $produtosTabela = is_array($ficha['produtos'] ?? null) ? $ficha['produtos'] : [];
                $this->sincronizarProdutos($terreno, $produtosTabela, $formulario);
                $importados++;
            }
        });

        return [
            'imported' => $importados,
            'cidades_nao_resolvidas' => array_keys($cidadesNaoResolvidas),
        ];
    }

    /**
     * @param  array<int, array<string, string>>  $produtosTabela
     * @param  array<string, mixed>  $formulario
     */
    private function sincronizarProdutos(Terreno $terreno, array $produtosTabela, array $formulario): void
    {
        TerrenoProduto::where('terreno_id', $terreno->id)
            ->where('observacoes', 'like', self::PRODUTO_TAG.'%')
            ->forceDelete();

        if ($produtosTabela !== []) {
            foreach ($produtosTabela as $produto) {
                TerrenoProduto::create([
                    'terreno_id' => $terreno->id,
                    'produto_id' => null,
                    'unidades' => $this->parseInteiro((string) ($produto['unidades'] ?? '')),
                    'valor' => $this->parseNumero((string) ($produto['preco'] ?? '')),
                    'permuta' => $this->parseInteiro((string) ($produto['permutas'] ?? '')),
                    'observacoes' => trim(self::PRODUTO_TAG.' '.($produto['tipo_unidade'] ?? '').
                        (! empty($produto['lancamento']) ? ' lançamento '.$produto['lancamento'] : '')),
                ]);
            }

            return;
        }

        foreach ($this->extrairProdutosDoFormulario($formulario) as $linha) {
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
     * @param  array<string, mixed>  $formulario
     * @return list<array{tipo: string, unidades: ?int, valor: ?float}>
     */
    private function extrairProdutosDoFormulario(array $formulario): array
    {
        $candidatos = [
            '2 Dorm' => [
                'unidades' => (string) ($formulario['Quant. de Unidades 2 Dorm'] ?? ($formulario['Quant. Unidades 2 Dorm'] ?? '')),
                'valor' => (string) ($formulario['Valor Unid. 2 Dorm.'] ?? ''),
            ],
            '3 Dorm' => [
                'unidades' => (string) ($formulario['Quant. Unidades 3 Dorm.'] ?? ($formulario['Quant. Unidades 3 Dorm'] ?? '')),
                'valor' => (string) ($formulario['Valor Unid. 3 Dorm.'] ?? ''),
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

    /**
     * @param  array<int, mixed>  $corretoresList
     */
    private function resolverCorretor(array $corretoresList): ?int
    {
        if ($corretoresList === []) {
            return null;
        }

        $dados = $corretoresList[0];
        if (! is_array($dados)) {
            return null;
        }

        $nome = trim((string) ($dados['nome'] ?? ''));

        if ($nome === '') {
            return null;
        }

        $telefone = trim((string) ($dados['celular'] ?? $dados['telefone'] ?? ''));
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

    /**
     * @param  array<string, mixed>|null  $ficha
     */
    private function montarEndereco(?array $ficha): ?string
    {
        if ($ficha === null) {
            return null;
        }

        $endereco = trim((string) ($ficha['endereco'] ?? ''));
        $complemento = trim((string) ($ficha['complemento'] ?? ''));
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
                $city = (string) $cidade->getAttribute('city');
                $state = (string) $cidade->getAttribute('state_code');
                $code = (string) $cidade->getAttribute('code');
                $chave = $this->normalizar($city).'|'.strtoupper($state);
                $this->cidadeIndex[$chave] = $code;
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
            $parsed = Carbon::createFromFormat('d/m/Y', $valor);

            return $parsed !== null ? $parsed->format('Y-m-d') : null;
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
