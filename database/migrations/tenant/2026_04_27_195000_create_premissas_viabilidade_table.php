<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('premissas_viabilidade', function (Blueprint $table) {
            $table->id();

            $table->string('nome')->nullable()
                ->comment('Identificação amigável do conjunto de premissas (ex.: Padrão, Conservador)');

            $table->string('perfil_financiamento')->default('cef')
                ->comment('Perfil de financiamento: cef ou proprio');

            $table->boolean('ativo')->default(true)
                ->comment('Se este conjunto de premissas está ativo como default do tenant');

            $table->decimal('pis_cofins', 10, 4)->default(4.0);
            $table->decimal('iss', 10, 4)->default(0.0);
            $table->decimal('outros_impostos', 10, 4)->default(0.5);
            $table->decimal('comissao', 10, 4)->default(0.001);
            $table->decimal('parceria_vgv', 10, 4)->default(0.0);
            $table->decimal('infra_nao_incidente', 10, 4)->default(1.0);
            $table->decimal('incorporacao', 10, 4)->default(1.0);
            $table->decimal('area_comum', 12, 2)->default(0.00);
            $table->decimal('contrapartidas', 10, 4)->default(0.0);
            $table->decimal('canteiro_mensal', 12, 2)->default(85715.00);
            $table->decimal('mo_administrativa', 12, 2)->default(62502.00);
            $table->decimal('seguros', 10, 4)->default(0.5);
            $table->decimal('assistencia_tecnica', 10, 4)->default(1.0);
            $table->decimal('despesas_comerciais', 10, 4)->default(5.0);
            $table->decimal('stand_vendas', 12, 2)->default(0.00);
            $table->decimal('mobilia_decoracao', 12, 2)->default(90000.00);
            $table->decimal('ajuda_custo_gerente', 12, 2)->default(5000.00);
            $table->decimal('ajuda_custo_gerente_regional', 12, 2)->default(2733.00);
            $table->decimal('reembolso_logistica', 12, 2)->default(5000.00);
            $table->decimal('bonus_cca', 12, 2)->default(350.00);
            $table->decimal('bonus_gerente', 10, 4)->default(0.3);
            $table->decimal('bonus_gerente_regional', 10, 4)->default(0.12);
            $table->decimal('bonus_credito', 10, 4)->default(0.05);
            $table->decimal('bonus_gestor_comercial', 10, 4)->default(0.05);
            $table->decimal('pagamento_comissao_desligamento', 10, 4)->default(50.0);
            $table->integer('parcelamento_comissao_meses')->default(18);
            $table->decimal('marketing', 10, 4)->default(1.0);
            $table->decimal('itbi_iptu', 10, 4)->default(1.1);
            $table->decimal('registro', 12, 2)->default(2500.00);
            $table->decimal('custo_contratacao_cef', 12, 2)->default(0.00);
            $table->decimal('custo_medicao_cef', 12, 2)->default(0.00);
            $table->decimal('contratos_cef', 12, 2)->default(300.00);
            $table->decimal('produtos_cef', 10, 4)->default(0.5);
            $table->decimal('outras_despesas_financeiras', 10, 4)->default(0.3);
            $table->decimal('despesas_onerosas_bancos', 10, 4)->default(10.0);
            $table->integer('prazo_obra')->default(36);
            $table->decimal('compra_terreno', 12, 2)->default(0.00);
            $table->decimal('porcentagem_lote_proprietario', 10, 4)->default(10.0);
            $table->decimal('taxa_juros_pj', 10, 4)->default(10.5);
            $table->decimal('percentual_antecipacao_pj', 10, 4)->default(10.0);
            $table->decimal('aporte_adicional_mensal', 12, 2)->default(0.00);
            $table->decimal('devolucao_aporte_percentual', 10, 4)->default(20.0);
            $table->decimal('distribuicao_lucros_percentual_obra', 10, 4)->default(100.0);
            $table->decimal('taxa_exposicao_aplicada', 10, 4)->default(12.5);
            $table->json('avaliacao_lotes_cef')->nullable();
            $table->decimal('inadimplencia', 10, 4)->default(0.10);
            $table->integer('atraso_meses')->default(2);
            $table->decimal('taxa_perda', 10, 4)->default(0.02);

            $table->integer('meses_incorporacao')->default(18);
            $table->integer('meses_lancamento')->default(6);
            $table->integer('meses_entrega')->default(1);
            $table->integer('meses_pos_obra')->default(60);
            $table->decimal('variavel_correcao', 10, 6)->default(0.027545);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('premissas_viabilidade');
    }
};
