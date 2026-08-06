# Plano de MFA para o admin central

**Data:** 2026-08-06  
**Status:** Implementação concluída
**Escopo:** autenticação dos administradores centrais do SIGAPP

## 1. Objetivo

Adicionar autenticação multifator obrigatória para usuários centrais com `is_admin=true`, sem alterar o fluxo de autenticação dos usuários dos tenants.

O segundo fator inicial será TOTP por aplicativo autenticador. O token Sanctum do admin só será emitido depois da validação da senha e do código MFA.

## 2. Situação atual

O endpoint `POST /api/v1/admin/login` valida e-mail, senha e `is_admin`, emitindo diretamente um token Sanctum com a ability `admin` por 12 horas.

As rotas centrais protegidas usam `auth:sanctum`, `auth.central`, `central.admin` e `throttle:api-auth`. O middleware `central.admin` verifica o tipo do usuário, `is_admin` e a ability `admin` do token.

O endpoint `POST /api/v1/auth/login` é outro fluxo: funciona como broker para autenticação de usuários dos tenants. Ele não deve ser alterado por este plano.

## 3. Decisões de segurança

- MFA obrigatório para todo administrador central.
- TOTP com código numérico de seis dígitos e intervalo padrão de 30 segundos.
- Tolerância máxima de um intervalo de relógio para compensar pequenas diferenças de horário.
- Sem SMS ou e-mail como segundo fator.
- Sem opção inicial de dispositivo confiável ou bypass permanente.
- Recovery codes de uso único, exibidos somente após a configuração ou regeneração.
- Segredo TOTP criptografado em repouso.
- Recovery codes armazenados somente como hashes.
- Nenhum código TOTP ou recovery code será gravado em logs.
- Desafios MFA opacos, armazenados como hash, com expiração, limite de tentativas e consumo atômico.
- Cada timestep TOTP aceito será de uso único por fator, inclusive quando houver mais de um desafio válido para o mesmo administrador. O timestep efetivamente aceito será persistido e avançado atomicamente antes da emissão do token.
- Rotação do fator exige reautenticação com senha e fator atual.
- Configuração inicial, rotação, regeneração de recovery codes e reset serão transações atômicas, com lock do usuário e invalidação dos desafios concorrentes aplicáveis.
- Rotação e reset revogam todos os tokens Sanctum do administrador. O middleware também exige que o MFA continue confirmado, além das abilities do token.
- Tokens administrativos expiram em 12 horas e não podem ser renovados por `POST /api/v1/auth/refresh`; o administrador deve executar novamente o login completo com MFA.
- Configuração, rotação, regeneração e reset enviam notificação fora de banda ao e-mail do administrador, sem incluir segredo ou recovery codes.
- Reset emergencial será uma operação controlada e auditada, não uma ação simples baseada apenas em sessão ativa.

