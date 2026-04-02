# Relatório Técnico: Configuração e Integração do Serviço Resend no SIG.APP

## 1. Resumo Executivo
Este documento apresenta o plano detalhado para a configuração definitiva do serviço de e-mail **Resend** na aplicação SIG.APP. A análise da base de código revelou que o sistema utiliza exclusivamente o sistema de **Notifications** nativo do Laravel (via canal `mail`), não fazendo uso direto da facade `Mail` ou de classes `Mailable` soltas. O pacote `resend/resend-laravel` já encontra-se instalado no projeto, restando a parametrização de chaves, validação de templates e monitoramento de entrega.

---

## 2. Mapeamento Completo de Envios de E-mails

Abaixo está o mapeamento detalhado de todos os pontos de envio de e-mails identificados no código-fonte.

### 2.1. Boas-vindas ao Tenant
- **Arquivo/Linha:** `app/Notifications/TenantWelcomeNotification.php` (Disparado por `app/Jobs/CreateFullTenantJob.php`, linha 214-225)
- **Tipo:** Boas-vindas / Onboarding
- **Dados do Template:** `$tenantName` (nome do inquilino), `$appUrl` (URL de acesso ao sistema)
- **Destinatários:** E-mail do administrador principal do Tenant recém-criado
- **Gatilho:** Conclusão bem-sucedida do Job assíncrono de provisionamento do banco de dados do Tenant
- **Frequência:** Única vez (no momento da ativação da conta)

### 2.2. Recuperação de Senha
- **Arquivo/Linha:** `app/Notifications/TenantResetPasswordNotification.php` (Disparado por `app/Models/Tenant/User.php`, linha 125-144)
- **Tipo:** Recuperação de Senha (Password Reset)
- **Dados do Template:** `$resetUrl` (Link com token seguro), `$expireMinutes` (Tempo de expiração do link)
- **Destinatários:** Usuário do Tenant que solicitou a recuperação
- **Gatilho:** Ação do usuário na tela de "Esqueci minha senha"
- **Frequência:** Sob demanda (sempre que solicitado)

### 2.3. Abandono de Checkout (Limpeza de Inativos)
- **Arquivo/Linha:** `app/Notifications/AbandonedCheckoutNotification.php` (Disparado por `app/Jobs/CleanupPendingTenantsJob.php`, linha 113-114)
- **Tipo:** Notificação de Exclusão / Retenção
- **Dados do Template:** `$tenantName`, `$planSlug`, `$signupUrl` (URL para novo cadastro)
- **Destinatários:** E-mail do administrador do Tenant pendente (rota sob demanda via `Notification::route`)
- **Gatilho:** Execução programada do Job de limpeza que identifica contas criadas que não concluíram o pagamento/checkout
- **Frequência:** Única vez por tentativa de cadastro abandonado

### 2.4. Falha de Pagamento de Assinatura (Stripe)
- **Arquivo/Linha:** `app/Notifications/PaymentFailedNotification.php` (Disparado por `app/Services/Billing/TenantBillingService.php` linha 145-147 e `app/Http/Controllers/Api/V1/WebhookController.php` linha 295)
- **Tipo:** Alerta de Cobrança / Faturamento
- **Dados do Template:** `$tenantName`, `$attemptCount` (número de tentativas), `$invoiceUrl` (link da fatura)
- **Destinatários:** Cliente/Administrador do Tenant inadimplente
- **Gatilho:** Recebimento do webhook `invoice.payment_failed` do Stripe ou sincronização manual com status `past_due`
- **Frequência:** A cada falha de ciclo de cobrança do Stripe (conforme configurações de retry do Stripe)

### 2.5. Fim do Período de Testes (Trial Ending)
- **Arquivo/Linha:** `app/Notifications/TrialEndingNotification.php` (Disparado por `app/Http/Controllers/Api/V1/WebhookController.php`, linha 440)
- **Tipo:** Aviso de Cobrança / Fim de Trial
- **Dados do Template:** `$tenantName`, `$trialEndsAt` (data de encerramento formatada), cálculo de dias restantes
- **Destinatários:** Cliente/Administrador do Tenant em período de testes
- **Gatilho:** Webhook `customer.subscription.trial_will_end` do Stripe (geralmente enviado 3 dias antes)
- **Frequência:** Única vez por assinatura

### 2.6. Autenticação Adicional Requerida (SCA/3DS)
- **Arquivo/Linha:** Automático do Laravel Cashier (não possui arquivo customizado na base de código atual)
- **Tipo:** Segurança de Pagamento
- **Gatilho:** Processo de pagamento que requer verificação de segurança adicional pelo banco do cliente
- **Dependência:** Requer configuração da variável `CASHIER_PAYMENT_NOTIFICATION` no `.env`

---

## 3. Configurações Atuais do Serviço de E-mail

A análise dos arquivos de configuração indica que a infraestrutura para o Resend já está parcialmente preparada:
- **`composer.json`:** O pacote oficial `resend/resend-laravel` (v1.1) já está instalado.
- **`config/mail.php`:** O transportador `resend` está devidamente declarado na lista de mailers, e o default aponta para a variável de ambiente `MAIL_MAILER`.
- **`config/services.php`:** A chave da API do Resend está mapeada corretamente (`'resend' => ['key' => env('RESEND_API_KEY')]`).
- **`.env`:** A variável `MAIL_MAILER=resend` está configurada, porém a variável `RESEND_API_KEY` encontra-se **vazia**.

