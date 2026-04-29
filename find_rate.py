# Confirmar: correcao / VENDAS é constante
plan_corr = [66793, 60114, 54102, 48692, 43823, 39440, 35496, 32961, 30426, 27890, 25355, 22819, 20284, 17748, 15213, 12677, 10142, 7606, 5071, 2535]
plan_vendas = [20240000, 18216000, 16394400, 14754960, 13279464, 11951518, 10756366, 6914807, 6914807, 6914807, 6914807, 6914807, 6914807, 6914807, 6914807, 6914807, 6914807, 6914807, 6914807, 6914807]

r_obra = (1.05) ** (1/12) - 1
print(f"INCC mensal = {r_obra:.6f}")
print()

for i in range(20):
    ratio = plan_corr[i] / plan_vendas[i]
    print(f"Mes {i}: {plan_corr[i]:,} / {plan_vendas[i]:,} = {ratio:.10f}")

print()
ratio_avg = sum(plan_corr[:7]) / sum(plan_vendas[:7])  # primeiros 7 onde vendas variam
print(f"Ratio médio: {ratio_avg:.10f}")
print(f"Ratio / INCC: {ratio_avg/r_obra:.6f}")
print(f"Ratio * 12: {ratio_avg*12:.6f} (equivalente anual)")
