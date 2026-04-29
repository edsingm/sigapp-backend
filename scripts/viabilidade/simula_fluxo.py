"""
Replica a lógica atual do preCalcularRecebiveisCef() do FluxoMensalCalculator.php
com os mesmos marcos temporais e a mesma correção de obra da planilha.

Mudanças aplicadas:
  1. Sinal: lump sum no mês da venda
  2. Obra: parcelas iguais em `prazo_lancamento + prazo_obra - (s - 1)`
  3. Correção de obra: saldo remanescente da obra ainda não vendida no mês
  4. Pós-chave: inicia após entrega + prazo_lancamento
  5. Parcelas pós: apenas amortização; juros e correção segregados
"""
import math

# ============================================================
# PREMISSAS
# ============================================================
unidades = 1000
permutas = 80
unidades_construtora = unidades - permutas  # 920
preco = 220_000.0
prazo_pos_chave = 36
prazo_lancamento = 6
prazo_obra = 36
prazo_total_obra = prazo_lancamento + prazo_obra

percentual_sinal = 2 / 100
percentual_obra = 9 / 100
percentual_pos = 9 / 100

# Taxas mensais
r_obra = (1 + 0.05) ** (1/12) - 1   # INCC: 5.0% a.a.
r_pos = (1 + 0.045) ** (1/12) - 1  # IPCA: 4.5% a.a.
juros_mensal_pos = 1 / 100

# Curva de vendas (2 Dorm)
curva_vendas = [10, 9, 8.1, 7.29, 6.561, 5.9049, 5.31441, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428]

# ============================================================
# SIMULAÇÃO DO SISTEMA (preCalcularRecebiveisCef)
# ============================================================
recursos = {}
valor_obra_total = preco * percentual_obra * unidades_construtora
obra_vendida_acumulada = 0.0

for mes_venda, percentual_venda in enumerate(curva_vendas):
    if percentual_venda <= 0:
        continue

    s = mes_venda + 1  # mês 1-based
    unid_vendidas = unidades_construtora * percentual_venda / 100
    valor_sinal = preco * percentual_sinal
    valor_obra = preco * percentual_obra

    # ---- SINAL (lump sum no mês da venda) ----
    mes_recebimento = s - 1  # 0-based
    recursos.setdefault(mes_recebimento, {})
    recursos[mes_recebimento]['sinal'] = recursos[mes_recebimento].get('sinal', 0) + valor_sinal * unid_vendidas

    # ---- CORREÇÃO DE OBRA (saldo de obra ainda não vendido) ----
    valor_obra_coorte = valor_obra * unid_vendidas
    obra_vendida_acumulada += valor_obra_coorte
    saldo_remanescente_obra = max(0.0, valor_obra_total - obra_vendida_acumulada)

    if saldo_remanescente_obra > 0:
        recursos[mes_recebimento]['correcao_obra'] = recursos[mes_recebimento].get('correcao_obra', 0) + saldo_remanescente_obra * r_obra

    # ---- OBRA (prazo_lancamento + prazo_obra - (s-1) parcelas iguais) ----
    num_obra_parcelas = prazo_total_obra - (s - 1)

    if valor_obra > 0:
        parcela_obra = valor_obra / num_obra_parcelas

        for i in range(num_obra_parcelas):
            mes_rec = s + i - 1  # 0-based
            recursos.setdefault(mes_rec, {})
            valor_parcela_mes = parcela_obra * unid_vendidas
            recursos[mes_rec]['parcelas_obra'] = recursos[mes_rec].get('parcelas_obra', 0) + valor_parcela_mes

# ---- PARCELAS PÓS-CHAVE (início após entrega + prazo_lancamento) ----
valor_pos_total = preco * percentual_pos * unidades_construtora
amortizacao = valor_pos_total / prazo_pos_chave
mes_inicio_pos = prazo_total_obra  # 0-based do primeiro mês pós-chave

