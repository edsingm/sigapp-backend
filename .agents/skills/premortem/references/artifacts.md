# Artefatos de premortem

Leia esta referência somente quando o usuário pedir arquivos, relatório visual
ou transcrição. A criação de arquivos não implica permissão para publicá-los,
enviá-los ou abrir aplicativos externos.

## Local e nomes

Use a localização indicada pelo usuário. Caso não exista uma preferência, crie
os arquivos em `artifacts/premortem/` dentro do workspace:

```text
premortem-report-YYYYMMDD-HHMMSS.html
premortem-transcript-YYYYMMDD-HHMMSS.md
```

Crie somente os formatos solicitados. Não abra o HTML automaticamente; faça isso
apenas quando o usuário pedir e a autorização/ferramenta apropriada estiver
disponível.

## Relatório HTML

O relatório deve ser autocontido, responsivo e legível para impressão. Use CSS
inline ou em uma tag `style`; não use JavaScript, fontes remotas, rastreadores ou
recursos de rede.

Ordem recomendada:

1. título, objeto analisado, horizonte e marca de tempo;
2. síntese: risco mais provável, mais perigoso, premissa oculta e revisão de
   maior alavancagem;
3. matriz ou tabela de riscos com probabilidade, impacto, visibilidade,
   confiança e recuperabilidade;
4. análises aprofundadas;
5. plano revisado;
6. checklist, critérios de go/no-go e desconhecidos;
7. nota metodológica com o número real de análises independentes realizadas.

Pode usar fundo escuro e cartões, desde que haja contraste adequado, hierarquia
semântica, foco visível em links e estilos de impressão. Não afirme que agentes
foram executados se apenas passagens locais foram feitas.

## Segurança do conteúdo

- Escape todo texto vindo do usuário, de ferramentas ou de arquivos antes de
  inseri-lo no HTML. Nunca concatene HTML não confiável.
- Remova tokens, credenciais, dados pessoais desnecessários e detalhes internos
  que não sejam essenciais à decisão.
- Não inclua caminhos privados completos quando um nome relativo for suficiente.
- Não exponha raciocínio interno oculto. Mostre conclusões, evidências,
  classificações e justificativas resumidas.

## Transcrição Markdown

A transcrição pode registrar:

- cápsula de contexto;
- fatos, inferências e desconhecidos;
- listas estruturadas produzidas nas passagens independentes;
- consolidação e revisão de cobertura;
- avaliações e análises aprofundadas;
- síntese e plano revisado.

Ela não deve alegar ser uma transcrição literal do raciocínio interno nem conter
scratchpads ou cadeias de pensamento privadas.

## Verificação

Depois de gerar:

1. confirme nomes e localização dos arquivos;
2. verifique se todas as seções solicitadas existem;
3. procure conteúdo sensível e texto não escapado;
4. faça inspeção visual do HTML quando houver ferramenta de renderização;
5. entregue links locais clicáveis para os artefatos.
