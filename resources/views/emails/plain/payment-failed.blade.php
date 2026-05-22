Ola, {{ $tenantName }}!

@if ($attemptCount > 0)
Nao conseguimos processar o pagamento apos {{ $attemptCount }} {{ $attemptCount === 1 ? 'tentativa' : 'tentativas' }}.
@else
Identificamos uma pendencia no pagamento da sua assinatura.
@endif

Para evitar a suspensao da sua conta, por favor, atualize seu metodo de pagamento.
@if ($invoiceUrl)
Acesse o link para pagamento: {{ $invoiceUrl }}
@endif

Se precisar de ajuda, responda este e-mail ou entre em contato com nosso suporte.

---
Atenciosamente,
Equipe SIG.APP