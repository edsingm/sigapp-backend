Ola{{ $userName !== '' ? ', '.$userName : '' }}!

Voce foi convidado para acessar o SIG.APP na conta {{ $tenantName }}.

Para comecar, defina sua senha de acesso pelo link abaixo:
{{ $inviteUrl }}

Este link expira em {{ $expireMinutes }} minutos.

Se voce nao esperava este convite, ignore este e-mail.

---
Atenciosamente,
Equipe SIG.APP
