# Importação Hiperdados via Admin Central

## Objetivo

Permitir que o admin da plataforma migre terrenos do portal legado (`comproterreno.com.br` / Hiperdados) para o schema de um tenant SIGAPP, sem linha de comando.

## Fluxo

1. **Start** — `POST /api/v1/admin/hiperdados-imports` com `username`, `password` e `limit` opcional  
   - Credenciais criptografadas (`Crypt`) só durante o fetch  
   - Job `FetchHiperdadosImportJob` (fila `exports`)
2. **Fetch em lotes** — extrai lista do mapa e enriquece ficha/formulário/corretores em batches de 20  
   - Progresso em `processed_count` / `total_count`  
   - Estado intermediário em `storage/app/private/imports/hiperdados/{uuid}.work.json`
3. **Ready** — JSON final em `{uuid}.json`; credenciais apagadas  
4. **Preview** — `GET .../{uuid}/preview`  
5. **Commit** — `POST .../{uuid}/commit` com `tenant_id`  
   - Job `CommitHiperdadosImportJob` roda `HiperdadosTerrenoCommitService` no schema do tenant (upsert por nome)

## UI

- `frontend_admin` → `/hiperdados`  
- BFF: `/api/admin/hiperdados-imports*`

## Pré-requisitos operacionais

```bash
# Backend
php artisan migrate
php artisan queue:work --queue=exports,default

# Admin frontend
npm run dev
```

## CLI legado (ainda válido)

```bash
php database/dados_teste/Hiperdados/extrair_terrenos_portal.php
php artisan portal:enriquecer-terrenos
php artisan tenants:seed --class='Database\Seeders\Tenant\TerrenosPortalSeeder' --tenants=TENANT_ID
```

O seeder agora delega para `HiperdadosTerrenoCommitService` (mesma lógica da UI).
