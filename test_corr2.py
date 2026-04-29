import math

preco = 220_000
unidades_construtora = 920
r_obra = (1.05) ** (1/12) - 1

curva = [10, 9, 8.1, 7.29, 6.561, 5.9049, 5.31441, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428]

plan_corr = [66793, 60114, 54102, 48692, 43823, 39440, 35496, 32961, 30426, 27890, 25355, 22819, 20284, 17748, 15213, 12677, 10142, 7606, 5071, 2535]

# Modelo: saldo_obra_acumulado * r_obra
# saldo_obra = soma de todos os coortes: (valor_obra * (42-s) / (42-(s-1))) * unid
# onde (42-s) é o número de meses restantes de obra para a coorte s
print("=== Correção INCC: saldo_obra * r_obra ===")
saldo_obra = 0
for mes_venda, pct in enumerate(curva):
    if pct <= 0: continue
    s = mes_venda + 1
    unid = unidades_construtora * pct / 100
    valor_obra = preco * 0.09
    num_parc = 42 - (s - 1)
    # saldo inicial: todas as parcelas menos 1 (já que a primeira é paga no mesmo mês)
    saldo_inicial_coorte = valor_obra * (num_parc - 1) / num_parc * unid
    # Na verdade: no mês 0, a coorte 1 tem 42-1 parcelas restantes = 41 parcelas * valor_parcela * unid
    saldo_inicial_coorte = valor_obra * (num_parc - 1) / num_parc * unid
    saldo_obra += saldo_inicial_coorte

# Agora mês a mês: aplicar r_obra sobre saldo, depois subtrair as parcelas recebidas
# Mas precisamos de um loop mês a mês

# Vamos calcular coorte por coorte de forma mais direta:
# Para cada mês m, soma sobre todas as coortes s <= m:
#   saldo_restante(s,m) = (parcela * unid) * max(0, (42-s) - (m - s + 1))
#                      = (parcela * unid) * max(0, 41 - m)

# Vou calcular corr[m] = sum_{s <= m} saldo_restante(s,m) × r_obra
corr_calc = []
monthly_obra_rp = {}  # obra RP per month (for delta validation)

# Primeiro, computar o RP de obra por mês
for mes_venda, pct in enumerate(curva):
    if pct <= 0: continue
    s = mes_venda + 1
    unid = unidades_construtora * pct / 100
    valor_obra = preco * 0.09
    num_parc = 42 - (s - 1)
    parcela = valor_obra / num_parc
    for i in range(num_parc):
        mes = s + i - 1
        monthly_obra_rp[mes] = monthly_obra_rp.get(mes, 0) + parcela * unid

# Agora, para cada mês, calcular saldo_obra e correção
saldo = 0.0
print(f"Mes | Saldo Ini | RP Obra | Saldo Fin | Corr=r*S(ini) | Corr=r*S(fin) | Plan")
for mes in range(20):
    saldo_ini = saldo
    rp_obra_mes = monthly_obra_rp.get(mes, 0)
    corr_ini = saldo_ini * r_obra
    saldo -= rp_obra_mes
    corr_fin = saldo * r_obra
    print(f"{mes:3} | {saldo_ini:>10,.0f} | {rp_obra_mes:>8,.0f} | {saldo:>10,.0f} | {corr_ini:>10,.0f} | {corr_fin:>10,.0f} | {plan_corr[mes]:>8,.0f}")
