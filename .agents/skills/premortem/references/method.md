# Método completo de premortem

Use este fluxo no modo completo. Adapte a quantidade de riscos e a profundidade
ao plano; não preencha cotas com riscos fracos.

## 1. Cápsula de contexto

Registre de forma concisa:

- **Plano:** o que será feito e o que já está comprometido.
- **Pessoas afetadas:** clientes, equipe, parceiros e decisores.
- **Sucesso:** resultado, prazo e métricas relevantes.
- **Fracasso assumido:** qual resultado não foi alcançado.
- **Horizonte:** quando estamos olhando para trás e por que esse prazo faz
  sentido.
- **Restrições e dependências:** orçamento, capacidade, tecnologia, fornecedores,
  regulação e decisões irreversíveis.
- **Base disponível:** fatos confirmados, inferências e desconhecidos críticos.

Mostre a cápsula ao usuário quando uma suposição importante puder alterar o
resultado. Não transforme a coleta de contexto em questionário se a conversa já
for suficiente.

## 2. Descoberta independente

O coordenador e cada analista recebem a mesma cápsula neutra e geram modos de
falha sem acesso às listas dos demais. Essa independência vem antes do
aprofundamento; ela é o mecanismo para encontrar pontos cegos.

Prompt sugerido para cada analista:

```text
Execute uma descoberta independente para este premortem.

Contexto:
[cápsula de contexto]

Enquadramento: estamos em [horizonte] e o plano fracassou em [resultado].

Identifique apenas modos de falha genuínos e específicos. Para cada um, informe:
- modo de falha em uma frase;
- mecanismo causal: como ele derruba o resultado;
- base: fato, inferência ou hipótese;
- por que é plausível neste plano;
- um sinal precoce observável.

Procure causas-raiz e cadeias de falha. Não complete uma quantidade fixa, não
invente fatos e não leia conclusões de outros analistas.
```

Use dois ou três analistas conforme a capacidade disponível. Não crie um agente
por risco. O coordenador também deve fazer sua própria passagem antes de ler as
respostas.

## 3. Consolidação causal

Agrupe formulações equivalentes e preserve divergências reais. Para cada risco
consolidado:

1. dê um nome específico;
2. separe causa-raiz, mecanismo e consequência;
3. indique quais riscos ele pode desencadear;
4. registre fatos, inferências e desconhecidos;
5. descarte inconvenientes menores e casos remotos sem mecanismo plausível.

O objetivo usual é chegar a 4–8 riscos consolidados, mas o plano determina o
número. Não afirme que a lista é exaustiva.

## 4. Revisão de cobertura

Depois da consolidação, faça uma segunda revisão procurando omissões. Use as
lentes abaixo apenas como verificação, não como categorias obrigatórias da
primeira rodada:

- demanda, adoção e comportamento do usuário;
- execução, capacidade, prazo e coordenação;
- economia, preço, caixa e incentivos;
- fornecedores, integrações e dependências externas;
- confiabilidade, segurança, privacidade e dados;
- jurídico, regulação e reputação;
- governança, propriedade das decisões e conflitos de interesse;
- mudanças de mercado, concorrência e timing.

Acrescente um risco somente quando houver um mecanismo causal específico para o
plano.

## 5. Avaliação calibrada

Avalie cada modo de falha sem fingir precisão estatística:

- **Probabilidade:** baixa, média ou alta.
- **Impacto:** moderado, alto ou crítico.
- **Visibilidade antecipada:** baixa, média ou alta. Baixa visibilidade torna o
  risco mais difícil de detectar a tempo.
- **Confiança:** baixa, média ou alta, conforme a evidência disponível.
- **Recuperabilidade:** fácil, difícil ou irreversível depois de ocorrido.

Explique a classificação em uma frase. Não multiplique notas para produzir uma
prioridade mecânica. Considere interdependências, velocidade de propagação e
custo de recuperação.

## 6. Aprofundamento

Aprofunde os riscos prioritários em lotes, respeitando o limite de agentes. Cada
análise deve conter:

1. **Cadeia do fracasso:** sequência causal curta, ancorada no contexto, sem
   detalhes inventados.
2. **Premissa subjacente:** o que precisava ser verdade para o plano funcionar.
3. **Sinais precoces:** um a três indicadores observáveis, com limiar quando
   houver base para defini-lo.
4. **Prevenção:** mudança concreta antes da execução.
5. **Contingência:** resposta se o sinal aparecer.
6. **Questão de validação:** qual desconhecido precisa ser testado primeiro.

Mantenha cada análise abaixo de 250 palavras. Profundidade vem da precisão
causal, não do dramatismo.

## 7. Síntese e plano revisado

Selecione:

- **Falha mais provável:** maior plausibilidade diante do plano atual e das
  evidências existentes.
- **Falha mais perigosa:** maior dano combinado com baixa recuperabilidade,
  mesmo que seja menos provável.
- **Premissa oculta:** suposição recorrente que sustenta vários riscos e ainda
  não foi validada.
- **Revisão de maior alavancagem:** mudança capaz de reduzir vários riscos ou
  testar a premissa oculta rapidamente.

Apresente o plano revisado em uma tabela com estas colunas quando houver dados:

| Risco | Ação preventiva | Responsável | Prazo | Indicador | Gatilho/contingência |
| --- | --- | --- | --- | --- | --- |

Se o responsável for desconhecido, escreva “a definir” em vez de inventar um.
Finalize com:

- checklist pré-execução de 3–7 itens;
- critérios explícitos de go/no-go ou pausa;
- desconhecidos que ainda impedem alta confiança;
- resumo no chat com o risco principal, a premissa oculta e a ação prioritária.
