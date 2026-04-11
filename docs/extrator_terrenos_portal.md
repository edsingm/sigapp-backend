# Extrator de Terrenos — Portal Hiperdados (comproterreno.com.br)

## O que foi feito

Script que autentica no portal [comproterreno.com.br](https://comproterreno.com.br) usando credenciais configuradas por variável de ambiente, acessa a página do mapa de terrenos (`/login/Terrenos/Mapa`), extrai os dados de cada polígono (id, nome, gestor, status e coordenadas) e grava o resultado em JSON.

## Arquivos criados

| Arquivo | Descrição |
|---|---|
| `app/Services/Tenant/PortalTerrenoScraperService.php` | Serviço que parseia o HTML/JS da página e extrai os campos de cada terreno |
| `database/dados_teste/extrair_terrenos_portal.php` | Script executável que faz login, busca a página e salva o JSON |
| `database/dados_teste/terrenos_portal.json` | Resultado da extração (gerado ao executar o script) |

## Como usar

### 1. Configurar credenciais

Defina a variável de ambiente com a senha do portal:

```bash
export PORTAL_TERRENOS_PASSWORD='sua_senha_aqui'
```

Opcionalmente, você também pode sobrescrever o usuário padrão (`edson@lrgconstrutora.com.br`):

```bash
export PORTAL_TERRENOS_USERNAME='outro@email.com'
```

### 2. Executar o script

```bash
php database/dados_teste/extrair_terrenos_portal.php
```

O arquivo será gerado em `database/dados_teste/terrenos_portal.json`.

### 3. Parâmetros por linha de comando (opcional)

Todos os valores podem ser passados ou sobrescritos pela linha de comando:

```bash
php database/dados_teste/extrair_terrenos_portal.php \
  --login-url=https://comproterreno.com.br/login/ \
  --target-url=https://comproterreno.com.br/login/Terrenos/Mapa \
  --username=edson@lrgconstrutora.com.br \
  --password=sua_senha \
  --output=/caminho/terrenos.json
```

## Saída

O script gera um JSON com um array de terrenos, cada um contendo:

```json
[
  {
    "id": "33975",
    "nome": "DNN33975 - Area Faculdade",
    "gestor": "Edson Maldonado",
    "status": "Descartado",
    "poligono": [
      { "lat": -22.23102863738233, "lng": -49.64705652676553 },
      { "lat": -22.2303086150564,  "lng": -49.64755005322427 }
    ]
  }
]
```

## Campos extraídos

| Campo | Origem no JS |
|---|---|
| `id` | `idterreno` ou sufixo numérico da variável `poligono_NNNNN` |
| `nome` | `descricao` |
| `gestor` | `gestor` |
| `status` | `status` |
| `poligono` | Coordenadas `lat`/`lng` do array `poligono_NNNNN` |

## Segurança

- A senha **não** está gravada em texto puro no código fonte.
- O script lê a senha da variável de ambiente `PORTAL_TERRENOS_PASSWORD`.
- O arquivo de saída `.gitignore` deve ignorar `terrenos_portal.json` para não commitar dados sensíveis.

## Validações realizadas

- Sintaxe PHP verificada com `php -l` nos 3 arquivos.
- Formatação de código verificada com Laravel Pint.
- Testes unitários do `PortalTerrenoScraperService` passando (3 testes, 11 assertions).
