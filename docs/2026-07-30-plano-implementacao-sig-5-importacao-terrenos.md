# SIG-5 — Importação de terrenos por Excel e polígonos KMZ

## Resumo

Implementar dois fluxos integrados:

1. Importação cadastral por `.xlsx`, com validação assíncrona, pré-visualização e confirmação atômica.
2. Importação de múltiplos KML/KMZ, extraindo todos os polígonos para uma área pendente do mapa e permitindo vinculá-los a terrenos cadastrados ou importados.

A entrega usa a feature e as permissões atuais de Prospecção, sem novo entitlement. Os jobs usam a fila `exports`.

## Importação por Excel

- Persistir a execução e suas linhas em `terreno_imports` e `terreno_import_rows`.
- Estados: `queued`, `validating`, `invalid`, `ready`, `importing`, `completed`, `failed` e `expired`.
- Disponibilizar template com abas `Terrenos`, `Instruções` e `Referências`; somente `nome` é obrigatório.
- Resolver usuários e corretores por e-mail, regional por nome e cidade pelo código IBGE ou por cidade + UF exatas.
- Rejeitar fórmulas, cabeçalhos desconhecidos, referências ausentes, datas inválidas e duplicatas por nome, cidade e endereço.
- Aceitar `.xlsx` de até 10 MB e 1.000 linhas. Não criar terrenos durante a validação.
- Confirmar somente imports sem erros. Sob o lock do limite do plano, revalidar capacidade e duplicidades e criar tudo em uma única transação.
- Reutilizar o fluxo de criação do `TerrenoService`, com autoria e workflow inicial `EM_ANALISE`; não importar workflow, geometria ou áreas calculadas.
- Contabilizar o arquivo na quota enquanto existir, apagá-lo ao terminar a validação e manter metadados/linhas por 30 dias.

## Importação e associação de polígonos

- Persistir lotes, arquivos e geometrias em `terreno_polygon_imports`, `terreno_polygon_import_files` e `terreno_pending_polygons`.
- Evoluir `KmzParserService` com `parseMany()`, preservando `parse()` para compatibilidade.
- Extrair todos os `Polygon` de `Placemark` e `MultiGeometry`; tratar cada geometria como um polígono independente.
- Bloquear XML externo, ZIPs com mais de 100 entradas, mais de 20 MB descompactados, coordenadas inválidas e polígonos com buracos.
- Aceitar até 10 arquivos, 10 MB por arquivo e 40 MB agregados. Preservar resultados válidos quando outro arquivo do lote falhar.
- Deduplicar por hash e expor polígonos pendentes como GeoJSON filtrado pela bounding box do mapa.
- Vincular sob lock transacional. Não sobrescrever terreno que já possua geometria; repetir o mesmo vínculo é idempotente.
- Copiar coordenadas para `terrenos.polygon_coords`, permitindo que o observer existente dispare o cálculo de área.

## Contratos HTTP

- `GET /api/v1/terrenos/imports/template`
- `POST /api/v1/terrenos/imports`
- `GET /api/v1/terrenos/imports/{import}`
- `GET /api/v1/terrenos/imports/{import}/rows`
- `POST /api/v1/terrenos/imports/{import}/confirm`
- `GET /api/v1/terrenos/imports/{import}/errors`
- `POST /api/v1/terrenos/polygon-imports`
- `GET /api/v1/terrenos/polygon-imports/{import}`
- `GET /api/v1/terrenos/polygons?bbox=minLng,minLat,maxLng,maxLat`
- `POST /api/v1/terrenos/polygons/{polygon}/link`
- `DELETE /api/v1/terrenos/polygons/{polygon}`

Uploads exigem `idempotency_key`, retornam `202` e usam o rate limiter `terrain-imports`. Acesso usa `prospection`, `prospection.terrains`, `prospection.maps`, policies e o limite atual de terrenos.

## Testes e aceite

- Validar template, fórmulas, referências, cidades, datas, valores, duplicatas, limite de linhas e relatório de erros.
- Garantir que uma planilha inválida não crie terrenos e que confirmação repetida não duplique dados.
- Cobrir concorrência no último slot do plano e rollback integral diante de falha.
- Cobrir múltiplos arquivos, múltiplos placemarks, `MultiGeometry`, XML externo, ZIP bomb, coordenadas inválidas e hash duplicado.
- Validar isolamento tenant, autorização, GeoJSON por bounding box, associação concorrente, bloqueio de sobrescrita e disparo do cálculo de área.
- Rodar testes focados, Architecture, suíte completa, PHPStan nível 8 e Pint em modo de verificação.

## Decisões

- Excel e KMZ fazem parte da mesma entrega.
- Excel é criação atômica, sem upsert ou importação parcial.
- Referências ausentes não são criadas.
- Não há novo entitlement comercial.
- Polígonos existentes nunca são substituídos pelo novo fluxo.
- A interação visual do mapa pertence ao frontend separado.