---

## 4. Problemas e Vulnerabilidades Identificadas

1. **Falta da Chave de API:** O envio de e-mails falhará imediatamente em produção/staging devido à ausência de valor na variável `RESEND_API_KEY`.
2. **Dependência de Filas Síncronas em Desenvolvimento:** No `.env`, `QUEUE_CONNECTION=sync` fará com que disparos de e-mail (como em Jobs ou Controllers) travem a requisição até o envio ser concluído. Para produção, deve ser `redis` ou `database`.
3. **Ausência de Monitoramento de Falhas (Dead Letter Queue):** Embora o Laravel possua suporte a jobs falhos, não há uma notificação ou métrica clara configurada para alertar a equipe técnica caso os e-mails comecem a falhar devido a limites de API ou chaves inválidas.
4. **Falta da configuração CASHIER_PAYMENT_NOTIFICATION:** Como o sistema lida com Stripe, é necessário mapear a notificação padrão do Cashier no `.env` para garantir o fluxo do 3D Secure.

---

## 5. Passo a Passo para Implementação do Resend

1. **Criar Conta e Chave no Resend:**
   - Acesse o painel do [Resend](https://resend.com) e crie uma nova API Key com permissões de envio.
2. **Configuração de Domínio (Garantia de Entrega):**
   - No Resend, adicione o domínio `sigapp.com.br`.
   - Adicione os registros DNS gerados pelo Resend (DKIM, SPF, DMARC) no gerenciador de DNS do seu domínio (Cloudflare, Route53, etc).
   - Aguarde a verificação de domínio (Status "Verified").
3. **Atualização das Variáveis de Ambiente:**
   Edite o arquivo `.env`:
   ```env
   MAIL_MAILER=resend
   RESEND_API_KEY=re_sua_chave_aqui
   MAIL_FROM_ADDRESS="noreply@sigapp.com.br"
   MAIL_FROM_NAME="SIG.APP"
   CASHIER_PAYMENT_NOTIFICATION=Laravel\Cashier\Notifications\ConfirmPayment
   ```
4. **Configuração de Filas (Produção):**
   Garanta que o `.env` de produção contenha `QUEUE_CONNECTION=redis` (ou database) para que os envios sejam assíncronos, rodando o worker com `php artisan queue:work`.

---

## 6. Exemplos de Código para Integração e Testes

Para testar a integração do Resend em ambiente de desenvolvimento sem precisar acionar fluxos complexos como faturamento ou criação de Tenant, recomenda-se criar um comando Artisan simples de teste:

```php
// routes/console.php ou em um comando app/Console/Commands/TestEmailCommand.php
use Illuminate\Support\Facades\Notification;
use App\Notifications\TenantWelcomeNotification;

Artisan::command('mail:test {email}', function (string $email) {
    $this->info("Enviando e-mail de teste para {$email} via Resend...");
    
    Notification::route('mail', $email)->notify(
        new TenantWelcomeNotification('Tenant Teste', config('app.url'))
    );
    
    $this->info("Notificação enviada para fila/transporte.");
})->purpose('Testa o envio de e-mails via Resend');
```
Uso no terminal: `php artisan mail:test seu-email@dominio.com`

---

## 7. Validação de Templates e Garantia de Entrega

- **Validação Visual (Mailables Preview):** Como as classes estendem `Notification` e utilizam o construtor nativo `MailMessage`, elas utilizam o template padrão de Markdown do Laravel. Para personalizar visualmente, publique as views do Laravel:
  ```bash
  php artisan vendor:publish --tag=laravel-mail
  ```
  Isso criará a pasta `resources/views/vendor/mail/`, permitindo estilizar o cabeçalho, rodapé e cores globais dos e-mails da aplicação.
- **Garantia de Entrega (Deliverability):** A validação do DKIM e SPF (passo 5.2) é essencial para evitar que e-mails de Boas-Vindas ou Reset de Senha caiam na caixa de Spam.

---

## 8. Métricas de Monitoramento e Logs

Para acompanhar os envios e garantir a observabilidade:

1. **Painel do Resend:** O dashboard nativo do Resend fornece métricas automáticas de *Delivered*, *Bounced*, *Spam Complaints*, *Opened* e *Clicked*.
2. **Logs da Aplicação (Fallback):** Configure o driver `failover` no `config/mail.php` caso o Resend saia do ar:
   ```php
   'default' => env('MAIL_MAILER', 'failover'),
   // ...
   'failover' => [
       'transport' => 'failover',
       'mailers' => ['resend', 'log'],
   ],
   ```
   Dessa forma, falhas na API do Resend farão com que o e-mail seja gravado no `laravel.log`, evitando a perda da mensagem.
3. **Monitoramento de Jobs Falhos:** Monitore a tabela `failed_jobs` no banco de dados. Como todos os Jobs (ex: `CreateFullTenantJob`) possuem limite de tentativas (`$tries`) conforme suas regras arquiteturais, alertas podem ser configurados (ex: integrando Slack/Discord via `queue:failing` event) para avisar a equipe se envios de e-mail estiverem falhando repetidamente.
