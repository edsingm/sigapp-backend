import math

preco = 220_000
unid = 920
r_obra = (1.05) ** (1/12) - 1
curva = [10, 9, 8.1, 7.29, 6.561, 5.9049, 5.31441, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428]

plan_corr = [66793, 60114, 54102, 48692, 43823, 39440, 35496, 32961, 30426, 27890, 25355, 22819, 20284, 17748, 15213, 12677, 10142, 7606, 5071, 2535]

# Computar TOTAL obra
total_obra = preco * 0.09 * unid
print(f"Total obra = {total_obra:,.0f}")

# Computar base = total_obra × 0.81 (factor from corr/VENDAS ratio)
base = total_obra * 0.81

# Computar RP obra por mes
obra_rp = {}
for ms, pct in enumerate(curva):
    if pct <= 0: continue
    s = ms + 1
    u = unid * pct / 100
    valor_obra = preco * 0.09
    n = 42 - (s - 1)
    parc = valor_obra / n
    for i in range(n):
        m = s + i - 1
        obra_rp[m] = obra_rp.get(m, 0) + parc * u

# Track saldo from base, sub obra_rp each month
print("\nCorr = (saldo - rp_recebida_ate_mes) * r_obra")
saldo_initial = base  # 0.81 × total_obra
total_recebido = 0
for mes in range(20):
    rp = obra_rp.get(mes, 0)
    saldo = saldo_initial - total_recebido
    corr = saldo * r_obra
    match = abs(corr - plan_corr[mes]) < 100
    print(f"Mes {mes:2}: RP={rp:>8,.0f} saldo={saldo:>10,.0f} corr={corr:>10,.0f} plan={plan_corr[mes]:>8,.0f} {'✅' if match else '🔴'}")
    total_recebido += rp

# Hmm - corr[0] would be base * r_obra = 14844k * 0.004074
base_r = total_obra * 0.81 * r_obra
print(f"\nBase * r_obra = {base_r:,.0f} (target month0={plan_corr[0]:,.0f})")
print(f"Need base = {plan_corr[0] / r_obra:,.0f} to get month 0 correct")

# What if it's VGV_vendas × INCC × 0.81 for months 0-6, then tapering?
print("\nCorr = VENDAS × 0.81 × r_obra (months 0-6 only)")
for mes in range(7):
    vendas_mes = unid * curva[mes] / 100 * preco * 0.09
    corr = vendas_mes * 0.81 * r_obra
    print(f"  Mes {mes}: corr={corr:,.0f} plan={plan_corr[mes]:,.0f}")
