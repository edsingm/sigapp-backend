import math

# Dados da planilha - correção nos meses 0-19
plan_corr = [66793, 60114, 54102, 48692, 43823, 39440, 35496, 32961, 30426, 27890, 25355, 22819, 20284, 17748, 15213, 12677, 10142, 7606, 5071, 2535]

# Dados da planilha - RP (recebimentos) nos meses 0-19
plan_rp = [448171, 447678, 448133, 449394, 451336, 453848, 456836, 397786, 416090, 434948, 454396, 474471, 495216, 516675, 538902, 561951, 585887, 610780, 636711, 663769]

# Hipótese: correção = saldo_recebiveis * taxa_INCC_mensal
# O saldo começa em 18,216,000 (9% do VGV)
r_obra = (1.05) ** (1/12) - 1  # 0.4074% a.m.

saldo = 18_216_000
corr_calc = []
print("Hipótese: corr = saldo * INCC_mensal")
print(f"{'Mes':>4} | {'Saldo Ini':>12} | {'Corr Calc':>10} | {'Corr Plan':>10} | {'Match?'}")
for mes in range(20):
    c = saldo * r_obra
    corr_calc.append(c)
    match = abs(c - plan_corr[mes]) < 500
    print(f"{mes:4} | {saldo:>12,.0f} | {c:>10,.0f} | {plan_corr[mes]:>10,.0f} | {'✅' if match else '🔴'}")
    saldo -= plan_rp[mes]

print(f"\nTotal calc: {sum(corr_calc):,.0f}, Total plan: {sum(plan_corr):,.0f}")

# Hipótese 2: correção = parcela_recebida * INCC_acumulado_desde_venda
# Como fizemos antes com 42 parcelas iguais sem INCC
# Para cada coorte, calcular correção = parcela * ((1+r_obra)^t - 1)
# e somar por mês
preco = 220_000
unidades_construtora = 920
curva = [10, 9, 8.1, 7.29, 6.561, 5.9049, 5.31441, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428]

recursos = {}
for mes_venda, pct in enumerate(curva):
    if pct <= 0: continue
    s = mes_venda + 1
    unid = unidades_construtora * pct / 100
    valor_obra = preco * 0.09
    num_parc = 42 - (s - 1)
    parcela = valor_obra / num_parc
    
    for i in range(num_parc):
        mes_rec = s + i - 1
        meses_passados = mes_rec - (s - 1)
        parcela_ajustada = parcela * ((1 + r_obra) ** meses_passados)
        corr = parcela_ajustada - parcela
        
        recursos.setdefault(mes_rec, {})
        recursos[mes_rec]['corr_obra'] = recursos[mes_rec].get('corr_obra', 0) + corr * unid

print("\nHipótese 2: INCC sobre parcela nominal (42-s parcelas)")
print(f"{'Mes':>4} | {'Corr Calc':>10} | {'Corr Plan':>10} | {'Match?'}")
soma = 0
for mes in range(20):
    c = recursos.get(mes, {}).get('corr_obra', 0)
    match = abs(c - plan_corr[mes]) < 500
    print(f"{mes:4} | {c:>10,.0f} | {plan_corr[mes]:>10,.0f} | {'✅' if match else '🔴'}")
    soma += c
print(f"Total calc: {soma:,.0f}, Total plan: {sum(plan_corr):,.0f}")
