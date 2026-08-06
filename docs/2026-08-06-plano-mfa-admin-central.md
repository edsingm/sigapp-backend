# Plano de MFA para o admin central

**Data:** 2026-08-06  
**Status:** Planejamento  
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
- Rotação do fator exige reautenticação com senha e fator atual.
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

O primeiro endpoint nunca retorna token quando o MFA é necessário. A resposta contém somente um desafio temporário e, no caso de configuração inicial, os dados necessários para o frontend gerar o QR Code.

O token emitido após a confirmação terá as abilities:

```php
['admin', 'admin:mfa']
```

O middleware central exigirá ambas. Tokens antigos contendo somente `admin` não poderão acessar as rotas administrativas.

## 5. API planejada

### Login e verificação

- `POST /api/v1/admin/login`
  - credenciais inválidas: `401 UNAUTHORIZED` com mensagem genérica;
  - primeiro acesso: `MFA_SETUP_REQUIRED`;
  - MFA já configurado: `MFA_REQUIRED`;
  - não retorna token antes do segundo fator.

- `POST /api/v1/admin/login/verify`
  - recebe o desafio e um `code` TOTP ou `recovery_code`;
  - em configuração inicial, confirma o segredo e gera os recovery codes;
  - em sucesso, emite o token administrativo;
  - retorna recovery codes somente na criação/regeneração, nunca em consultas posteriores.

### Administração do fator

- `GET /api/v1/admin/mfa` — status do MFA e quantidade de recovery codes restantes, sem expor segredos.
- `POST /api/v1/admin/mfa/rotate` — inicia a troca do autenticador após reautenticação.
- `POST /api/v1/admin/mfa/recovery-codes` — regenera recovery codes após senha + fator atual.
- Comando operacional auditado para reset emergencial durante o desenvolvimento e, futuramente, procedimento administrativo equivalente.

Todas as respostas seguirão o envelope existente de `ApiResponseService` e usarão Resources quando houver dados de usuário.

## 6. Persistência

Criar migrations centrais novas, sem editar migrations históricas.

### Usuário central

Adicionar ao usuário central:

- `admin_mfa_secret`, nullable, com cast `encrypted`;
- `admin_mfa_confirmed_at`, nullable, com cast `datetime`;
- campo opcional para impedir reutilização do mesmo intervalo TOTP, caso necessário após os testes.

O segredo não será incluído em `$fillable` nem em Resources.

### Desafios MFA

Nova tabela central para:

- usuário;
- hash do desafio;
- finalidade (`setup` ou `login`);
- segredo pendente criptografado, quando aplicável;
- IP e user-agent;
- nome do dispositivo;
- quantidade de tentativas;
- expiração;
- data de consumo;
- timestamps.

### Recovery codes

Nova tabela central para:

- usuário;
- hash do código;
- data de uso;
- timestamps.

O consumo deve usar uma operação condicional para impedir uso concorrente do mesmo código.

## 7. Arquitetura de código

Adicionar, respeitando Controller → Service → Repository:

- `AdminMfaService`;
- repository e contrato para desafios e recovery codes;
- models em `app/Models/Central/`;
- FormRequests para login MFA e gestão do fator;
- controller central fino;
- enum para finalidade/status do desafio, se necessário;
- rate limiter `admin-mfa`;
- eventos ou auditoria para configuração, rotação, falha, uso de recovery code e reset.

Alterar:

- `AdminAuthService` para retornar desafio em vez de emitir token imediatamente;
- `AdminController` para responder ao novo contrato;
- `EnsureUserIsAdmin` para exigir `admin:mfa`;
- `AdminLoginAttemptLogger` para registrar o estágio e motivo da falha sem dados sensíveis;
- `routes/api.php` para a rota de verificação e as rotas de gestão;
- `AppServiceProvider` para o novo limiter.

Não será usado Fortify integralmente, pois o projeto possui autenticação API própria, Sanctum e envelope de resposta customizado. Uma biblioteca TOTP compatível com RFC 6238 deverá ser usada em vez de implementar criptografia e Base32 manualmente. `spomky-labs/otphp` é a dependência candidata, mas sua instalação exige aprovação explícita antes de alterar `composer.json`/`composer.lock`.

## 8. Rate limiting e antiabuso

Manter o limiter atual de login administrativo e adicionar um limiter específico para MFA:

- por IP + desafio;
- com proteção adicional por conta quando o usuário já puder ser identificado;
- máximo de tentativas por desafio, após o qual o desafio será invalidado;
- resposta no envelope padrão com `429 TOO_MANY_REQUESTS`.

Desafios expirados, consumidos ou inválidos devem ter resposta segura e não revelar detalhes sobre a existência de usuário.

## 9. Rollout no ambiente de desenvolvimento

Como o sistema ainda não está em produção:

1. Criar migrations novas e atualizar o código diretamente para o contrato MFA.
2. Fazer com que todos os administradores existentes configurem MFA no próximo login.
3. Não manter emissão de tokens legados com apenas `admin`.
4. Fazer `EnsureUserIsAdmin` exigir `admin:mfa` desde a primeira entrega.
5. Atualizar factories e fixtures de testes para tokens com `admin` + `admin:mfa`.
6. Revogar ou ignorar tokens antigos locais durante a validação.
7. Atualizar o frontend para os estados `MFA_SETUP_REQUIRED` e `MFA_REQUIRED`.

Não será criada uma feature flag de compatibilidade nem uma janela de dupla emissão de tokens.

## 10. Testes obrigatórios

### Feature

- login inválido continua retornando 401 genérico;
- admin sem MFA recebe desafio de configuração;
- admin com MFA recebe desafio sem token;
- TOTP válido emite token;
- TOTP inválido não emite token;
- desafio expirado é rejeitado;
- desafio consumido não pode ser reutilizado;
- recovery code válido funciona;
- recovery code usado novamente falha;
- rate limit MFA retorna 429;
- token sem `admin:mfa` retorna 403;
- token completo acessa rotas administrativas;
- fluxo tenant permanece funcionando.

### Unit

- geração e validação TOTP;
- normalização de códigos;
- geração e hash de recovery codes;
- expiração e consumo atômico de desafios;
- rotação e reset do fator.

### Arquitetura

- controllers sem queries diretas;
- services sem `Request` e sem Eloquent direto;
- rotas centrais com contexto e throttle;
- route cache preservado;
- Resources sem segredo MFA ou recovery codes.

## 11. Critérios de aceite

- Nenhum token administrativo é emitido somente com senha.
- Todo token administrativo válido possui `admin:mfa`.
- O segredo TOTP nunca aparece em logs, Resources ou respostas posteriores.
- Recovery codes são exibidos uma única vez e consumidos individualmente.
- Tokens antigos não conseguem acessar o painel.
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