Referências: [OWASP Multifactor Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Multifactor_Authentication_Cheat_Sheet.html) e [RFC 6238](https://www.rfc-editor.org/rfc/rfc6238).

## 4. Fluxo proposto

```text
POST /api/v1/admin/login
    |
    |-- credenciais inválidas ----------------------> 401 genérico
    |
    |-- admin sem MFA confirmado -------------------> MFA_SETUP_REQUIRED
    |
    `-- admin com MFA confirmado -------------------> MFA_REQUIRED
                                                        |
                                                        v
POST /api/v1/admin/login/verify
    |
    |-- código inválido/expirado -------------------> erro seguro + rate limit
    |
    `-- TOTP/recovery code válido ------------------> token Sanctum
```

O primeiro endpoint nunca retorna token quando o MFA é necessário. Após credenciais válidas, responde `200` com envelope de sucesso e `data.state` igual a `MFA_SETUP_REQUIRED` ou `MFA_REQUIRED`, além de `challenge` e `expires_at`. No setup, `data.setup` contém `otpauth_uri` e a chave manual necessárias para o frontend gerar o QR Code.

Respostas que contenham desafio, `otpauth_uri`, chave manual, token ou recovery codes usarão `Cache-Control: no-store`.

O token emitido após a confirmação terá as abilities:

```php
['admin', 'admin:mfa']
```

O middleware central exigirá ambas. Tokens antigos contendo somente `admin` não poderão acessar as rotas administrativas.

O middleware também verificará `admin_mfa_confirmed_at`. Assim, um token eventualmente não revogado durante uma falha operacional de reset não continuará válido somente por possuir as abilities antigas.

## 5. API planejada

### Login e verificação

- `POST /api/v1/admin/login`
  - credenciais inválidas: `401 UNAUTHORIZED` com mensagem genérica;
  - primeiro acesso: `200`, `data.state=MFA_SETUP_REQUIRED`, desafio com validade de 10 minutos e dados de setup;
  - MFA já configurado: `200`, `data.state=MFA_REQUIRED` e desafio com validade de 5 minutos;
  - não retorna token antes do segundo fator.

- `POST /api/v1/admin/login/verify`
  - recebe `challenge` e exatamente um entre `code` TOTP ou `recovery_code`;
  - setup inicial aceita somente `code` TOTP;
  - em configuração inicial, confirma o segredo e gera os recovery codes;
  - em sucesso, emite o token administrativo;
  - retorna o usuário via `CentralUserResource`, `token`, `expires_at` e, somente no setup, os recovery codes;
  - desafio inválido, expirado, consumido ou invalidado retorna `401 MFA_CHALLENGE_INVALID` sem distinguir o motivo;
  - código inválido retorna `422 MFA_CODE_INVALID` e incrementa atomicamente as tentativas;
  - retorna recovery codes somente na criação/regeneração, nunca em consultas posteriores.

### Administração do fator

- `GET /api/v1/admin/mfa` — status do MFA e quantidade de recovery codes restantes, sem expor segredos.
- `POST /api/v1/admin/mfa/rotate` — recebe senha e fator atual, inicia a troca, mantém o segredo vigente ativo e retorna desafio de rotação com validade de 10 minutos e os dados do novo autenticador.
- `POST /api/v1/admin/mfa/rotate/verify` — recebe o desafio e um TOTP gerado pelo novo segredo; confirma a rotação, substitui os recovery codes, invalida desafios anteriores e revoga todos os tokens do administrador.
- `POST /api/v1/admin/mfa/recovery-codes` — regenera e substitui atomicamente todos os recovery codes após senha + fator atual. Um recovery code usado como fator atual é consumido na mesma transação.
- `POST /api/v1/auth/refresh` — para usuário central administrativo, retorna `422 ADMIN_MFA_REAUTH_REQUIRED`; o comportamento tenant permanece inalterado.
- Comando `admin:mfa-reset {email} --operator= --reason=` para reset emergencial. Exige confirmação interativa, operador e justificativa; remove segredo/confirmation, recovery codes e desafios, incrementa a versão do fator, revoga tokens e persiste a auditoria obrigatória na mesma operação. Se a auditoria falhar, o reset não é concluído.

As rotas de login/verificação ficam no domínio central com `central.context` e limiters próprios, sem `auth:sanctum`. As rotas de gestão ficam no grupo autenticado com `central.context`, `auth:sanctum`, `auth.central`, `central.admin` e throttle. Todas as respostas seguirão o envelope existente de `ApiResponseService` e usarão Resources quando houver dados de usuário.

## 6. Persistência

Criar migrations centrais novas, sem editar migrations históricas.

### Usuário central

Adicionar ao usuário central:

- `admin_mfa_secret`, `text`, nullable, com cast `encrypted`;
- `admin_mfa_confirmed_at`, nullable, com cast `datetime`;
- `admin_mfa_last_used_timestep`, `bigInteger`, nullable, para impedir obrigatoriamente a reutilização de um TOTP já aceito;
- `admin_mfa_version`, inteiro sem sinal, com default `0`, incrementado a cada setup confirmado, rotação, regeneração de recovery codes ou reset para invalidar operações pendentes de uma versão anterior.

O segredo não será incluído em `$fillable` nem em Resources e será incluído no `#[Hidden]` do model `App\Models\User`. Os novos models também ocultarão hashes e segredos pendentes. Haverá teste explícito de `toArray()` para impedir regressão de serialização.

### Desafios MFA

Nova tabela central para:

- usuário;
- hash SHA-256 único de um desafio aleatório de pelo menos 256 bits; o valor em claro existe somente na resposta;
- finalidade obrigatória via enum (`setup`, `login` ou `rotate`);
- estado obrigatório via enum (`pending`, `consumed` ou `invalidated`);
- versão do fator capturada na criação;
- segredo pendente em coluna `text` com cast `encrypted`, somente para `setup` e `rotate`;
- IP e user-agent;
- nome do dispositivo;
- quantidade de tentativas;
- expiração;
- data de consumo;
- data de invalidação;
- timestamps.

O hash do desafio será `unique`, e as FKs de desafios e recovery codes usarão cascade no delete do usuário. Haverá índices para usuário/finalidade/estado e para expiração. Desafios expirados ou invalidados serão removidos pelo comando `admin:mfa-cleanup`, agendado diariamente com nome único, `onOneServer()` e `withoutOverlapping()`.

### Recovery codes

Nova tabela central para:

- usuário;
- hash do código;
- data de uso;
- timestamps.

Serão gerados dez códigos com entropia criptográfica. O consumo usa hash seguro e uma atualização condicional de `used_at IS NULL` sob transação para impedir uso concorrente. A tabela terá índice em `(user_id, used_at)`. Regeneração invalida todos os códigos anteriores antes de persistir o novo conjunto, dentro da mesma transação.

### Transações e concorrência

- A verificação carrega desafio e usuário com lock, valida expiração, estado, finalidade e `admin_mfa_version`, e incrementa tentativas atomicamente.
- Setup só confirma quando `admin_mfa_confirmed_at IS NULL`; o primeiro desafio vencedor instala o segredo, grava como último timestep o código usado na confirmação, incrementa a versão e invalida todos os demais desafios de setup daquele usuário.
- Rotação só confirma se a versão do fator ainda for a capturada pelo desafio. A validação do segredo pendente não usa o timestep do fator antigo; ao instalar o segredo novo, `admin_mfa_last_used_timestep` passa a ser o timestep usado na confirmação. A troca, incremento da versão, substituição dos recovery codes, consumo/invalidação dos desafios e revogação dos tokens ocorre na mesma transação.
- Regeneração de recovery codes incrementa a versão do fator e invalida desafios pendentes, mas preserva o segredo TOTP e seu último timestep aceito.
- Reset incrementa a versão e limpa segredo, confirmação e último timestep.
- Ao validar o TOTP do fator ativo, o timestep correspondente dentro da janela tolerada deve ser maior que `admin_mfa_last_used_timestep`. A gravação do timestep ocorre antes da emissão do token e sob o mesmo lock.
- Emissão do token e consumo de recovery code/desafio pertencem à mesma transação lógica: falha em qualquer etapa não pode deixar um código consumido sem token nem emitir token sem consumo persistido.

## 7. Arquitetura de código

Adicionar, respeitando Controller → Service → Repository:

- `AdminMfaService`;
- repository e contrato para desafios e recovery codes;
- models em `app/Models/Central/`;
- factories para todos os novos models;
- FormRequests para login MFA e gestão do fator;
- controller central fino;
- enums para finalidade e estado do desafio;
- rate limiter `admin-mfa`;
- exceções de domínio para desafio inválido, código inválido/reutilizado e conflito de versão;
- eventos/listeners e auditoria para configuração, rotação, falha, uso de recovery code, regeneração e reset;
- comando `admin:mfa-cleanup` e schedule protegido;
- traduções dos novos códigos/mensagens em `pt-br.json` e `en-us.json`.

Alterar:

- `AdminAuthService` para retornar desafio em vez de emitir token imediatamente;
- `AdminController` para responder ao novo contrato;
- `EnsureUserIsAdmin` para exigir `admin:mfa`;
- `AdminLoginAttemptLogger` para registrar o estágio e motivo da falha sem dados sensíveis;
- migration, model, repository, Resource e filtros de tentativas de login para incluir o estágio (`password`, `mfa` ou `recovery`) e manter `successful` como resultado daquele estágio;
- `routes/api.php` para a rota de verificação e as rotas de gestão;
- `AppServiceProvider` para os binds e o novo limiter;
- `TenantAuthController@refresh` para impedir renovação de token do admin central sem alterar o refresh tenant;
- `AGENTS.md`, pois a mudança adiciona rotas, autenticação, comando e schedule relevantes para as próximas implementações.

Não será usado Fortify integralmente, pois o projeto possui autenticação API própria, Sanctum e envelope de resposta customizado. Uma biblioteca TOTP compatível com RFC 6238 deverá ser usada em vez de implementar criptografia e Base32 manualmente. `spomky-labs/otphp` é a dependência candidata, mas sua instalação exige aprovação explícita antes de alterar `composer.json`/`composer.lock`.

## 8. Rate limiting e antiabuso

Manter o limiter atual de login administrativo e adicionar um limiter específico para MFA com buckets independentes, nunca uma única chave composta que possa ser contornada variando o desafio:

- por IP: 20 verificações a cada 10 minutos;
- por conta identificada pelo desafio: 10 falhas a cada 10 minutos;
- por desafio: máximo de 5 tentativas durante sua vida, após o qual será invalidado;
- resposta no envelope padrão com `429 TOO_MANY_REQUESTS`.

O bucket de IP será aplicado antes da resolução do desafio, inclusive para hashes inexistentes. Os buckets de conta e desafio serão aplicados depois de resolver um desafio válido. Desafios expirados, consumidos, invalidados ou inexistentes devem ter a mesma resposta segura e não revelar detalhes sobre a existência de usuário.

## 9. Rollout no ambiente de desenvolvimento

Como o sistema ainda não está em produção:

1. Criar migrations novas e atualizar o código diretamente para o contrato MFA.
2. Fazer com que todos os administradores existentes configurem MFA no próximo login.
3. Não manter emissão de tokens legados com apenas `admin`.
4. Fazer `EnsureUserIsAdmin` exigir `admin:mfa` desde a primeira entrega.
5. Fazer `EnsureUserIsAdmin` exigir também MFA confirmado no usuário.
6. Atualizar factories e fixtures de testes para tokens com `admin` + `admin:mfa` e usuário com MFA confirmado.
7. Revogar todos os tokens centrais administrativos existentes no rollout local; o middleware continua bloqueando qualquer token legado remanescente.
8. Atualizar o frontend para os estados `MFA_SETUP_REQUIRED` e `MFA_REQUIRED`, rotação em duas etapas, exibição única de recovery codes e relogin após expiração.
9. Documentar o comando de reset, o cleanup agendado e o procedimento de sincronização de relógio dos servidores.

Não será criada uma feature flag de compatibilidade nem uma janela de dupla emissão de tokens.

## 10. Testes obrigatórios

### Feature

- login inválido continua retornando 401 genérico;
- admin sem MFA recebe desafio de configuração;
- admin com MFA recebe desafio sem token;
- TOTP válido emite token;
- TOTP inválido não emite token;
- o mesmo timestep TOTP não pode ser reutilizado em outro desafio;
- desafio expirado é rejeitado;
- desafio consumido não pode ser reutilizado;
- desafio invalidado por uma configuração concorrente é rejeitado;
- entre dois setups concorrentes, apenas um confirma o fator;
- recovery code válido funciona;
- recovery code usado novamente falha;
- regeneração substitui e invalida todo o conjunto anterior;
- rate limit MFA retorna 429;
- variar desafios não contorna o limite por IP ou por conta;
- token sem `admin:mfa` retorna 403;
- token com `admin:mfa` de usuário cujo MFA foi resetado retorna 403;
- token completo acessa rotas administrativas;
- rotação confirma somente com código do segredo novo;
- rotação e reset revogam todos os tokens anteriores;
- desafio de rotação antigo não sobrescreve uma rotação mais nova;
- refresh de admin central exige novo login MFA e refresh tenant permanece funcionando;
- respostas de setup, token e recovery codes incluem `Cache-Control: no-store`;
- eventos de configuração, rotação e reset disparam notificação sem material secreto;
- fluxo tenant permanece funcionando.

### Unit

- geração e validação TOTP;
- normalização de códigos;
- geração e hash de recovery codes;
- expiração e consumo atômico de desafios;
- seleção do timestep aceito dentro da janela e prevenção de replay;
- conflito de versão e concorrência de setup/rotação;
- rotação, regeneração e reset do fator;
- auditoria obrigatória do reset e limpeza de desafios expirados.

### Arquitetura

- controllers sem queries diretas;
- services sem `Request` e sem Eloquent direto;
- rotas centrais com contexto e throttle;
- route cache preservado;
- Resources e `toArray()` sem segredo MFA, segredo pendente, hashes ou recovery codes;
- schedule de limpeza com nome único, `onOneServer()` e `withoutOverlapping()`.

## 11. Critérios de aceite

- Nenhum token administrativo é emitido somente com senha.
- Todo token administrativo válido possui `admin:mfa`.
- O segredo TOTP nunca aparece em logs, Resources ou respostas posteriores.
- Recovery codes são exibidos uma única vez e consumidos individualmente.
- Um timestep TOTP aceito não pode ser reutilizado, mesmo em desafios concorrentes.
- Apenas uma configuração/rotação concorrente pode vencer para cada versão do fator.
- Tokens antigos não conseguem acessar o painel.
- Rotação e reset revogam todos os tokens administrativos existentes.
- Tokens administrativos não podem estender indefinidamente a sessão via refresh.
- Segredos e recovery codes não são serializados por models e respostas sensíveis não são armazenáveis em cache.
- O login dos tenants não sofre alteração.
- PHPUnit, arquitetura, Pint e PHPStan permanecem verdes.
- A documentação da API e o frontend refletem os novos estados de autenticação.

## 12. Fora do MVP

- Passkeys/WebAuthn;
- MFA por SMS ou e-mail;
- dispositivo confiável permanente;
- MFA para usuários tenant;
- autenticação adaptativa baseada em risco.

Passkeys podem ser avaliadas posteriormente como fator resistente a phishing; o Laravel documenta suporte a passkeys via WebAuthn no Fortify.