for k in range(1, prazo_pos_chave + 1):
    saldo_devedor = valor_pos_total - (amortizacao * k)
    juros_mes = saldo_devedor * juros_mensal_pos
    correcao_mes = saldo_devedor * r_pos

    mes_pos = mes_inicio_pos - 1 + k

    recursos.setdefault(mes_pos, {})
    recursos[mes_pos]['parcelas_pos'] = recursos[mes_pos].get('parcelas_pos', 0) + amortizacao
    recursos[mes_pos]['juros'] = recursos[mes_pos].get('juros', 0) + juros_mes
    recursos[mes_pos]['correcao'] = recursos[mes_pos].get('correcao', 0) + correcao_mes

# ============================================================
# GERAR TABELA COMPLETA
# ============================================================
periodo_nomes = {
    'INCORP':    lambda m: m < 0,
    'LANCTO':    lambda m: 0 <= m <= 5,
    'OBRA':      lambda m: 6 <= m <= 41,
    'ENTREGA':   lambda m: m == 41,
    'POS_OBRA':  lambda m: m >= 42,
}

def get_periodo(m):
    for nome, fn in periodo_nomes.items():
        if fn(m):
            return nome
    return '?'

print(f"{'Mês':>4} | {'Período':8} | {'Sinal':>12} | {'Parc Obra':>12} | {'Parc Pós':>12} | {'RP Total':>12} | {'Juros':>10} | {'Corr Obra':>11} | {'Corr Pós':>10} | {'Corr Tot':>10} | {'RP+J+C':>12}")
print("-" * 120)

total_sinal = 0
total_parc_obra = 0
total_parc_pos = 0
total_juros = 0
total_corr_obra = 0
total_corr_pos = 0

for mes in range(-18, 100):
    r = recursos.get(mes, {})
    sinal = r.get('sinal', 0)
    parc_obra = r.get('parcelas_obra', 0)
    parc_pos = r.get('parcelas_pos', 0)
    juros = r.get('juros', 0)
    corr_obra = r.get('correcao_obra', 0)
    corr_pos = r.get('correcao', 0)
    corr_total = corr_obra + corr_pos

    rp_total = sinal + parc_obra + parc_pos
    soma = rp_total + juros + corr_total

    if rp_total > 0.01 or juros > 0.01 or corr_total > 0.01:
        periodo = get_periodo(mes)
        print(f"{mes:4} | {periodo:8} | {sinal:>12,.0f} | {parc_obra:>12,.0f} | {parc_pos:>12,.0f} | {rp_total:>12,.0f} | {juros:>10,.0f} | {corr_obra:>11,.0f} | {corr_pos:>10,.0f} | {corr_total:>10,.0f} | {soma:>12,.0f}")

        total_sinal += sinal
        total_parc_obra += parc_obra
        total_parc_pos += parc_pos
        total_juros += juros
        total_corr_obra += corr_obra
        total_corr_pos += corr_pos

print("-" * 120)
print(f"{'TOTAL':>4} | {'':8} | {total_sinal:>12,.0f} | {total_parc_obra:>12,.0f} | {total_parc_pos:>12,.0f} | {total_sinal+total_parc_obra+total_parc_pos:>12,.0f} | {total_juros:>10,.0f} | {total_corr_obra:>11,.0f} | {total_corr_pos:>10,.0f} | {total_corr_obra+total_corr_pos:>10,.0f} | {total_sinal+total_parc_obra+total_parc_pos+total_juros+total_corr_obra+total_corr_pos:>12,.0f}")

print(f"\nResumo:")
print(f"  Sinal:           {total_sinal:>14,.0f}")
print(f"  Parc. Obra:      {total_parc_obra:>14,.0f}")
print(f"  Parc. Pós-Chave: {total_parc_pos:>14,.0f}")
print(f"  RP Total:        {total_sinal+total_parc_obra+total_parc_pos:>14,.0f}")
print(f"  Juros:           {total_juros:>14,.0f}")
print(f"  Corr. Obra:      {total_corr_obra:>14,.0f}")
print(f"  Corr. Pós (IPCA):{total_corr_pos:>14,.0f}")
print(f"  Corr. Total:     {total_corr_obra+total_corr_pos:>14,.0f}")
print(f"  Juros + Corr:    {total_juros+total_corr_obra+total_corr_pos:>14,.0f}")
print(f"")
print(f"  Esperado planilha: Juros=3.187.800, Corr Obra=579.187, Corr Pós=1.171.456, J+C=4.938.443")
